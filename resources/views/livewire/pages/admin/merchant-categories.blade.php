<?php

use App\Models\MerchantCategory;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';
    public string $successMessage = '';
    public string $toastType = 'success';

    public ?int $categoryId = null;
    public string $name = '';
    public string $name_ms = '';
    public string $name_zh = '';
    public bool $is_active = true;

    public function with(): array
    {
        $allowed = ['name', 'is_active', 'created_at'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'categories' => MerchantCategory::query()
                ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('is_active', $this->statusFilter === 'active'))
                ->orderBy($sortField, $sortDirection)
                ->paginate(15),
        ];
    }

    public function sortBy(string $field): void
    {
        $allowed = ['name', 'is_active', 'created_at'];
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

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function save(): void
    {
        $isNew = ! $this->categoryId;
        abort_unless(auth()->user()->can($isNew ? 'merchant-categories.create' : 'merchant-categories.edit'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:100', $this->categoryId
                ? \Illuminate\Validation\Rule::unique('merchant_categories', 'name')->ignore($this->categoryId)
                : \Illuminate\Validation\Rule::unique('merchant_categories', 'name'),
            ],
            'name_ms' => ['required', 'string', 'max:100'],
            'name_zh' => ['required', 'string', 'max:100'],
        ]);

        MerchantCategory::updateOrCreate(
            ['id' => $this->categoryId],
            [
                'name' => trim($this->name),
                'name_ms' => trim($this->name_ms),
                'name_zh' => trim($this->name_zh),
                'is_active' => $this->is_active,
            ]
        );

        $this->showToast($isNew ? 'Category created successfully.' : 'Category updated successfully.');
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $cat = MerchantCategory::findOrFail($id);
        $this->categoryId = $cat->id;
        $this->name = $cat->name;
        $this->name_ms = (string) ($cat->name_ms ?? '');
        $this->name_zh = (string) ($cat->name_zh ?? '');
        $this->is_active = $cat->is_active;
        $this->showToast('Category loaded.');
        $this->js("
            window.dispatchEvent(new CustomEvent('cat-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function toggleStatus(int $id): void
    {
        abort_unless(auth()->user()->can('merchant-categories.activate'), 403);

        $cat = MerchantCategory::findOrFail($id);
        $cat->is_active = ! $cat->is_active;
        $cat->save();
        $this->showToast($cat->is_active ? 'Category activated.' : 'Category deactivated.');
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('merchant-categories.delete'), 403);

        MerchantCategory::findOrFail($id)->delete();
        $this->showToast('Category deleted.');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showToast('Ready to create a new category.');
        $this->js("
            window.dispatchEvent(new CustomEvent('cat-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    private function resetForm(): void
    {
        $this->reset(['categoryId', 'name', 'name_ms', 'name_zh']);
        $this->is_active = true;
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = $message;
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">Merchant Categories</h1>
        <p class="pk-subtitle">Manage the category list that merchants can be assigned to.</p>
    </div>
</x-slot>

<div class="space-y-6">
    {{-- Inline toast --}}
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
                >Dismiss</button>
            </div>
        </div>
    </div>

    {{-- Filter bar --}}
    <section class="pk-filter-bar">
        <input wire:model.live="search" class="pk-input w-full sm:max-w-sm" placeholder="Search category">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        {{-- Form card (collapsible on mobile) --}}
        <div
            class="pk-card w-full self-start p-5 xl:col-span-1"
            x-data="{ open: window.matchMedia('(min-width: 768px)').matches, activeLang: 'en' }"
            x-init="
                const mq = window.matchMedia('(min-width: 768px)');
                const sync = () => { if (mq.matches) open = true; };
                if (mq.addEventListener) mq.addEventListener('change', sync);
                else mq.addListener(sync);
            "
            @cat-form-open.window="open = true"
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
                <h2 class="text-base font-semibold text-slate-800">{{ $categoryId ? 'Edit Category' : 'Create Category' }}</h2>
                <div class="flex items-center gap-2">
                    @if($categoryId)
                        @can('merchant-categories.create')
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

                <form wire:submit="save" class="mt-4 space-y-4">
                    <x-admin.form.group label="Category Name *">
                        <div x-show="activeLang === 'en'" x-cloak>
                            <input
                                type="text"
                                wire:model="name"
                                class="{{ $errors->has('name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}"
                                placeholder="e.g. Food & Beverage"
                            >
                            @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="activeLang === 'ms'" x-cloak>
                            <input
                                type="text"
                                wire:model="name_ms"
                                class="{{ $errors->has('name_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}"
                                placeholder="Malay name"
                            >
                            @error('name_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <div x-show="activeLang === 'zh'" x-cloak>
                            <input
                                type="text"
                                wire:model="name_zh"
                                class="{{ $errors->has('name_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}"
                                placeholder="Chinese name"
                            >
                            @error('name_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        <p class="mt-1 text-xs text-slate-500">Switch tabs to fill EN/MS/ZH. If MS/ZH is empty, the app will fall back to English.</p>
                    </x-admin.form.group>

                    <x-admin.form.group label="Status">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="is_active" class="rounded">
                            <span>Active</span>
                        </label>
                    </x-admin.form.group>

                    @if($categoryId)
                        @can('merchant-categories.edit')
                        <button class="pk-btn-primary w-full">Update Category</button>
                        @endcan
                    @else
                        @can('merchant-categories.create')
                        <button class="pk-btn-primary w-full">Create Category</button>
                        @endcan
                    @endif
                </form>
            </div>
        </div>

        {{-- List --}}
        <div class="xl:col-span-2">
            {{-- Mobile card view --}}
            <div class="pk-mobile-only space-y-3">
                @forelse($categories as $cat)
                    <article class="pk-card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">{{ $cat->name }}</p>
                            <x-admin.status-badge :status="$cat->is_active ? 'active' : 'inactive'" />
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" wire:click="edit({{ $cat->id }})">View</button>
                            @can('merchant-categories.edit')
                            <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" wire:click="edit({{ $cat->id }})">Edit</button>
                            @endcan
                            @can('merchant-categories.activate')
                            @if($cat->is_active)
                                <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" style="color:#dc2626" wire:click="toggleStatus({{ $cat->id }})">Deactivate</button>
                            @else
                                <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" style="color:#16a34a" wire:click="toggleStatus({{ $cat->id }})">Activate</button>
                            @endif
                            @endcan
                            @can('merchant-categories.delete')
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-rose-600" wire:click="delete({{ $cat->id }})" wire:confirm="Delete '{{ $cat->name }}'?">Delete</button>
                            @endcan
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-slate-400">No categories found.</p>
                @endforelse
            </div>

            {{-- Desktop table view --}}
            <div class="pk-desktop-only">
                <x-admin.table.wrapper>
                    <table class="pk-table w-full">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1">Name <span>{{ $this->sortIcon('name') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('is_active')" class="inline-flex items-center gap-1">Status <span>{{ $this->sortIcon('is_active') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('created_at')" class="inline-flex items-center gap-1">Created <span>{{ $this->sortIcon('created_at') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($categories as $cat)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold text-slate-700">{{ $cat->name }}</td>
                                    <td class="whitespace-nowrap"><x-admin.status-badge :status="$cat->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="whitespace-nowrap text-slate-500">{{ $cat->created_at->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="edit({{ $cat->id }})">View</button>
                                            @can('merchant-categories.edit')
                                            <button class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="edit({{ $cat->id }})">Edit</button>
                                            @endcan
                                            @can('merchant-categories.activate')
                                            @if($cat->is_active)
                                                <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#dc2626" wire:click="toggleStatus({{ $cat->id }})">Deactivate</button>
                                            @else
                                                <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="toggleStatus({{ $cat->id }})">Activate</button>
                                            @endif
                                            @endcan
                                            @can('merchant-categories.delete')
                                            <button class="pk-btn-secondary px-3 py-1.5 text-xs text-rose-600" wire:click="delete({{ $cat->id }})" wire:confirm="Delete '{{ $cat->name }}'?">Delete</button>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-sm text-slate-400">No categories found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-admin.table.wrapper>
            </div>
            <div class="mt-3">{{ $categories->links() }}</div>
        </div>
    </section>
</div>
