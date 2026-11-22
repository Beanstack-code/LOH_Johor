<?php

use App\Models\Announcement;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';
    public string $search = '';
    public string $statusFilter = '';
    public string $highlightFilter = '';
    public string $successMessage = '';
    public string $toastType = 'success';

    public ?int $announcementId = null;
    public string $title = '';
    public string $title_ms = '';
    public string $title_zh = '';
    public string $category = '';
    public string $content = '';
    public string $content_ms = '';
    public string $content_zh = '';
    public string $terms_and_conditions = '';
    public string $terms_and_conditions_ms = '';
    public string $terms_and_conditions_zh = '';
    public string $starts_at = '';
    public string $ends_at = '';
    public bool $is_highlighted = false;
    public string $status = 'draft';

    // Image upload
    public ?string $existingCoverImage = null;
    public $coverImageFile = null;

    public function with(): array
    {
        $allowed = ['title', 'starts_at', 'ends_at', 'status', 'is_highlighted', 'created_at'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'created_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'announcements' => Announcement::query()
                ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->highlightFilter !== '', fn ($q) => $q->where('is_highlighted', $this->highlightFilter === 'yes'))
                ->orderBy($sortField, $sortDirection)
                ->paginate(10),
        ];
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedHighlightFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['title', 'starts_at', 'ends_at', 'status', 'is_highlighted', 'created_at'];
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
        $this->successMessage = $message;
        $this->toastType = $type;
        $this->js("
            window.dispatchEvent(new CustomEvent('pk-announcement-toast', { detail: { message: " . json_encode($message) . ", type: " . json_encode($type) . " } }));
        ");
    }

    public function save(): void
    {
        $isNew = ! $this->announcementId;
        abort_unless(auth()->user()->can($isNew ? 'announcements.create' : 'announcements.edit'), 403);

        $rules = [
            'title'          => ['required', 'string'],
            'content'        => ['required', 'string'],
            'title_ms'       => ['required', 'string', 'max:255'],
            'title_zh'       => ['required', 'string', 'max:255'],
            'content_ms'     => ['required', 'string'],
            'content_zh'     => ['required', 'string'],
            'terms_and_conditions' => ['required', 'string'],
            'terms_and_conditions_ms' => ['required', 'string'],
            'terms_and_conditions_zh' => ['required', 'string'],
        ];

        // Cover image is required for new announcements, optional for updates if one already exists
        if (!$this->announcementId || (!$this->existingCoverImage && !$this->coverImageFile)) {
            $rules['coverImageFile'] = ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];
        } else {
            $rules['coverImageFile'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];
        }

        $this->validate($rules);

        $payload = [
            'title'                 => $this->title,
            'title_ms'              => $this->title_ms,
            'title_zh'              => $this->title_zh,
            'category'              => $this->category,
            'content'               => $this->content,
            'content_ms'            => $this->content_ms,
            'content_zh'            => $this->content_zh,
            'terms_and_conditions'  => $this->terms_and_conditions,
            'terms_and_conditions_ms' => $this->terms_and_conditions_ms,
            'terms_and_conditions_zh' => $this->terms_and_conditions_zh,
            'starts_at'             => $this->starts_at ?: null,
            'ends_at'               => $this->ends_at ?: null,
            'is_highlighted'        => $this->is_highlighted,
            'status'                => $this->status,
        ];

        if ($this->coverImageFile) {
            if ($this->existingCoverImage) {
                Storage::disk('public')->delete($this->existingCoverImage);
            }
            $payload['cover_image'] = $this->coverImageFile->store('announcements', 'public');
        }

        $isNew = ! $this->announcementId;
        $announcement = Announcement::updateOrCreate(['id' => $this->announcementId], $payload);
        $this->showToast($isNew ? 'Announcement created successfully.' : 'Announcement updated successfully.');

        if ($isNew) {
            $this->resetForm();
        } else {
            $this->existingCoverImage = $announcement->cover_image;
            $this->coverImageFile = null;
        }
    }

    public function edit(int $id): void
    {
        $item = Announcement::findOrFail($id);
        $this->announcementId = $item->id;
        $this->title = $item->title;
        $this->title_ms = (string) ($item->title_ms ?? '');
        $this->title_zh = (string) ($item->title_zh ?? '');
        $this->category = (string) $item->category;
        $this->content = $item->content;
        $this->content_ms = (string) ($item->content_ms ?? '');
        $this->content_zh = (string) ($item->content_zh ?? '');
        $this->terms_and_conditions = (string) $item->terms_and_conditions;
        $this->terms_and_conditions_ms = (string) ($item->terms_and_conditions_ms ?? '');
        $this->terms_and_conditions_zh = (string) ($item->terms_and_conditions_zh ?? '');
        $this->existingCoverImage = $item->cover_image;
        $this->coverImageFile = null;
        $this->starts_at = optional($item->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = optional($item->ends_at)->format('Y-m-d\TH:i') ?? '';
        $this->is_highlighted = (bool) $item->is_highlighted;
        $this->status = $item->status;
        $this->showToast('Announcement details loaded.');
        $this->js("
            window.dispatchEvent(new CustomEvent('announcement-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function quickDraft(int $id): void
    {
        abort_unless(auth()->user()->can('announcements.edit'), 403);

        Announcement::whereKey($id)->update(['status' => 'draft']);
        $this->showToast('Announcement moved to draft.');
    }

    public function quickPublish(int $id): void
    {
        abort_unless(auth()->user()->can('announcements.edit'), 403);

        Announcement::whereKey($id)->update(['status' => 'published']);
        $this->showToast('Announcement published.');
    }

    public function deleteAnnouncement(int $id): void
    {
        abort_unless(auth()->user()->can('announcements.delete'), 403);

        $announcement = Announcement::query()->findOrFail($id);

        if ($this->announcementId === $id) {
            $this->resetForm();
        }

        if ($announcement->cover_image) {
            Storage::disk('public')->delete($announcement->cover_image);
        }

        $announcement->delete();
        $this->resetPage();
        $this->showToast('Announcement deleted.', 'error');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showToast('Ready to create a new announcement.');
        $this->js("window.dispatchEvent(new CustomEvent('announcement-form-open'));");
    }

    private function resetForm(): void
    {
        $this->reset(['announcementId', 'title', 'category', 'content', 'terms_and_conditions', 'starts_at', 'ends_at', 'is_highlighted', 'existingCoverImage', 'coverImageFile']);
        $this->reset(['title_ms', 'title_zh', 'content_ms', 'content_zh', 'terms_and_conditions_ms', 'terms_and_conditions_zh']);
        $this->status = 'draft';
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">Announcements Management</h1>
        <p class="pk-subtitle">Publish banners and platform-wide updates with start/end windows.</p>
    </div>
</x-slot>

<div class="space-y-6">
    {{-- Inline toast --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        @pk-announcement-toast.window="message = $event.detail.message; type = $event.detail.type; show = true; setTimeout(() => show = false, 3500)"
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
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by title..." class="pk-input w-full sm:max-w-sm">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
        </select>
        <select wire:model.live="highlightFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Highlights</option>
            <option value="yes">Highlighted</option>
            <option value="no">Not Highlighted</option>
        </select>
    </section>

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
        @announcement-form-open.window="open = true"
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
            <h2 class="text-base font-semibold text-slate-800">{{ $announcementId ? 'Edit Announcement' : 'Publish Announcement' }}</h2>
            <div class="flex items-center gap-2">
                @if($announcementId)
                    @can('announcements.create')
                    <button type="button" class="pk-btn-primary shrink-0" wire:click="startCreate">
                        Create New
                    </button>
                    @endcan
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
        </div>

        <div x-show="open" x-collapse>
            <form wire:submit="save" class="mt-4 space-y-3">

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

                <div x-show="activeLang === 'en'" x-cloak>
                    <x-admin.form.group label="Title *">
                        <input class="{{ $errors->has('title') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="title">
                        @error('title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'ms'" x-cloak>
                    <x-admin.form.group label="Title (Malay)">
                        <input class="pk-input" wire:model="title_ms">
                        @error('title_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'zh'" x-cloak>
                    <x-admin.form.group label="Title (Chinese)">
                        <input class="pk-input" wire:model="title_zh">
                        @error('title_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>

                <x-admin.form.group label="Category">
                    <select class="pk-input" wire:model="category">
                        <option value="">-- Select Category --</option>
                        <option value="general">General</option>
                        <option value="promotion">Promotion</option>
                        <option value="event">Event</option>
                        <option value="update">System Update</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </x-admin.form.group>

                <div x-show="activeLang === 'en'" x-cloak>
                    <x-admin.form.group label="Content *">
                        <textarea class="{{ $errors->has('content') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="content" rows="8" placeholder="Enter announcement content. Use line breaks for paragraphs."></textarea>
                        <p class="mt-1 text-xs text-slate-500">Use Enter key to create new paragraphs. Content will preserve line breaks.</p>
                        @error('content')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'ms'" x-cloak>
                    <x-admin.form.group label="Content (Malay)">
                        <textarea class="pk-input" wire:model="content_ms" rows="6"></textarea>
                        @error('content_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'zh'" x-cloak>
                    <x-admin.form.group label="Content (Chinese)">
                        <textarea class="pk-input" wire:model="content_zh" rows="6"></textarea>
                        @error('content_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>

                <div x-show="activeLang === 'en'" x-cloak>
                    <x-admin.form.group label="Terms and Conditions">
                        <textarea class="pk-input" wire:model="terms_and_conditions" rows="6" placeholder="Enter terms and conditions"></textarea>
                        <p class="mt-1 text-xs text-slate-500">Add any terms, conditions, or disclaimers for this announcement.</p>
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'ms'" x-cloak>
                    <x-admin.form.group label="Terms & Conditions (Malay)">
                        <textarea class="pk-input" wire:model="terms_and_conditions_ms" rows="5"></textarea>
                        @error('terms_and_conditions_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'zh'" x-cloak>
                    <x-admin.form.group label="Terms & Conditions (Chinese)">
                        <textarea class="pk-input" wire:model="terms_and_conditions_zh" rows="5"></textarea>
                        @error('terms_and_conditions_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>

                {{-- Cover Image upload --}}
                <x-admin.form.group label="Cover Image *">
                    @if($existingCoverImage)
                        <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                            <img src="{{ Storage::url($existingCoverImage) }}" alt="Current cover" class="h-32 w-full object-cover">
                            <p class="px-2 py-1 text-xs text-slate-500">Current cover image</p>
                        </div>
                    @endif
                    <div
                        x-data="{
                            isDragging: false,
                            preview: null,
                            handleDrop(e) {
                                this.isDragging = false;
                                const file = e.dataTransfer.files[0];
                                if (!file) return;
                                const dt = new DataTransfer();
                                dt.items.add(file);
                                $refs.coverInput.files = dt.files;
                                $refs.coverInput.dispatchEvent(new Event('change'));
                                this.showPreview(file);
                            },
                            handleChange(e) {
                                if (e.target.files[0]) this.showPreview(e.target.files[0]);
                            },
                            showPreview(file) {
                                const reader = new FileReader();
                                reader.onload = (ev) => this.preview = ev.target.result;
                                reader.readAsDataURL(file);
                            }
                        }"
                    >
                        <div
                            @dragover.prevent="isDragging = true"
                            @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop($event)"
                            @click="$refs.coverInput.click()"
                            :class="isDragging ? 'border-[#ed1c24] bg-rose-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                            class="cursor-pointer rounded-xl border-2 border-dashed p-5 text-center transition"
                        >
                            <svg class="mx-auto mb-2 h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <p class="text-xs font-medium text-slate-600">Drag & drop or <span class="text-[#ed1c24]">click to browse</span></p>
                            <p class="mt-0.5 text-xs text-slate-400">JPG, PNG, GIF, WEBP — max 10 MB</p>
                            <input x-ref="coverInput" type="file" wire:model="coverImageFile" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleChange($event)">
                        </div>
                        @error('coverImageFile')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <div x-show="preview !== null" x-cloak class="mt-2 overflow-hidden rounded-xl border border-slate-200">
                            <img :src="preview" class="h-32 w-full object-cover">
                            <p class="px-2 py-1 text-xs text-slate-500">New cover preview</p>
                        </div>
                    </div>
                </x-admin.form.group>

                <x-admin.form.group label="Start Date">
                    <input type="datetime-local" class="pk-input" wire:model="starts_at">
                </x-admin.form.group>

                <x-admin.form.group label="End Date">
                    <input type="datetime-local" class="pk-input" wire:model="ends_at">
                </x-admin.form.group>

                <x-admin.form.group label="Status">
                    <select class="pk-input" wire:model="status">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </x-admin.form.group>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="is_highlighted" class="rounded">
                    Highlight on homepage
                </label>

                @if($announcementId)
                    @can('announcements.edit')
                    <button class="pk-btn-primary w-full">Update Announcement</button>
                    @endcan
                @else
                    @can('announcements.create')
                    <button class="pk-btn-primary w-full">Save Announcement</button>
                    @endcan
                @endif
            </form>
        </div>
    </section>

    <section class="xl:col-span-2">
        {{-- Mobile card view --}}
        <div class="pk-mobile-only space-y-3">
            @foreach($announcements as $announcement)
                <article class="pk-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            @if($announcement->cover_image)
                                <img src="{{ Storage::url($announcement->cover_image) }}" class="h-10 w-10 rounded-xl object-cover border border-slate-200 shrink-0">
                            @endif
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $announcement->title }}</p>
                                @if($announcement->category)
                                    <span class="text-xs text-slate-500 font-medium">{{ ucfirst($announcement->category) }}</span>
                                @endif
                                @if($announcement->is_highlighted)
                                    <span class="text-xs text-amber-600 font-medium ml-2">★ Highlighted</span>
                                @endif
                            </div>
                        </div>
                        <x-admin.status-badge :status="$announcement->status" />
                    </div>
                    <dl class="mt-3 space-y-1 text-xs text-slate-600">
                        <div class="flex justify-between gap-3"><dt>Start</dt><dd>{{ optional($announcement->starts_at)->format('d M Y, h:i A') ?: '-' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>End</dt><dd>{{ optional($announcement->ends_at)->format('d M Y, h:i A') ?: '-' }}</dd></div>
                    </dl>
                    @if($announcement->terms_and_conditions)
                        <div class="mt-2 text-xs text-slate-500">
                            <span class="font-medium">T&C:</span> {{ Str::limit($announcement->terms_and_conditions, 50) }}
                        </div>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button class="pk-btn-secondary flex-1 min-w-[4rem] px-2 py-2 text-xs" wire:click="edit({{ $announcement->id }})">View</button>
                        @can('announcements.edit')
                        <button class="pk-btn-secondary flex-1 min-w-[4rem] px-2 py-2 text-xs" wire:click="edit({{ $announcement->id }})">Edit</button>
                        @if($announcement->status === 'published')
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-amber-600" wire:click="quickDraft({{ $announcement->id }})">Draft</button>
                        @else
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-emerald-600" wire:click="quickPublish({{ $announcement->id }})">Publish</button>
                        @endif
                        @endcan
                        @can('announcements.delete')
                        <button
                            type="button"
                            class="w-full rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 sm:w-auto sm:flex-1"
                            wire:click="deleteAnnouncement({{ $announcement->id }})"
                            wire:confirm="Delete this announcement and its cover image? This cannot be undone."
                        >Delete</button>
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
                                <button type="button" wire:click="sortBy('title')" class="inline-flex items-center gap-1">Title <span>{{ $this->sortIcon('title') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">Category</th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('starts_at')" class="inline-flex items-center gap-1">Start <span>{{ $this->sortIcon('starts_at') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('ends_at')" class="inline-flex items-center gap-1">End <span>{{ $this->sortIcon('ends_at') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('is_highlighted')" class="inline-flex items-center gap-1">Highlight <span>{{ $this->sortIcon('is_highlighted') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">Status <span>{{ $this->sortIcon('status') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($announcements as $announcement)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($announcement->cover_image)
                                            <img src="{{ Storage::url($announcement->cover_image) }}" class="h-9 w-9 rounded-xl object-cover border border-slate-200 shrink-0">
                                        @endif
                                        <div>
                                            <p class="font-semibold text-slate-700">{{ $announcement->title }}</p>
                                            @if($announcement->terms_and_conditions)
                                                <p class="text-xs text-slate-400">Has T&C</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">
                                    @if($announcement->category)
                                        <span class="text-xs font-medium text-slate-600">{{ ucfirst($announcement->category) }}</span>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">{{ optional($announcement->starts_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                <td class="whitespace-nowrap">{{ optional($announcement->ends_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                <td class="whitespace-nowrap">
                                    @if($announcement->is_highlighted)
                                        <span class="text-xs text-amber-600 font-semibold">★ Yes</span>
                                    @else
                                        <span class="text-xs text-slate-400">No</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap"><x-admin.status-badge :status="$announcement->status" /></td>
                                <td class="whitespace-nowrap text-right space-x-1">
                                    <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="edit({{ $announcement->id }})">View</button>
                                    @can('announcements.edit')
                                    <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="edit({{ $announcement->id }})">Edit</button>
                                    @if($announcement->status === 'published')
                                        <button class="pk-btn-secondary px-3 py-2 text-xs text-amber-600" wire:click="quickDraft({{ $announcement->id }})">Draft</button>
                                    @else
                                        <button class="pk-btn-secondary px-3 py-2 text-xs text-emerald-600" wire:click="quickPublish({{ $announcement->id }})">Publish</button>
                                    @endif
                                    @endcan
                                    @can('announcements.delete')
                                    <button
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100"
                                        wire:click="deleteAnnouncement({{ $announcement->id }})"
                                        wire:confirm="Delete this announcement and its cover image? This cannot be undone."
                                    >Delete</button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.table.wrapper>
        </div>
        <div class="mt-3">{{ $announcements->links() }}</div>
    </section>
</div>
</div>
