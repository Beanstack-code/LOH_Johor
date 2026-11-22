<?php

use App\Models\ConversionSetting;
use App\Models\Member;
use App\Models\PointTransaction;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component {
    public string $currency_unit = 'MYR';
    public float $points_per_currency = 1;
    public string $effective_date = '';
    public ?int $adjust_member_id = null;
    public ?Member $selectedAdjustMember = null;
    public int $adjust_points = 0;
    public string $adjust_reason = '';
    public string $adjust_reference = '';

    public function selectAdjustMember(int $id): void
    {
        $this->adjust_member_id = $id;
        $this->selectedAdjustMember = Member::find($id);
    }

    public function mount(): void
    {
        $latest = ConversionSetting::query()->latest('effective_date')->first();
        if ($latest) {
            $this->currency_unit = $latest->currency_unit;
            $this->points_per_currency = (float) $latest->points_per_currency;
            $this->effective_date = (string) optional($latest->effective_date)->format('Y-m-d');
        } else {
            $this->effective_date = now()->toDateString();
        }
    }

    public function with(): array
    {
        return [
            'settings' => ConversionSetting::query()->latest('effective_date')->limit(10)->get(),
            'members' => Member::query()->orderBy('full_name')->limit(100)->get(),
        ];
    }

    public function saveConversion(): void
    {
        abort_unless(auth()->user()->can('points-config.edit'), 403);

        $this->validate([
            'points_per_currency' => ['required', 'numeric', 'min:0.01'],
            'effective_date' => ['required', 'date', 'after_or_equal:today'],
        ]);

        ConversionSetting::create([
            'currency_unit' => 'MYR',
            'points_per_currency' => $this->points_per_currency,
            'effective_date' => $this->effective_date,
            'is_active' => true,
            'changed_by' => auth()->id(),
        ]);

        $this->dispatch('pk-toast', message: 'Conversion ratio saved successfully.');
    }

    public function applyAdjustment(): void
    {
        abort_unless(auth()->user()->can('points-config.edit'), 403);

        $this->validate([
            'adjust_member_id' => ['required', 'exists:members,id'],
            'adjust_points' => ['required', 'integer', 'not_in:0'],
            'adjust_reason' => ['required', 'string'],
            'adjust_reference' => ['required', 'string'],
        ]);

        $member = Member::findOrFail($this->adjust_member_id);
        $member->increment('total_points', $this->adjust_points);

        PointTransaction::create([
            'member_id' => $member->id,
            'type' => 'manual_adjustment',
            'points' => $this->adjust_points,
            'reason' => $this->adjust_reason,
            'reference_number' => $this->adjust_reference,
            'status' => 'completed',
            'occurred_at' => now(),
        ]);

        $this->reset(['adjust_member_id', 'adjust_points', 'adjust_reason', 'adjust_reference']);
        $this->selectedAdjustMember = null;
        $this->dispatch('pk-toast', message: 'Points adjusted successfully.');
    }
}; ?>

<x-slot name="header">
    <div>
        <h1 class="pk-page-title">Points / Rebate Configuration</h1>
        <p class="pk-subtitle">Global conversion ratio and manual point adjustments with references.</p>
    </div>
</x-slot>

