<?php

use App\Models\PlatformBenefit;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?int $benefitId = null;
    public string $title = '';
    public string $title_ms = '';
    public string $title_zh = '';
    public string $content = '';
    public string $content_ms = '';
    public string $content_zh = '';
    public string $audience = 'both';
    public bool $is_active = true;
    public string $successMessage = '';
    public string $toastType = 'success';

    public function with(): array
    {
        return [
            'benefits' => PlatformBenefit::query()
                ->orderByDesc('id')
                ->get(),
        ];
    }

    public function saveBenefit(): void
    {
        abort_unless(auth()->user()->can($this->benefitId ? 'platform-benefits.edit' : 'platform-benefits.create'), 403);

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'title_ms' => ['required', 'string', 'max:255'],
            'title_zh' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'content_ms' => ['required', 'string'],
            'content_zh' => ['required', 'string'],
            'audience' => ['required', 'in:both,member,merchant'],
            'is_active' => ['boolean'],
        ]);

        PlatformBenefit::updateOrCreate(
            ['id' => $this->benefitId],
            [
                'title' => $this->title,
                'title_ms' => $this->title_ms,
                'title_zh' => $this->title_zh,
                'content' => $this->content,
                'content_ms' => $this->content_ms,
                'content_zh' => $this->content_zh,
                'audience' => $this->audience,
                'is_active' => $this->is_active,
            ]
        );

        $this->showToast($this->benefitId ? 'Platform benefit updated.' : 'Platform benefit created.');
        $this->resetForm();
    }

    public function editBenefit(int $id): void
    {
        abort_unless(auth()->user()->can('platform-benefits.edit'), 403);

        $benefit = PlatformBenefit::findOrFail($id);
        $this->benefitId = $benefit->id;
        $this->title = $benefit->title;
        $this->title_ms = (string) ($benefit->title_ms ?? '');
        $this->title_zh = (string) ($benefit->title_zh ?? '');
        $this->content = $benefit->content;
        $this->content_ms = (string) ($benefit->content_ms ?? '');
        $this->content_zh = (string) ($benefit->content_zh ?? '');
        $this->audience = $benefit->audience;
        $this->is_active = (bool) $benefit->is_active;
        $this->showToast('Benefit loaded for editing.');
    }

    public function deleteBenefit(int $id): void
    {
        abort_unless(auth()->user()->can('platform-benefits.delete'), 403);
        PlatformBenefit::whereKey($id)->delete();
        $this->showToast('Platform benefit deleted.');
    }

    public function toggleActive(int $id): void
    {
        abort_unless(auth()->user()->can('platform-benefits.edit'), 403);
        $benefit = PlatformBenefit::findOrFail($id);
        $benefit->is_active = ! $benefit->is_active;
        $benefit->save();
        $this->showToast($benefit->is_active ? 'Benefit enabled.' : 'Benefit disabled.');
    }

    public function startCreate(): void
    {
        abort_unless(auth()->user()->can('platform-benefits.create'), 403);
        $this->resetForm();
        $this->showToast('Ready to create a new platform benefit.');
    }

    private function resetForm(): void
    {
        $this->benefitId = null;
        $this->title = '';
        $this->title_ms = '';
        $this->title_zh = '';
        $this->content = '';
        $this->content_ms = '';
        $this->content_zh = '';
        $this->audience = 'both';
        $this->is_active = true;
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = __($message);
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">{{ __('Platform Benefits') }}</h1>
        <p class="pk-subtitle">{{ __('Manage benefit messaging shown to both apps or specific audience segments.') }}</p>
    </div>
</x-slot>

<div class="space-y-6">
    <div
        x-data="{ message: $wire.entangle('successMessage'), type: $wire.entangle('toastType') }"
        x-cloak
        class="pointer-events-none z-[150]"
        style="position: fixed; right: 0.75rem; bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px)); width: calc(100vw - 1.5rem); max-width: 24rem;"
    >
        <div
            x-show="message"
            x-transition.opacity.duration.200ms
            class="pointer-events-auto rounded-xl border p-4 text-sm shadow-lg"
            :style="type === 'error'
                ? 'border-color:#fecdd3;background-color:#fff1f2;color:#9f1239;'
                : 'border-color:#bbf7d0;background-color:#f0fdf4;color:#166534;'"
            x-init="$watch('message', value => { if (value) { setTimeout(() => message = '', 2800) } })"
        >
            <div class="flex items-center justify-between gap-3">
                <p x-text="message"></p>
                <button
                    type="button"
                    class="text-xs font-semibold"
                    :style="type === 'error' ? 'color:#be123c;' : 'color:#15803d;'"
                    @click="message = ''"
                >{{ __('Dismiss') }}</button>
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="pk-card p-5 xl:col-span-1" x-data="{ activeLang: 'en' }">
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

            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-800">{{ __($benefitId ? 'Edit Benefit' : 'Create Benefit') }}</h2>
                @if($benefitId && auth()->user()->can('platform-benefits.create'))
                    <button type="button" class="pk-btn-primary shrink-0" wire:click="startCreate">{{ __('Create New') }}</button>
                @endif
            </div>

            <form wire:submit="saveBenefit" class="mt-4 space-y-3">
                <x-admin.form.group label="Title *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <input class="{{ $errors->has('title') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title" placeholder="{{ __('e.g. Easy QR Rewards') }}">
                        @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <input class="{{ $errors->has('title_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title_ms" placeholder="{{ __('Malay title') }}">
                        @error('title_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <input class="{{ $errors->has('title_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title_zh" placeholder="{{ __('Chinese title') }}">
                        @error('title_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </x-admin.form.group>

                <x-admin.form.group label="Audience *">
                    <select class="{{ $errors->has('audience') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="audience">
                        <option value="both">{{ __('Both') }}</option>
                        <option value="member">{{ __('Member Only') }}</option>
                        <option value="merchant">{{ __('Merchant Only') }}</option>
                    </select>
                    @error('audience')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>

                <x-admin.form.group label="Content *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <textarea class="{{ $errors->has('content') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="content" rows="6" placeholder="{{ __('Describe the benefit...') }}"></textarea>
                        @error('content')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <textarea class="{{ $errors->has('content_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="content_ms" rows="6" placeholder="{{ __('Malay content') }}"></textarea>
                        @error('content_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <textarea class="{{ $errors->has('content_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="content_zh" rows="6" placeholder="{{ __('Chinese content') }}"></textarea>
                        @error('content_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </x-admin.form.group>

                <p class="mt-0 -mb-2 text-xs text-slate-500">{{ __('Switch tabs to fill EN/MS/ZH. If MS/ZH is empty, the app will fall back to English.') }}</p>

                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="is_active" class="rounded border-slate-300">
                    Active
                </label>

                @if($benefitId)
                    @can('platform-benefits.edit')
                    <button class="pk-btn-primary w-full">{{ __('Update Benefit') }}</button>
                    @endcan
                @else
                    @can('platform-benefits.create')
                    <button class="pk-btn-primary w-full">{{ __('Save Benefit') }}</button>
                    @endcan
                @endif
            </form>
        </section>

        <section class="xl:col-span-2">
            <x-admin.table.wrapper>
                <table class="pk-table w-full">
                    <thead>
                        <tr>
                            <th>{{ __('Title') }}</th>
                            <th class="whitespace-nowrap">{{ __('Audience') }}</th>
                            <th class="whitespace-nowrap">{{ __('Status') }}</th>
                            <th class="whitespace-nowrap text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($benefits as $benefit)
                            <tr>
                                <td>
                                    <p class="font-semibold text-slate-700">{{ $benefit->title }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($benefit->content, 110) }}</p>
                                </td>
                                <td class="whitespace-nowrap">{{ \Illuminate\Support\Str::title($benefit->audience) }}</td>
                                <td class="whitespace-nowrap">
                                    @if($benefit->is_active)
                                        <span class="pk-badge-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="pk-badge-danger">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @can('platform-benefits.edit')
                                        <button class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="editBenefit({{ $benefit->id }})">{{ __('Edit') }}</button>
                                        <button class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="toggleActive({{ $benefit->id }})">{{ __($benefit->is_active ? 'Disable' : 'Enable') }}</button>
                                        @endcan
                                        @can('platform-benefits.delete')
                                        <button class="pk-btn-secondary px-3 py-1.5 text-xs text-rose-600" wire:click="deleteBenefit({{ $benefit->id }})">{{ __('Delete') }}</button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-sm text-slate-500">{{ __('No platform benefits yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </x-admin.table.wrapper>
        </section>
    </div>
</div>

