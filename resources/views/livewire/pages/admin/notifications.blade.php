<?php

use App\Models\AdminNotification;
use App\Jobs\SendAdminNotificationPushJob;
use Illuminate\Support\Facades\Bus;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public string $search = '';
    public string $statusFilter = '';
    public string $successMessage = '';
    public string $toastType = 'success';

    public ?int $notificationId = null;
    public string $title = '';
    public string $title_ms = '';
    public string $title_zh = '';
    public string $message = '';
    public string $message_ms = '';
    public string $message_zh = '';
    public string $notification_type = 'announcement';
    public string $scheduled_at = '';
    public bool $isViewMode = false;

    /** When set, UI shows sending progress until the push job marks the row as `sent`. */
    public ?int $pendingSendId = null;

    public function with(): array
    {
        $allowed = ['title', 'notification_type', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'created_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'notifications' => AdminNotification::query()
                ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->orderBy($sortField, $sortDirection)
                ->paginate(10),
            'pendingSend' => $this->pendingSendId
                ? AdminNotification::query()->find($this->pendingSendId)
                : null,
        ];
    }

    public function pollSendProgress(): void
    {
        if ($this->pendingSendId === null) {
            return;
        }

        $n = AdminNotification::query()->find($this->pendingSendId);
        if (! $n) {
            $this->pendingSendId = null;

            return;
        }

        if ($n->status === 'sent') {
            $this->pendingSendId = null;
            $this->resetPage();
            $this->showToast('Notification sent successfully.');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['title', 'notification_type', 'status', 'created_at'];
        if (! in_array($field, $allowed, true)) return;
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortField !== $field) return '';
        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = __($message);
        $this->toastType = $type;
        $this->js("
            window.dispatchEvent(new CustomEvent('pk-notification-toast', { detail: { message: " . json_encode($message) . ", type: " . json_encode($type) . " } }));
        ");
    }

    public function saveDraft(): void
    {
        $this->save(false);
    }

    public function sendPush(): void
    {
        $this->save(true);
    }

    private function save(bool $sendNow = false): void
    {
        if ($this->isViewMode) {
            return;
        }

        abort_unless(auth()->user()->can('notifications.create'), 403);

        $this->validate([
            'title' => ['required', 'string'],
            'title_ms' => ['required', 'string'],
            'title_zh' => ['required', 'string'],
            'message' => ['required', 'string'],
            'message_ms' => ['required', 'string'],
            'message_zh' => ['required', 'string'],
        ]);

        $notification = AdminNotification::create([
            'title' => $this->title,
            'title_ms' => $this->title_ms,
            'title_zh' => $this->title_zh,
            'message' => $this->message,
            'message_ms' => $this->message_ms,
            'message_zh' => $this->message_zh,
            'notification_type'  => $this->notification_type,
            'target_audience'    => 'all',
            'scheduled_at'       => $sendNow ? now() : ($this->scheduled_at ?: null),
            'sent_at'            => null,
            'status'             => $sendNow ? 'processing' : ($this->scheduled_at ? 'scheduled' : 'draft'),
        ]);

        if ($sendNow) {
            if (config('queue.default') === 'sync') {
                Bus::dispatchSync(new SendAdminNotificationPushJob($notification->id));
                $this->showToast('Notification sent successfully.');
            } else {
                SendAdminNotificationPushJob::dispatch($notification->id);
                $this->pendingSendId = $notification->id;
                $this->showToast('Sending push notification…');
            }
        } else {
            $this->showToast('Notification saved successfully.');
        }

        $this->resetPage();
        $this->resetForm();
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->isViewMode = false;
        $this->showToast('Ready to create a new notification.');
        $this->js("window.dispatchEvent(new CustomEvent('notification-form-open'));");
    }

    public function viewNotification(int $id): void
    {
        abort_unless(auth()->user()->can('notifications.view'), 403);

        $notification = AdminNotification::findOrFail($id);
        $this->notificationId = $notification->id;
        $this->title = (string) $notification->title;
        $this->title_ms = (string) ($notification->title_ms ?? '');
        $this->title_zh = (string) ($notification->title_zh ?? '');
        $this->message = (string) $notification->message;
        $this->message_ms = (string) ($notification->message_ms ?? '');
        $this->message_zh = (string) ($notification->message_zh ?? '');
        $this->notification_type = (string) ($notification->notification_type ?? 'announcement');
        $this->scheduled_at = optional($notification->scheduled_at)->format('Y-m-d\TH:i') ?? '';
        $this->isViewMode = true;

        $this->showToast('Viewing notification details.');
        $this->js("window.dispatchEvent(new CustomEvent('notification-form-open'));");
    }

    public function deleteNotification(int $id): void
    {
        abort_unless(auth()->user()->can('notifications.delete'), 403);

        $notification = AdminNotification::query()->findOrFail($id);

        if ($this->pendingSendId === $id) {
            $this->pendingSendId = null;
        }
        if ($this->notificationId === $id) {
            $this->resetForm();
        }

        $notification->delete();
        $this->resetPage();
        $this->showToast('Notification deleted.', 'error');
    }

    private function resetForm(): void
    {
        $this->reset([
            'notificationId',
            'title',
            'title_ms',
            'title_zh',
            'message',
            'message_ms',
            'message_zh',
            'notification_type',
            'scheduled_at',
            'isViewMode',
        ]);
        $this->notification_type = 'announcement';
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">{{ __('Push Notification Manager') }}</h1>
        <p class="pk-subtitle">{{ __('Send push notifications to Flutter app users via Firebase Cloud Messaging.') }}</p>
    </div>
</x-slot>

<div class="space-y-6">
    {{-- Inline toast --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        @pk-notification-toast.window="message = $event.detail.message; type = $event.detail.type; show = true; setTimeout(() => show = false, 3500)"
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed bottom-5 right-5 z-[150] w-[calc(100vw-2.5rem)] max-w-sm rounded-xl px-4 py-3 text-sm font-medium text-white shadow-lg sm:w-80"
        :class="type === 'success' ? 'bg-emerald-500' : 'bg-rose-500'"
        x-text="message"
    ></div>

    {{-- Search and Filter bar --}}
    <section class="pk-filter-bar">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by title...') }}" class="pk-input w-full sm:max-w-sm">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">{{ __('All Status') }}</option>
            <option value="draft">{{ __('Draft') }}</option>
            <option value="scheduled">{{ __('Scheduled') }}</option>
            <option value="processing">{{ __('Processing') }}</option>
            <option value="sent">{{ __('Sent') }}</option>
        </select>
    </section>

    @if($pendingSendId)
        <div
            wire:poll.2s="pollSendProgress"
            class="flex flex-wrap items-center gap-3 rounded-xl border px-4 py-3 text-sm
                {{ $pendingSend && $pendingSend->status === 'processing' ? 'border-amber-200 bg-amber-50 text-amber-900' : 'border-slate-200 bg-slate-50 text-slate-600' }}"
        >
            @if($pendingSend && $pendingSend->status === 'processing')
                <svg class="h-5 w-5 shrink-0 animate-spin text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <div>
                    <p class="font-semibold">{{ __('Sending…') }}</p>
                    <p class="text-xs text-amber-800/90">{{ __('Status is') }} <span class="font-mono">processing</span> {{ __('until Firebase delivery finishes. This updates automatically.') }}</p>
                </div>
            @else
                <p class="text-xs">{{ __('Updating send status…') }}</p>
            @endif
        </div>
    @endif

<div class="grid gap-6 xl:grid-cols-3">
    {{-- Form card (collapsible on mobile) --}}
    <section
        class="pk-card w-full self-start p-5"
        x-data="{ open: window.matchMedia('(min-width: 768px)').matches, activeLang: 'en' }"
        x-init="
            const mq = window.matchMedia('(min-width: 768px)');
            const sync = () => { if (mq.matches) open = true; };
            if (mq.addEventListener) mq.addEventListener('change', sync);
            else mq.addListener(sync);
        "
        @notification-form-open.window="open = true"
    >
        <div class="mb-4 rounded-2xl border border-slate-200 bg-slate-50 p-1">
            <div class="grid grid-cols-3 gap-1">
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-xs font-semibold transition"
                    :class="activeLang === 'en'
                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-indigo-200'
                        : 'text-slate-500 hover:bg-white/70'"
                    @click="activeLang = 'en'"
                >
                    English
                </button>
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-xs font-semibold transition"
                    :class="activeLang === 'ms'
                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-indigo-200'
                        : 'text-slate-500 hover:bg-white/70'"
                    @click="activeLang = 'ms'"
                >
                    Malay
                </button>
                <button
                    type="button"
                    class="rounded-xl px-3 py-2 text-xs font-semibold transition"
                    :class="activeLang === 'zh'
                        ? 'bg-white text-slate-900 shadow-sm ring-1 ring-indigo-200'
                        : 'text-slate-500 hover:bg-white/70'"
                    @click="activeLang = 'zh'"
                >
                    Chinese
                </button>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-slate-800">{{ __($isViewMode ? 'View Notification' : 'Send Notification') }}</h2>
            @if($isViewMode)
                <button type="button" class="pk-btn-primary shrink-0" wire:click="startCreate">{{ __('Create New') }}</button>
            @endif
            <button
                type="button"
                class="flex items-center justify-center rounded-xl border p-2 text-slate-500 transition hover:bg-slate-50 md:hidden"
                @click="open = !open"
            >
                <svg x-show="!open" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                <svg x-show="open" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
        </div>

        <div x-show="open" x-collapse>
            @if($errors->any())
                <div class="mt-4 rounded-xl border px-3 py-2 text-sm font-medium" style="border-color:#fda4af;background-color:#fff1f2;color:#9f1239;">
                    @php
                        $missingLanguages = collect($errors->keys())->reduce(function (array $carry, string $key): array {
                            if (str_contains($key, '_ms')) {
                                $carry['Malay (BM)'] = true;
                            } elseif (str_contains($key, '_zh')) {
                                $carry['Chinese (ZH)'] = true;
                            } else {
                                $carry['English (EN)'] = true;
                            }

                            return $carry;
                        }, []);
                    @endphp
                    Please fill all required fields. Missing: {{ implode(', ', array_keys($missingLanguages)) }}.
                </div>
            @endif

            <form wire:submit="saveDraft" class="mt-4 space-y-3">
                <x-admin.form.group label="Title *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <input class="{{ $errors->has('title') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title" placeholder="{{ __('e.g. New Rewards Available') }}" @disabled($isViewMode)>
                        @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <input class="{{ $errors->has('title_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title_ms" placeholder="{{ __('Malay title') }}" @disabled($isViewMode)>
                        @error('title_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <input class="{{ $errors->has('title_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title_zh" placeholder="{{ __('Chinese title') }}" @disabled($isViewMode)>
                        @error('title_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </x-admin.form.group>

                <x-admin.form.group label="Message *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <textarea class="{{ $errors->has('message') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="message" rows="3" placeholder="{{ __('Notification message body...') }}" @disabled($isViewMode)></textarea>
                        @error('message')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <textarea class="{{ $errors->has('message_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="message_ms" rows="3" placeholder="{{ __('Malay message') }}" @disabled($isViewMode)></textarea>
                        @error('message_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <textarea class="{{ $errors->has('message_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="message_zh" rows="3" placeholder="{{ __('Chinese message') }}" @disabled($isViewMode)></textarea>
                        @error('message_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </x-admin.form.group>

                <p class="mt-1 text-xs text-slate-500">{{ __('Hint: Use the tabs to input EN, Malay (MS), and Chinese (ZH). If MS/ZH is empty, the app will fall back to English.') }}</p>

                <x-admin.form.group label="Type">
                    <select class="pk-input" wire:model="notification_type" @disabled($isViewMode)>
                        <option value="announcement">{{ __('Announcement') }}</option>
                        <option value="reminder">{{ __('Reminder') }}</option>
                        <option value="promotional">{{ __('Promotional') }}</option>
                        <option value="transaction">{{ __('Transaction') }}</option>
                        <option value="system">{{ __('System') }}</option>
                    </select>
                </x-admin.form.group>

                <x-admin.form.group label="Schedule (optional)">
                    <input type="datetime-local" class="pk-input" wire:model="scheduled_at" @disabled($isViewMode)>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Leave blank to save as draft or send immediately') }}</p>
                </x-admin.form.group>

                @can('notifications.create')
                @if(! $isViewMode)
                <div class="grid grid-cols-2 gap-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="saveDraft" class="pk-btn-secondary inline-flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="saveDraft">{{ __('Save / schedule') }}</span>
                        <span wire:loading wire:target="saveDraft" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Saving…
                        </span>
                    </button>
                    <button type="button" wire:click="sendPush" wire:loading.attr="disabled" wire:target="sendPush" class="pk-btn-primary inline-flex items-center justify-center gap-2">
                        <span wire:loading.remove wire:target="sendPush">{{ __('Send now') }}</span>
                        <span wire:loading wire:target="sendPush" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                            Sending…
                        </span>
                    </button>
                </div>
                @endif
                @endcan
            </form>
        </div>
    </section>

    <section class="xl:col-span-2">
        {{-- Mobile card view --}}
        <div class="pk-mobile-only space-y-3">
            @foreach($notifications as $notification)
                <article class="pk-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">{{ $notification->title }}</p>
                            <p class="text-xs text-slate-500">{{ Str::limit($notification->message, 50) }}</p>
                        </div>
                        <x-admin.status-badge :status="$notification->status" />
                    </div>
                    <dl class="mt-3 space-y-1 text-xs text-slate-600">
                        <div class="flex justify-between gap-3"><dt>{{ __('Type') }}</dt><dd>{{ ucfirst(str_replace('_', ' ', $notification->notification_type)) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Scheduled') }}</dt><dd>{{ optional($notification->scheduled_at)->format('d M Y, h:i A') ?: '-' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Delivery') }}</dt><dd>{{ optional($notification->sent_at)->format('d M Y, h:i A') ?: '-' }}</dd></div>
                    </dl>
                    <div class="mt-3 flex gap-2">
                        <button type="button" class="pk-btn-secondary flex-1 text-xs" wire:click="viewNotification({{ $notification->id }})">{{ __('View') }}</button>
                        @can('notifications.delete')
                        <button
                            type="button"
                            class="flex-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                            wire:click="deleteNotification({{ $notification->id }})"
                            wire:confirm="Delete this push notification? This cannot be undone."
                        >{{ __('Delete') }}</button>
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Desktop table view --}}
        <div class="pk-desktop-only">
            <x-admin.table.wrapper>
                <table class="pk-table w-full">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('title')" class="inline-flex items-center gap-1">{{ __('Title') }} <span>{{ $this->sortIcon('title') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('notification_type')" class="inline-flex items-center gap-1">{{ __('Type') }} <span>{{ $this->sortIcon('notification_type') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">{{ __('Status') }} <span>{{ $this->sortIcon('status') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">{{ __('Scheduled') }}</th>
                            <th class="whitespace-nowrap">{{ __('Delivery') }}</th>
                            <th class="whitespace-nowrap text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($notifications as $notification)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div>
                                        <p class="font-semibold text-slate-700">{{ $notification->title }}</p>
                                        <p class="text-xs text-slate-500">{{ Str::limit($notification->message, 40) }}</p>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ ucfirst(str_replace('_', ' ', $notification->notification_type)) }}</td>
                                <td class="whitespace-nowrap"><x-admin.status-badge :status="$notification->status" /></td>
                                <td class="whitespace-nowrap">{{ optional($notification->scheduled_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                <td class="whitespace-nowrap">{{ optional($notification->sent_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                <td class="whitespace-nowrap text-right space-x-1">
                                    <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="viewNotification({{ $notification->id }})">{{ __('View') }}</button>
                                    @can('notifications.delete')
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                        wire:click="deleteNotification({{ $notification->id }})"
                                        wire:confirm="Delete this push notification? This cannot be undone."
                                    >{{ __('Delete') }}</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.table.wrapper>
        </div>
        <div class="mt-3">{{ $notifications->links() }}</div>
    </section>
</div>
</div>