<div class="space-y-6">
    <div class="grid gap-6 xl:grid-cols-2">
        <section class="pk-card p-5">
            <h2 class="text-base font-semibold text-slate-800">Global Conversion</h2>
            @if($errors->hasAny(['currency_unit', 'points_per_currency', 'effective_date']))
                <div class="mt-4 rounded-xl border px-3 py-2 text-sm font-medium" style="border-color:#fda4af;background-color:#fff1f2;color:#9f1239;">
                    Please fill in all required fields marked with *.
                </div>
            @endif
            <form wire:submit="saveConversion" class="mt-4 space-y-3">
                <x-admin.form.group label="Currency Unit *">
                    <input class="pk-input bg-slate-50" value="MYR (Malaysia Ringgit)" disabled readonly>
                </x-admin.form.group>
                <x-admin.form.group label="1 Currency = X Points *">
                    <input class="{{ $errors->has('points_per_currency') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" type="number" step="0.01" wire:model="points_per_currency">
                    @error('points_per_currency')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <x-admin.form.group label="Effective Date *">
                    <input class="{{ $errors->has('effective_date') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" type="date" min="{{ now()->toDateString() }}" wire:model="effective_date">
                    @error('effective_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                @can('points-config.edit')
                <button class="pk-btn-primary w-full">Save Ratio</button>
                @endcan
            </form>
        </section>

        <section class="pk-card p-5">
            <h2 class="text-base font-semibold text-slate-800">Manual Adjustment</h2>
            @if($errors->hasAny(['adjust_member_id', 'adjust_points', 'adjust_reason', 'adjust_reference']))
                <div class="mt-4 rounded-xl border px-3 py-2 text-sm font-medium" style="border-color:#fda4af;background-color:#fff1f2;color:#9f1239;">
                    Please fill in all required fields marked with *.
                </div>
            @endif
            <form wire:submit="applyAdjustment" class="mt-4 space-y-3">
                <x-admin.form.group label="Member *">
                    <div x-data="{ open: false, search: '' }">
                        <div class="relative">
                            <button @click="open = !open" type="button" class="{{ $errors->has('adjust_member_id') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }} text-left flex items-center justify-between w-full">
                                <span class="{{ $selectedAdjustMember ? 'text-slate-700' : 'text-slate-400' }}">
                                    {{ $selectedAdjustMember ? $selectedAdjustMember->full_name . ' (' . $selectedAdjustMember->email . ')' : '-- Select Member --' }}
                                </span>
                                <svg class="h-5 w-5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-hidden">
                                <div class="p-2 border-b border-slate-200">
                                    <input type="text" x-model="search" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#ed1c24] focus:border-[#ed1c24]" placeholder="Search by name, email or phone...">
                                </div>
                                <div class="overflow-y-auto max-h-64">
                                    @foreach($members as $member)
                                        <button type="button"
                                                wire:click="selectAdjustMember({{ $member->id }})"
                                                @click="open = false; search = ''"
                                                x-show="search === '' || '{{ strtolower($member->full_name . ' ' . $member->email . ' ' . $member->phone) }}'.includes(search.toLowerCase())"
                                                class="w-full text-left px-4 py-2.5 hover:bg-slate-50 transition text-sm border-b border-slate-100 last:border-b-0">
                                            <div class="font-medium text-slate-700">{{ $member->full_name }}</div>
                                            <div class="text-xs text-slate-500">{{ $member->email }} {{ $member->phone ? '· ' . $member->phone : '' }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                    @error('adjust_member_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <x-admin.form.group label="Points (+/-) *">
                    <input class="{{ $errors->has('adjust_points') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" type="number" wire:model="adjust_points">
                    @error('adjust_points')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <x-admin.form.group label="Reason *">
                    <input class="{{ $errors->has('adjust_reason') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="adjust_reason">
                    @error('adjust_reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                <x-admin.form.group label="Reference Number *">
                    <input class="{{ $errors->has('adjust_reference') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="adjust_reference">
                    @error('adjust_reference')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>
                @can('points-config.edit')
                <button class="pk-btn-primary w-full">Apply</button>
                @endcan
            </form>
        </section>
    </div>

    <section class="pk-card p-5">
        <h2 class="text-base font-semibold text-slate-800">Change History</h2>
        <div class="mt-3 space-y-2">
            @foreach($settings as $setting)
                <div class="rounded-lg border border-slate-100 p-3 text-sm">
                    {{ $setting->effective_date?->format('d M Y') }} — 1 {{ $setting->currency_unit }} = {{ $setting->points_per_currency }} points
                </div>
            @endforeach
        </div>
    </section>
</div>
