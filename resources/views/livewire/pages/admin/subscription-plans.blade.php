<?php

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $statusFilter = '';
    public string $audienceFilter = '';
    public string $durationFilter = '';
    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public ?int $planId = null;
    public string $name = '';
    public string $name_ms = '';
    public string $name_zh = '';
    public string $audience = 'both';
    public float $price = 0;
    public string $discount_type = 'none';
    public float $discount_value = 0;
    public float $final_price = 0;
    public int $duration_value = 1;
    public string $duration_unit = 'month';
    public array $descriptions = [];
    public array $descriptionsMs = [];
    public array $descriptionsZh = [];
    public string $newDescription = '';
    public string $newDescriptionMs = '';
    public string $newDescriptionZh = '';
    public bool $is_active = true;
    public string $successMessage = '';
    public string $toastType = 'success';

    public float $billingAdminFee = 30;

    public int $billingGraceDays = 365;

    /** When true, the Member subscription admin fee card is visible. */
    public bool $showBillingConfig = false;

    public ?int $historyModalPlanId = null;

    public string $historyModalPlanName = '';

    public function mount(): void
    {
        $this->billingAdminFee = AppSetting::subscriptionAdminFee();
        $this->billingGraceDays = AppSetting::subscriptionAdminFeeGraceDays();
    }

    public function saveBillingSettings(): void
    {
        abort_unless(auth()->user()->can('subscriptions.edit'), 403);

        $this->validate([
            'billingAdminFee' => ['required', 'numeric', 'min:0'],
            'billingGraceDays' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        AppSetting::setValue(
            AppSetting::SUBSCRIPTION_ADMIN_FEE,
            number_format((float) $this->billingAdminFee, 2, '.', '')
        );
        AppSetting::setValue(
            AppSetting::SUBSCRIPTION_ADMIN_FEE_GRACE_DAYS,
            (string) $this->billingGraceDays
        );

        $this->showToast('Subscription billing settings saved.', 'success');
    }

    public function toggleBillingConfig(): void
    {
        abort_unless(auth()->user()->can('subscriptions.edit'), 403);
        $this->showBillingConfig = ! $this->showBillingConfig;
    }

    public function openPlanHistoryModal(int $id): void
    {
        $plan = SubscriptionPlan::query()->find($id);
        if (! $plan) {
            return;
        }
        $this->historyModalPlanId = $plan->id;
        $this->historyModalPlanName = $plan->name;
    }

    public function closePlanHistoryModal(): void
    {
        $this->historyModalPlanId = null;
        $this->historyModalPlanName = '';
    }

    public function with(): array
    {
        $allowedSortFields = ['name', 'audience', 'price', 'final_price', 'duration_value', 'is_active'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $plans = SubscriptionPlan::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('is_active', $this->statusFilter === 'active'))
            ->when($this->audienceFilter !== '', fn ($query) => $query->where('audience', $this->audienceFilter))
            ->when($this->durationFilter !== '', fn ($query) => $query->where('duration_unit', $this->durationFilter))
            ->orderBy($sortField, $sortDirection)
            ->orderBy('id')
            ->paginate(12);

        $planIds = $plans->pluck('id')->all();
        if ($this->planId !== null && ! in_array($this->planId, $planIds, true)) {
            $planIds[] = $this->planId;
        }
        if ($this->historyModalPlanId !== null && ! in_array($this->historyModalPlanId, $planIds, true)) {
            $planIds[] = $this->historyModalPlanId;
        }
        $planIds = array_values(array_unique(array_map('intval', $planIds)));

        $planSubscriptionsByPlanId = collect();
        $planPaymentsByPlanId = collect();
        if ($planIds !== []) {
            $planSubscriptionsByPlanId = Subscription::query()
                ->whereIn('subscription_plan_id', $planIds)
                ->with(['subscribable'])
                ->orderByDesc('starts_at')
                ->get()
                ->groupBy('subscription_plan_id')
                ->map(fn ($rows) => $rows->take(30))
                ->mapWithKeys(fn ($rows, $key) => [(int) $key => $rows]);

            $planPaymentsByPlanId = PaymentTransaction::query()
                ->where('transaction_type', 'subscription')
                ->whereIn('subscription_plan_id', $planIds)
                ->with(['subscribable'])
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('subscription_plan_id')
                ->map(fn ($rows) => $rows->take(40))
                ->mapWithKeys(fn ($rows, $key) => [(int) $key => $rows]);
        }

        return [
            'plans' => $plans,
            'planSubscriptionsByPlanId' => $planSubscriptionsByPlanId,
            'planPaymentsByPlanId' => $planPaymentsByPlanId,
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

    public function updatedAudienceFilter(): void
    {
        $this->resetPage();
    }

    public function updatedDurationFilter(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        $allowed = ['name', 'audience', 'price', 'final_price', 'duration_value', 'is_active'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

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
        if ($this->sortField !== $field) {
            return '';
        }

        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    public function savePlan(): void
    {
        $isNew = ! $this->planId;
        abort_unless(auth()->user()->can($isNew ? 'subscriptions.create' : 'subscriptions.edit'), 403);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ms' => ['required', 'string', 'max:255'],
            'name_zh' => ['required', 'string', 'max:255'],
            'audience' => ['required', 'in:member,merchant,both'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'discount_type' => ['required', 'in:none,percentage,fixed'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'duration_value' => ['required', 'integer', 'min:1'],
            'duration_unit' => ['required', 'in:day,month,year'],
            'descriptions' => ['array'],
            'descriptions.*' => ['required', 'string', 'max:255'],
            'descriptionsMs' => ['array'],
            'descriptionsMs.*' => ['required', 'string', 'max:255'],
            'descriptionsZh' => ['array'],
            'descriptionsZh.*' => ['required', 'string', 'max:255'],
        ]);

        if ($this->discount_type === 'percentage' && $this->discount_value > 100) {
            $this->addError('discount_value', 'Percentage discount cannot exceed 100.');
            $this->showToast('Failed to save: percentage discount cannot exceed 100%.', 'error');
            return;
        }

        $this->final_price = $this->calculateFinalPrice();

        $plan = SubscriptionPlan::updateOrCreate(
            ['id' => $this->planId],
            [
                'name' => $this->name,
                'name_ms' => trim($this->name_ms),
                'name_zh' => trim($this->name_zh),
                'audience' => $this->audience,
                'price' => $this->price,
                'discount_type' => $this->discount_type,
                'discount_value' => $this->discount_value,
                'final_price' => $this->final_price,
                'duration_value' => $this->duration_value,
                'duration_unit' => $this->duration_unit,
                'is_active' => $this->is_active,
            ]
        );

        $plan->descriptions()->delete();
        foreach ($this->descriptions as $index => $description) {
            $ms = trim((string) ($this->descriptionsMs[$index] ?? ''));
            $zh = trim((string) ($this->descriptionsZh[$index] ?? ''));

            $plan->descriptions()->create([
                'description' => trim($description),
                'description_ms' => $ms,
                'description_zh' => $zh,
                'display_order' => $index,
            ]);
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $this->planId ? 'subscription_plan_updated' : 'subscription_plan_created',
            'entity_type' => SubscriptionPlan::class,
            'entity_id' => $plan->id,
            'after_data' => $plan->only(['name', 'audience', 'price', 'discount_type', 'discount_value', 'final_price', 'duration_value', 'duration_unit', 'is_active']),
        ]);

        $this->showToast(
            $this->planId
                ? 'Subscription plan updated successfully.'
                : 'Subscription plan created successfully.',
            'success'
        );

        $this->resetForm();
    }

    public function editPlan(int $id): void
    {
        $this->closePlanHistoryModal();

        $plan = SubscriptionPlan::with('descriptions')->findOrFail($id);

        $this->planId = $plan->id;
        $this->name = $plan->name;
        $this->name_ms = (string) ($plan->name_ms ?? '');
        $this->name_zh = (string) ($plan->name_zh ?? '');
        $this->audience = $plan->audience;
        $this->price = (float) $plan->price;
        $this->discount_type = (string) ($plan->discount_type ?: 'none');
        $this->discount_value = (float) ($plan->discount_value ?? 0);
        $this->final_price = (float) ($plan->final_price ?? $plan->price);
        $this->duration_value = (int) $plan->duration_value;
        $this->duration_unit = $plan->duration_unit;
        $this->descriptions = $plan->descriptions->pluck('description')->all();
        $this->descriptionsMs = $plan->descriptions->map(function ($d) {
            return (string) ($d->description_ms ?? '');
        })->all();
        $this->descriptionsZh = $plan->descriptions->map(function ($d) {
            return (string) ($d->description_zh ?? '');
        })->all();
        $this->is_active = (bool) $plan->is_active;
        $this->showToast('Plan details loaded successfully.', 'success');
        $this->js("
            window.dispatchEvent(new CustomEvent('plan-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showToast('Ready to create a new plan.', 'success');
        $this->js("
            window.dispatchEvent(new CustomEvent('plan-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function addDescription(): void
    {
        $en = trim($this->newDescription);
        if ($en === '') {
            return;
        }

        $ms = trim((string) ($this->newDescriptionMs ?? ''));
        $zh = trim((string) ($this->newDescriptionZh ?? ''));

        $this->descriptions[] = $en;
        $this->descriptionsMs[] = $ms;
        $this->descriptionsZh[] = $zh;
        $this->newDescription = '';
        $this->newDescriptionMs = '';
        $this->newDescriptionZh = '';
    }

    public function removeDescription(int $index): void
    {
        if (! array_key_exists($index, $this->descriptions)) {
            return;
        }

        unset($this->descriptions[$index]);
        if (array_key_exists($index, $this->descriptionsMs)) {
            unset($this->descriptionsMs[$index]);
        }
        if (array_key_exists($index, $this->descriptionsZh)) {
            unset($this->descriptionsZh[$index]);
        }
        $this->descriptions = array_values($this->descriptions);
        $this->descriptionsMs = array_values($this->descriptionsMs);
        $this->descriptionsZh = array_values($this->descriptionsZh);
    }

    public function togglePlan(int $id): void
    {
        abort_unless(auth()->user()->can('subscriptions.activate'), 403);

        $plan = SubscriptionPlan::findOrFail($id);
        $before = $plan->is_active;
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'subscription_plan_toggled',
            'entity_type' => SubscriptionPlan::class,
            'entity_id' => $plan->id,
            'before_data' => ['is_active' => $before],
            'after_data' => ['is_active' => $plan->is_active],
        ]);
        $this->showToast($plan->is_active ? 'Plan activated.' : 'Plan deactivated.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'planId',
            'name',
            'name_ms',
            'name_zh',
            'audience',
            'price',
            'discount_type',
            'discount_value',
            'final_price',
            'duration_value',
            'duration_unit',
            'descriptions',
            'descriptionsMs',
            'descriptionsZh',
            'newDescription',
            'newDescriptionMs',
            'newDescriptionZh',
            'is_active',
        ]);
        $this->audience = 'both';
        $this->discount_type = 'none';
        $this->discount_value = 0;
        $this->final_price = 0;
        $this->duration_value = 1;
        $this->duration_unit = 'month';
        $this->descriptions = [];
        $this->descriptionsMs = [];
        $this->descriptionsZh = [];
        $this->is_active = true;
    }

    private function calculateFinalPrice(): float
    {
        $price = property_exists($this, 'price')
            ? max(0, (float) $this->price)
            : 0.0;
        $discount = property_exists($this, 'discount_value')
            ? max(0, (float) $this->discount_value)
            : 0.0;
        $discountType = property_exists($this, 'discount_type')
            ? (string) $this->discount_type
            : 'none';

        $final = match ($discountType) {
            'percentage' => $price - (($price * $discount) / 100),
            'fixed' => $price - $discount,
            default => $price,
        };

        return round(max(0, $final), 2);
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = $message;
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }
}; ?>

<x-slot name="header">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h1 class="pk-page-title">Subscription Plan Management</h1>
            <p class="pk-subtitle">Create flexible plans for frontend display and backend subscription assignment.</p>
        </div>
        @can('subscriptions.edit')
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('subscription-billing-config-toggle'))"
                class="pk-btn-primary shrink-0"
            >
                Config
            </button>
        @endcan
    </div>
</x-slot>

<div class="space-y-6" x-data @subscription-billing-config-toggle.window="$wire.toggleBillingConfig()">
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
                >
                    Dismiss
                </button>
            </div>
        </div>
    </div>

    @can('subscriptions.edit')
    @if($showBillingConfig)
    <section class="pk-card p-5" wire:key="billing-config-section">
        <h2 class="text-base font-semibold text-slate-800">Member subscription admin fee</h2>
        <p class="mt-1 text-xs text-slate-500">
            One-time fee for first-time subscriptions and for re-subscriptions after continuous inactivity (subscription lapse or suspension) for at least the grace period. Values are stored in the database (app settings), not in <code class="rounded bg-slate-100 px-1 text-[11px]">.env</code>.
        </p>
        <form wire:submit="saveBillingSettings" class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label class="block text-xs font-semibold text-slate-700">Admin fee (RM)</label>
                <input type="number" min="0" step="0.01" wire:model="billingAdminFee" class="pk-input mt-1 w-full @error('billingAdminFee') border-rose-400 ring-1 ring-rose-200 @enderror">
                @error('billingAdminFee')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-700">Grace period (days)</label>
                <input type="number" min="0" max="3650" step="1" wire:model="billingGraceDays" class="pk-input mt-1 w-full @error('billingGraceDays') border-rose-400 ring-1 ring-rose-200 @enderror">
                @error('billingGraceDays')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[11px] text-slate-500">After this many days of ended eligibility, the admin fee applies again.</p>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="pk-btn-primary">Save billing settings</button>
            </div>
        </form>
    </section>
    @endif
    @endcan

    <section class="pk-filter-bar">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by plan name..." class="pk-input pk-subscription-filter-field">
        <select wire:model.live="statusFilter" class="pk-input pk-subscription-filter-field">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <select wire:model.live="audienceFilter" class="pk-input pk-subscription-filter-field">
            <option value="">All Audiences</option>
            <option value="member">Member</option>
            <option value="merchant">Merchant</option>
            <option value="both">Both</option>
        </select>
        <select wire:model.live="durationFilter" class="pk-input pk-subscription-filter-field">
            <option value="">All Durations</option>
            <option value="day">Day(s)</option>
            <option value="month">Month(s)</option>
            <option value="year">Year(s)</option>
        </select>
    </section>

    <section class="grid items-start gap-6 xl:grid-cols-3">
        <div
            class="pk-card w-full self-start p-5 xl:col-span-1"
            x-data="{
                open: window.matchMedia('(min-width: 768px)').matches || {{ $planId ? 'true' : 'false' }},
                activeLang: 'en'
            }"
            x-init="
                const mq = window.matchMedia('(min-width: 768px)');
                const sync = () => { if (mq.matches) open = true; };
                if (mq.addEventListener) mq.addEventListener('change', sync);
                else mq.addListener(sync);
            "
            @plan-form-open.window="open = true"
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
                <h2 class="text-base font-semibold text-slate-800">{{ $planId ? 'Edit Subscription Plan' : 'Create Subscription Plan' }}</h2>
                <div class="flex items-center gap-2">
                    @if($planId)
                        @can('subscriptions.create')
                        <button type="button" class="pk-btn-primary shrink-0" wire:click="startCreate">
                            Create New
                        </button>
                        @endcan
                    @endif
                    <button
                        type="button"
                        class="flex items-center justify-center rounded-xl border p-2 text-slate-500 transition hover:bg-slate-50 md:hidden"
                        @click="open = !open"
                        :aria-expanded="open"
                    >
                        <svg x-show="!open" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        <svg x-show="open" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    </button>
                </div>
            </div>
            <div x-show="open" x-collapse>
            @if($errors->any())
                <div
                    class="mt-4 rounded-xl border px-3 py-2 text-sm font-medium"
                    style="border-color:#fda4af;background-color:#fff1f2;color:#9f1239;"
                >
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
            <form wire:submit="savePlan" class="mt-4 space-y-3">
                <x-admin.form.group label="Plan Name *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <input class="{{ $errors->has('name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="name" required placeholder="Plan name (EN)">
                        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <input class="{{ $errors->has('name_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="name_ms" placeholder="Malay name">
                        @error('name_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <input class="{{ $errors->has('name_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="name_zh" placeholder="Chinese name">
                        @error('name_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <p class="mt-1 text-xs text-slate-500">Switch tabs to fill EN/MS/ZH. If MS/ZH is empty, the app will fall back to English.</p>
                </x-admin.form.group>
                <x-admin.form.group label="Audience *">
                    <select class="{{ $errors->has('audience') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="audience" required>
                        <option value="member">Member</option>
                        <option value="merchant">Merchant</option>
                        <option value="both">Both</option>
                    </select>
                    @error('audience')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <div
                    x-data="{
                        price: @js((float) $price),
                        discountType: @js((string) $discount_type),
                        discountValue: @js((float) $discount_value),
                        finalPrice: @js((float) $final_price),
                        recalc() {
                            const base = Math.max(0, Number(this.price) || 0);
                            const discount = Math.max(0, Number(this.discountValue) || 0);
                            if (this.discountType === 'percentage') {
                                this.finalPrice = Math.max(0, base - ((base * discount) / 100));
                            } else if (this.discountType === 'fixed') {
                                this.finalPrice = Math.max(0, base - discount);
                            } else {
                                this.finalPrice = base;
                            }
                        }
                    }"
                    x-init="recalc()"
                    class="space-y-3"
                >
                    <x-admin.form.group label="Price *">
                        <input type="number" step="0.01" class="{{ $errors->has('price') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="price" x-model.number="price" @input="recalc()" required>
                        @error('price')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                    <x-admin.form.group label="Discount *">
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <select class="{{ $errors->has('discount_type') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="discount_type" x-model="discountType" @change="recalc()" required>
                                <option value="none">No Discount</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (RM)</option>
                            </select>
                            <input type="number" min="0" step="0.01" class="{{ $errors->has('discount_value') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="discount_value" x-model.number="discountValue" @input="recalc()" placeholder="Discount value" required>
                        </div>
                        @error('discount_type')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        @error('discount_value')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                    <x-admin.form.group label="Final Price">
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            class="pk-input"
                            :value="finalPrice.toFixed(2)"
                            readonly
                        >
                    </x-admin.form.group>
                </div>
                <x-admin.form.group label="Duration *">
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <input type="number" min="1" class="{{ $errors->has('duration_value') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="duration_value" placeholder="Value" required>
                        <select class="{{ $errors->has('duration_unit') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="duration_unit" required>
                            <option value="day">Day(s)</option>
                            <option value="month">Month(s)</option>
                            <option value="year">Year(s)</option>
                        </select>
                    </div>
                    @error('duration_value')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('duration_unit')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <x-admin.form.group label="Plan Descriptions">
                    <p class="mt-0.5 text-xs text-slate-500">Add descriptions in EN. Switch tabs to fill MS/ZH for each saved item. If MS/ZH is empty, the app will fall back to English.</p>

                    <div class="mt-3 space-y-2">
                        <div class="grid grid-cols-1 gap-2">
                            <div x-show="activeLang === 'en'" x-cloak>
                                <input class="pk-input min-w-0 flex-1" wire:model.live="newDescription" placeholder="EN (required) e.g. Up to 10 campaigns">
                            </div>
                            <div x-show="activeLang === 'ms'" x-cloak>
                                <input class="pk-input min-w-0 flex-1" wire:model.live="newDescriptionMs" placeholder="MS">
                            </div>
                            <div x-show="activeLang === 'zh'" x-cloak>
                                <input class="pk-input min-w-0 flex-1" wire:model.live="newDescriptionZh" placeholder="ZH">
                            </div>

                            <div class="flex justify-end">
                                <button type="button" class="pk-btn-secondary px-3 py-2 text-xs" wire:click="addDescription">Add</button>
                            </div>
                        </div>

                        @foreach($descriptions as $index => $description)
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
                                <div class="space-y-2">
                                    <div x-show="activeLang === 'en'" x-cloak>
                                        <input
                                            class="{{ $errors->has('descriptions.'.$index) ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}"
                                            wire:model.live="descriptions.{{ $index }}"
                                            placeholder="EN description"
                                        >
                                        @error('descriptions.'.$index)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div x-show="activeLang === 'ms'" x-cloak>
                                        <input
                                            class="{{ $errors->has('descriptionsMs.'.$index) ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}"
                                            wire:model.live="descriptionsMs.{{ $index }}"
                                            placeholder="MS"
                                        >
                                        @error('descriptionsMs.'.$index)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div x-show="activeLang === 'zh'" x-cloak>
                                        <input
                                            class="{{ $errors->has('descriptionsZh.'.$index) ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}"
                                            wire:model.live="descriptionsZh.{{ $index }}"
                                            placeholder="ZH"
                                        >
                                        @error('descriptionsZh.'.$index)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="button" class="text-xs font-semibold text-rose-600" wire:click="removeDescription({{ $index }})">Delete</button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-admin.form.group>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active"> Active</label>
                @if($planId)
                    @can('subscriptions.edit')
                    <button class="pk-btn-primary w-full">Update Plan</button>
                    @endcan
                @else
                    @can('subscriptions.create')
                    <button class="pk-btn-primary w-full">Create Plan</button>
                    @endcan
                @endif
            </form>
            @if($planId)
                <div class="mt-4 border-t border-slate-200 pt-4">
                    <button
                        type="button"
                        class="pk-btn-secondary pk-btn-accent w-full text-sm font-semibold"
                        wire:click="openPlanHistoryModal({{ $planId }})"
                    >
                        Subscription &amp; payment history
                    </button>
                </div>
            @endif
            </div>
        </div>

        <div class="xl:col-span-2">
            <div class="pk-mobile-only space-y-3">
                @foreach($plans as $plan)
                    <article class="pk-card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-semibold text-slate-800">{{ $plan->name }}</h3>
                            <x-admin.status-badge :status="$plan->is_active ? 'active' : 'inactive'" />
                        </div>
                        <dl class="mt-3 space-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3"><dt>Audience</dt><dd>{{ ucfirst($plan->audience) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Price</dt><dd>RM {{ number_format((float) $plan->price, 2) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Final</dt><dd>RM {{ number_format((float) $plan->final_price, 2) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Duration</dt><dd>{{ $plan->duration_value }} {{ Str::plural($plan->duration_unit, $plan->duration_value) }}</dd></div>
                        </dl>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                type="button"
                                class="pk-btn-secondary pk-btn-accent px-2 py-2 text-xs font-semibold"
                                wire:click="openPlanHistoryModal({{ $plan->id }})"
                            >
                                History
                            </button>
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="editPlan({{ $plan->id }})">View</button>
                            @can('subscriptions.edit')
                            <button class="pk-btn-secondary px-2 py-2 text-xs" wire:click="editPlan({{ $plan->id }})">Edit</button>
                            @endcan
                            @can('subscriptions.activate')
                            @if($plan->is_active)
                                <button class="rounded-xl border px-2 py-2 text-xs font-semibold sm:col-span-2" style="color:#be123c;" wire:click="togglePlan({{ $plan->id }})">Deactivate</button>
                            @else
                                <button class="rounded-xl border px-2 py-2 text-xs font-semibold sm:col-span-2" style="color:#15803d;" wire:click="togglePlan({{ $plan->id }})">Activate</button>
                            @endif
                            @endcan
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="pk-desktop-only">
                <x-admin.table.wrapper>
                    <table class="pk-table w-full" data-disable-client-sort="1">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('name')" class="inline-flex items-center gap-1">
                                        Plan <span>{{ $this->sortIcon('name') }}</span>
                                    </button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('audience')" class="inline-flex items-center gap-1">
                                        Audience <span>{{ $this->sortIcon('audience') }}</span>
                                    </button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('price')" class="inline-flex items-center gap-1">
                                        Price <span>{{ $this->sortIcon('price') }}</span>
                                    </button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('final_price')" class="inline-flex items-center gap-1">
                                        Final Price <span>{{ $this->sortIcon('final_price') }}</span>
                                    </button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('duration_value')" class="inline-flex items-center gap-1">
                                        Duration <span>{{ $this->sortIcon('duration_value') }}</span>
                                    </button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('is_active')" class="inline-flex items-center gap-1">
                                        Status <span>{{ $this->sortIcon('is_active') }}</span>
                                    </button>
                                </th>
                                <th class="whitespace-nowrap text-right">Actions</th>
                            </tr>
                        </thead>
                            @foreach($plans as $plan)
                            <tbody wire:key="subscription-plan-row-{{ $plan->id }}">
                                <tr class="align-top">
                                    <td class="whitespace-nowrap">{{ $plan->name }}</td>
                                    <td class="whitespace-nowrap">{{ ucfirst($plan->audience) }}</td>
                                    <td class="whitespace-nowrap">RM {{ number_format((float) $plan->price, 2) }}</td>
                                    <td class="whitespace-nowrap">RM {{ number_format((float) $plan->final_price, 2) }}</td>
                                    <td class="whitespace-nowrap">{{ $plan->duration_value }} {{ Str::plural($plan->duration_unit, $plan->duration_value) }}</td>
                                    <td class="whitespace-nowrap"><x-admin.status-badge :status="$plan->is_active ? 'active' : 'inactive'" /></td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-1">
                                            <button type="button" class="pk-btn-secondary pk-btn-accent px-2 py-1.5 text-xs font-semibold" wire:click="openPlanHistoryModal({{ $plan->id }})">History</button>
                                            <button class="pk-btn-secondary px-2 py-1.5 text-xs" wire:click="editPlan({{ $plan->id }})">View</button>
                                            @can('subscriptions.edit')
                                            <button class="pk-btn-secondary px-2 py-1.5 text-xs" wire:click="editPlan({{ $plan->id }})">Edit</button>
                                            @endcan
                                            @can('subscriptions.activate')
                                            @if($plan->is_active)
                                                <button
                                                    class="rounded-xl border px-2 py-1.5 text-xs font-semibold transition"
                                                    style="color:#be123c;"
                                                    wire:click="togglePlan({{ $plan->id }})"
                                                >
                                                    Deactivate
                                                </button>
                                            @else
                                                <button
                                                    class="rounded-xl border px-2 py-1.5 text-xs font-semibold transition"
                                                    style="color:#15803d;"
                                                    wire:click="togglePlan({{ $plan->id }})"
                                                >
                                                    Activate
                                                </button>
                                            @endif
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                            @endforeach
                    </table>
                </x-admin.table.wrapper>
            </div>
            <div class="mt-3">{{ $plans->links() }}</div>
        </div>
    </section>

    {{-- Plan history modal (subscription assignments & gateway payments) --}}
    <div
        x-data="{ planId: @entangle('historyModalPlanId').live }"
        x-show="planId !== null"
        x-cloak
        class="fixed inset-0 z-[130] flex items-end justify-center sm:items-center sm:p-4"
        style="isolation: isolate;"
    >
        <div
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
            wire:click="closePlanHistoryModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0"
        ></div>

        <div
            class="relative flex w-full flex-col bg-white shadow-2xl sm:max-w-3xl sm:rounded-2xl"
            style="max-height: 92dvh;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-4"
            @click.stop
        >
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800">Subscription &amp; payment history</h2>
                    @if($historyModalPlanName !== '')
                        <p class="mt-0.5 text-xs text-slate-500">{{ $historyModalPlanName }}</p>
                    @endif
                </div>
                <button
                    type="button"
                    wire:click="closePlanHistoryModal"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Close"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="flex-1 overflow-y-auto px-6 py-4">
                @if($historyModalPlanId)
                    <x-admin.subscription-plan-history-panels
                        :subscriptions="$planSubscriptionsByPlanId->get($historyModalPlanId, collect())"
                        :payments="$planPaymentsByPlanId->get($historyModalPlanId, collect())"
                    />
                @endif
            </div>
        </div>
    </div>
</div>
