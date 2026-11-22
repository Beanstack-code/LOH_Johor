<?php

use App\Models\Merchant;
use App\Models\RedemptionItem;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $successMessage = '';
    public string $toastType = 'success';
    public string $statusFilter = '';
    public string $search = '';

    public ?int $itemId = null;
    public string $name = '';
    public string $name_ms = '';
    public string $name_zh = '';
    public string $description = '';
    public string $description_ms = '';
    public string $description_zh = '';
    public string $terms_and_conditions = '';
    public string $terms_and_conditions_ms = '';
    public string $terms_and_conditions_zh = '';
    public int $required_points = 0;
    public int $quantity_available = 0;
    public string $expiry_date = '';
    public string $status = 'active';

    // Image upload
    public ?string $existingImage = null;
    public $imageFile = null;

    public function with(): array
    {
        $allowed = ['name', 'required_points', 'quantity_available', 'expiry_date', 'status'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'items' => RedemptionItem::query()
                ->with('merchant:id,company_name')
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->orderBy($sortField, $sortDirection)
                ->paginate(10),
            'pendingReviewCount' => RedemptionItem::where('status', 'pending_review')->count(),
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

    public function sortBy(string $field): void
    {
        $allowed = ['name', 'required_points', 'quantity_available', 'expiry_date', 'status'];
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
            window.dispatchEvent(new CustomEvent('pk-item-toast', { detail: { message: " . json_encode($message) . ", type: " . json_encode($type) . " } }));
        ");
    }

    public function save(): void
    {
        $isNew = ! $this->itemId;
        abort_unless(auth()->user()->can($isNew ? 'redemption-items.create' : 'redemption-items.edit'), 403);

        $rules = [
            'name'               => ['required', 'string'],
            'name_ms'            => ['required', 'string', 'max:255'],
            'name_zh'            => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'description_ms'     => ['nullable', 'required_with:description', 'string'],
            'description_zh'     => ['nullable', 'required_with:description', 'string'],
            'required_points'    => ['required', 'integer', 'min:1'],
            'quantity_available' => ['required', 'integer', 'min:0'],
            'expiry_date'        => ['nullable', 'date', 'after_or_equal:today'],
        ];

        // Image is required for new items, optional for updates if one already exists
        if (!$this->itemId || (!$this->existingImage && !$this->imageFile)) {
            $rules['imageFile'] = ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];
        } else {
            $rules['imageFile'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];
        }

        $this->validate($rules);

        $payload = [
            'name'               => $this->name,
            'name_ms'            => $this->name_ms,
            'name_zh'            => $this->name_zh,
            'description'             => $this->description,
            'description_ms'          => $this->description_ms,
            'description_zh'          => $this->description_zh,
            'terms_and_conditions'    => $this->terms_and_conditions ?: null,
            'terms_and_conditions_ms' => $this->terms_and_conditions_ms ?: null,
            'terms_and_conditions_zh' => $this->terms_and_conditions_zh ?: null,
            'required_points'         => $this->required_points,
            'quantity_available' => $this->quantity_available,
            'expiry_date'        => $this->expiry_date ?: null,
            'status'             => $this->status,
        ];

        if ($this->imageFile) {
            if ($this->existingImage) {
                Storage::disk('public')->delete($this->existingImage);
            }
            $payload['image_path'] = $this->imageFile->store('redemption-items', 'public');
        }

        $isNew = ! $this->itemId;
        $item = RedemptionItem::updateOrCreate(['id' => $this->itemId], $payload);
        $this->showToast($isNew ? 'Redemption item created successfully.' : 'Redemption item updated successfully.');

        if ($isNew) {
            $this->resetForm();
        } else {
            $this->existingImage = $item->image_path;
            $this->imageFile = null;
        }
    }

    public function edit(int $id): void
    {
        $item = RedemptionItem::findOrFail($id);
        $this->itemId = $item->id;
        $this->name = $item->name;
        $this->name_ms = (string) ($item->name_ms ?? '');
        $this->name_zh = (string) ($item->name_zh ?? '');
        $this->description = (string) $item->description;
        $this->description_ms = (string) ($item->description_ms ?? '');
        $this->description_zh = (string) ($item->description_zh ?? '');
        $this->terms_and_conditions = (string) ($item->terms_and_conditions ?? '');
        $this->terms_and_conditions_ms = (string) ($item->terms_and_conditions_ms ?? '');
        $this->terms_and_conditions_zh = (string) ($item->terms_and_conditions_zh ?? '');
        $this->required_points = (int) $item->required_points;
        $this->quantity_available = (int) $item->quantity_available;
        $this->expiry_date = optional($item->expiry_date)->format('Y-m-d') ?? '';
        $this->status = $item->status;
        $this->existingImage = $item->image_path;
        $this->imageFile = null;
        $this->showToast('Item details loaded.');
        $this->js("
            window.dispatchEvent(new CustomEvent('item-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()->can('redemption-items.activate'), 403);

        $item = RedemptionItem::findOrFail($id);
        $item->status = $item->status === 'active' ? 'inactive' : 'active';
        $item->save();
        $this->showToast($item->status === 'active' ? 'Item activated.' : 'Item deactivated.');
    }

    public function approveItem(int $id): void
    {
        abort_unless(auth()->user()->can('redemption-items.activate'), 403);

        $item = RedemptionItem::findOrFail($id);
        if ($item->status !== 'pending_review') return;

        $item->update([
            'status'      => 'active',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->showToast('Item approved and now live.');
    }

    public function rejectItem(int $id): void
    {
        abort_unless(auth()->user()->can('redemption-items.activate'), 403);

        $item = RedemptionItem::findOrFail($id);
        if ($item->status !== 'pending_review') return;

        $item->update([
            'status'      => 'inactive',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->showToast('Item rejected (set to inactive).', 'error');
    }

    public function deleteItem(int $id): void
    {
        abort_unless(auth()->user()->can('redemption-items.delete'), 403);

        $item = RedemptionItem::findOrFail($id);

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        // Reset edit form if the item being deleted is currently loaded
        if ($this->itemId === $id) {
            $this->resetForm();
        }

        $item->delete();
        $this->showToast('Redemption item deleted.', 'error');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showToast('Ready to create a new item.');
        $this->js("window.dispatchEvent(new CustomEvent('item-form-open'));");
    }

    private function resetForm(): void
    {
        $this->reset(['itemId', 'name', 'name_ms', 'name_zh', 'description', 'description_ms', 'description_zh', 'terms_and_conditions', 'terms_and_conditions_ms', 'terms_and_conditions_zh', 'required_points', 'quantity_available', 'expiry_date', 'existingImage', 'imageFile']);
        $this->status = 'active';
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="pk-page-title">Redemption Item Management</h1>
            <p class="pk-subtitle">Create/edit catalog items, monitor stock, and enable/disable rewards anytime.</p>
        </div>
        @if($pendingReviewCount > 0)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">
                <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                {{ $pendingReviewCount }} Pending Review
            </span>
        @endif
    </div>
</x-slot>

<div class="space-y-6">
    {{-- Search and Filter bar --}}
    <section class="pk-filter-bar">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by item name..." class="pk-input w-full sm:max-w-sm">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Status</option>
            <option value="pending_review">Pending Review</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </section>

<div class="grid gap-6 xl:grid-cols-3">
    {{-- Inline toast --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        @pk-item-toast.window="message = $event.detail.message; type = $event.detail.type; show = true; setTimeout(() => show = false, 3500)"
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
        @item-form-open.window="open = true"
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
            <h2 class="text-base font-semibold text-slate-800">{{ $itemId ? 'Edit Item' : 'Create Item' }}</h2>
            <div class="flex items-center gap-2">
                @if($itemId)
                    @can('redemption-items.create')
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
                    <x-admin.form.group label="Item Name *">
                        <input class="{{ $errors->has('name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="name">
                        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'ms'" x-cloak>
                    <x-admin.form.group label="Item Name (Malay)">
                        <input class="pk-input" wire:model="name_ms">
                        @error('name_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'zh'" x-cloak>
                    <x-admin.form.group label="Item Name (Chinese)">
                        <input class="pk-input" wire:model="name_zh">
                        @error('name_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>

                <div x-show="activeLang === 'en'" x-cloak>
                    <x-admin.form.group label="Description">
                        <textarea class="pk-input" wire:model="description"></textarea>
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'ms'" x-cloak>
                    <x-admin.form.group label="Description (Malay)">
                        <textarea class="pk-input" wire:model="description_ms"></textarea>
                        @error('description_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'zh'" x-cloak>
                    <x-admin.form.group label="Description (Chinese)">
                        <textarea class="pk-input" wire:model="description_zh"></textarea>
                        @error('description_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                </div>

                <div x-show="activeLang === 'en'" x-cloak>
                    <x-admin.form.group label="Terms & Conditions (optional)">
                        <textarea class="pk-input min-h-[80px]" wire:model="terms_and_conditions" placeholder="e.g. Valid at selected outlets only. Not exchangeable for cash."></textarea>
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'ms'" x-cloak>
                    <x-admin.form.group label="Terms & Conditions (Malay)">
                        <textarea class="pk-input min-h-[80px]" wire:model="terms_and_conditions_ms"></textarea>
                    </x-admin.form.group>
                </div>
                <div x-show="activeLang === 'zh'" x-cloak>
                    <x-admin.form.group label="Terms & Conditions (Chinese)">
                        <textarea class="pk-input min-h-[80px]" wire:model="terms_and_conditions_zh"></textarea>
                    </x-admin.form.group>
                </div>

                <x-admin.form.group label="Required Points *">
                    <input type="number" class="{{ $errors->has('required_points') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="required_points">
                    @error('required_points')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>

                <x-admin.form.group label="Quantity Available *">
                    <input type="number" class="{{ $errors->has('quantity_available') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="quantity_available">
                    @error('quantity_available')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>

                <x-admin.form.group label="Expiry Date">
                    <input type="date" class="{{ $errors->has('expiry_date') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="expiry_date" min="{{ date('Y-m-d') }}">
                    @error('expiry_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>

                <x-admin.form.group label="Status">
                    <select class="pk-input" wire:model="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </x-admin.form.group>

                {{-- Image upload --}}
                <x-admin.form.group label="Item Image *">
                    @if($existingImage)
                        <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                            <img src="{{ Storage::url($existingImage) }}" alt="Current image" class="h-32 w-full object-cover">
                            <p class="px-2 py-1 text-xs text-slate-500">Current image</p>
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
                                $refs.imageInput.files = dt.files;
                                $refs.imageInput.dispatchEvent(new Event('change'));
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
                            @click="$refs.imageInput.click()"
                            :class="isDragging ? 'border-[#ed1c24] bg-rose-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                            class="cursor-pointer rounded-xl border-2 border-dashed p-5 text-center transition"
                        >
                            <svg class="mx-auto mb-2 h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <p class="text-xs font-medium text-slate-600">Drag & drop or <span class="text-[#ed1c24]">click to browse</span></p>
                            <p class="mt-0.5 text-xs text-slate-400">JPG, PNG, GIF, WEBP — max 10 MB</p>
                            <input x-ref="imageInput" type="file" wire:model="imageFile" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleChange($event)">
                        </div>
                        @error('imageFile')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <div x-show="preview !== null" x-cloak class="mt-2 overflow-hidden rounded-xl border border-slate-200">
                            <img :src="preview" class="h-32 w-full object-cover">
                            <p class="px-2 py-1 text-xs text-slate-500">New image preview</p>
                        </div>
                    </div>
                </x-admin.form.group>

                @if($itemId)
                    @can('redemption-items.edit')
                    <button class="pk-btn-primary w-full">Update Item</button>
                    @endcan
                @else
                    @can('redemption-items.create')
                    <button class="pk-btn-primary w-full">Create Item</button>
                    @endcan
                @endif
            </form>
        </div>
    </section>

    <section class="xl:col-span-2">
        {{-- Mobile card view --}}
        <div class="pk-mobile-only space-y-3">
            @foreach($items as $item)
                <article class="pk-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            @if($item->image_path)
                                <img src="{{ Storage::url($item->image_path) }}" class="h-10 w-10 rounded-xl object-cover border border-slate-200 shrink-0">
                            @endif
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $item->name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->description }}</p>
                            </div>
                        </div>
                        <x-admin.status-badge :status="$item->status" />
                    </div>
                    <dl class="mt-3 space-y-1 text-xs text-slate-600">
                        <div class="flex justify-between gap-3"><dt>Points</dt><dd>{{ number_format($item->required_points) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Quantity</dt><dd>{{ number_format($item->quantity_available) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Expiry</dt><dd>{{ optional($item->expiry_date)->format('d M Y') ?: '-' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>Creator</dt><dd>{{ $item->merchant?->company_name ?? 'Admin' }}</dd></div>
                    </dl>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @if($item->status === 'pending_review')
                            @can('redemption-items.activate')
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-emerald-700 col-span-1" wire:click="approveItem({{ $item->id }})" wire:confirm="Approve this item?">Approve</button>
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-rose-600 col-span-1" wire:click="rejectItem({{ $item->id }})" wire:confirm="Reject this item?">Reject</button>
                            @endcan
                        @else
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="edit({{ $item->id }})">View</button>
                            @can('redemption-items.edit')
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="edit({{ $item->id }})">Edit</button>
                            @endcan
                            @can('redemption-items.activate')
                            <button class="pk-btn-secondary px-2 py-2 text-xs {{ $item->status === 'active' ? 'text-rose-600' : 'text-emerald-600' }}" wire:click="toggleStatus({{ $item->id }})">
                                {{ $item->status === 'active' ? 'Deactivate' : 'Activate' }}
                            </button>
                            @endcan
                        @endif
                        @can('redemption-items.delete')
                        <button class="pk-btn-secondary px-2 py-2 text-xs text-rose-600 col-span-2" wire:click="deleteItem({{ $item->id }})" wire:confirm="Permanently delete this item? This cannot be undone.">Delete</button>
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
                                <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1">Item <span>{{ $this->sortIcon('name') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('required_points')" class="inline-flex items-center gap-1">Points <span>{{ $this->sortIcon('required_points') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('quantity_available')" class="inline-flex items-center gap-1">Quantity <span>{{ $this->sortIcon('quantity_available') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('expiry_date')" class="inline-flex items-center gap-1">Expiry <span>{{ $this->sortIcon('expiry_date') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">Creator</th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">Status <span>{{ $this->sortIcon('status') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr class="{{ $item->status === 'pending_review' ? 'bg-amber-50/50' : '' }}">
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($item->image_path)
                                            <img src="{{ Storage::url($item->image_path) }}" class="h-9 w-9 rounded-xl object-cover border border-slate-200 shrink-0">
                                        @endif
                                        <div>
                                            <p class="font-semibold text-slate-700">{{ $item->name }}</p>
                                            <p class="text-xs text-slate-500">{{ $item->description }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">{{ number_format($item->required_points) }}</td>
                                <td class="whitespace-nowrap">{{ number_format($item->quantity_available) }}</td>
                                <td class="whitespace-nowrap">{{ optional($item->expiry_date)->format('d M Y') ?: '-' }}</td>
                                <td class="whitespace-nowrap text-xs text-slate-600">
                                    @if($item->merchant)
                                        <span class="font-medium text-indigo-700">{{ $item->merchant->company_name }}</span>
                                    @else
                                        <span class="text-slate-400">Admin</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap"><x-admin.status-badge :status="$item->status" /></td>
                                <td class="whitespace-nowrap text-right space-x-1">
                                    @if($item->status === 'pending_review')
                                        @can('redemption-items.activate')
                                        <button class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition"
                                            wire:click="approveItem({{ $item->id }})"
                                            wire:confirm="Approve this item and make it live?">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            Approve
                                        </button>
                                        <button class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                            wire:click="rejectItem({{ $item->id }})"
                                            wire:confirm="Reject this item? It will be set to inactive.">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Reject
                                        </button>
                                        @endcan
                                    @else
                                        <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="edit({{ $item->id }})">View</button>
                                        @can('redemption-items.edit')
                                        <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="edit({{ $item->id }})">Edit</button>
                                        @endcan
                                        @can('redemption-items.activate')
                                        <button class="pk-btn-secondary px-3 py-2 text-xs {{ $item->status === 'active' ? 'text-rose-600' : 'text-emerald-600' }}" wire:click="toggleStatus({{ $item->id }})">
                                            {{ $item->status === 'active' ? 'Deactivate' : 'Activate' }}
                                        </button>
                                        @endcan
                                    @endif
                                    @can('redemption-items.delete')
                                    <button class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition"
                                        wire:click="deleteItem({{ $item->id }})"
                                        wire:confirm="Permanently delete this item? This cannot be undone.">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        Delete
                                    </button>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.table.wrapper>
        </div>
        <div class="mt-3">{{ $items->links() }}</div>
    </section>
</div>
</div>
