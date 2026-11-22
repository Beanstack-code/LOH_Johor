<?php

use App\Models\Member;
use App\Models\Merchant;
use App\Models\PointTransaction;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $type     = '';
    public ?int $memberId   = null;
    public ?int $merchantId = null;
    public string $dateFrom = '';
    public string $dateTo   = '';
    public string $sortField     = 'occurred_at';
    public string $sortDirection = 'desc';

    public ?Member   $selectedMember   = null;
    public ?Merchant $selectedMerchant = null;

    public function selectMember(int $id): void
    {
        $this->memberId       = $id;
        $this->selectedMember = Member::find($id);
        $this->resetPage();
    }

    public function clearMember(): void
    {
        $this->memberId       = null;
        $this->selectedMember = null;
        $this->resetPage();
    }

    public function selectMerchant(int $id): void
    {
        $this->merchantId       = $id;
        $this->selectedMerchant = Merchant::find($id);
        $this->resetPage();
    }

    public function clearMerchant(): void
    {
        $this->merchantId       = null;
        $this->selectedMerchant = null;
        $this->resetPage();
    }

    public function with(): array
    {
        $allowed = ['currency_amount', 'points', 'occurred_at', 'status', 'type'];
        $sortField     = in_array($this->sortField, $allowed, true) ? $this->sortField : 'occurred_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $query = PointTransaction::query()
            ->with(['merchant', 'member'])
            ->when($this->type !== '',       fn ($q) => $q->where('type', $this->type))
            ->when($this->memberId !== null,   fn ($q) => $q->where('member_id', $this->memberId))
            ->when($this->merchantId !== null, fn ($q) => $q->where('merchant_id', $this->merchantId))
            ->when($this->dateFrom !== '',    fn ($q) => $q->whereDate('occurred_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '',      fn ($q) => $q->whereDate('occurred_at', '<=', $this->dateTo))
            ->orderBy($sortField, $sortDirection);

        return [
            'transactions' => $query->paginate(15),
            'members'      => Member::orderBy('full_name')->get(['id', 'full_name', 'email', 'phone']),
            'merchants'    => Merchant::where('status', 'active')->orderBy('company_name')->get(['id', 'company_name', 'company_email']),
        ];
    }

    public function sortBy(string $field): void
    {
        $allowed = ['currency_amount', 'points', 'occurred_at', 'type'];
        if (! in_array($field, $allowed, true)) return;
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField     = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function sortIcon(string $field): string
    {
        if ($this->sortField !== $field) return '';
        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    public function updatedType(): void    { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void   { $this->resetPage(); }

    public function exportCsv()
    {
        abort_unless(auth()->user()->can('transactions.view'), 403);

        $rows = PointTransaction::query()
            ->with(['merchant', 'member'])
            ->when($this->type !== '',       fn ($q) => $q->where('type', $this->type))
            ->when($this->memberId !== null,   fn ($q) => $q->where('member_id', $this->memberId))
            ->when($this->merchantId !== null, fn ($q) => $q->where('merchant_id', $this->merchantId))
            ->when($this->dateFrom !== '',    fn ($q) => $q->whereDate('occurred_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '',      fn ($q) => $q->whereDate('occurred_at', '<=', $this->dateTo))
            ->orderBy('occurred_at', 'desc')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Type', 'Member', 'Merchant', 'Currency Amount', 'Points', 'Reason', 'Reference', 'Date & Time', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->type,
                    $row->member?->full_name ?? '-',
                    $row->merchant?->company_name ?? '-',
                    $row->currency_amount ? 'RM ' . number_format((float) $row->currency_amount, 2) : '-',
                    ($row->points > 0 ? '+' : '') . number_format($row->points),
                    $row->reason ?? '-',
                    $row->reference_number ?? '-',
                    optional($row->occurred_at)->format('d M Y, h:i A') ?? '-',
                    $row->status,
                ]);
            }
            fclose($handle);
        }, 'transactions_' . date('Y-m-d_His') . '.csv');
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="pk-page-title">Transaction History</h1>
            <p class="pk-subtitle">All point transactions — allocations, redemptions, adjustments and more.</p>
        </div>
    </div>
</x-slot>

<div class="space-y-6">
    {{-- Filters --}}
    <section class="pk-filter-bar items-start">

        {{-- Type --}}
        <select class="pk-input w-full sm:max-w-xs" wire:model.live="type">
            <option value="">All Types</option>
            <option value="allocation">Merchant Points</option>
            <option value="redemption">Redemption</option>
            <option value="manual_adjustment">Manual Adjustment</option>
        </select>

        {{-- Member searchable dropdown --}}
        <div class="w-full sm:max-w-xs" x-data="{ open: false, search: '' }">
            @if($selectedMember)
                <div class="pk-input flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-slate-700">{{ $selectedMember->full_name }}</div>
                        <div class="truncate text-xs text-slate-400">{{ $selectedMember->email }}</div>
                    </div>
                    <button type="button" wire:click="clearMember" class="shrink-0 text-slate-400 hover:text-rose-500 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @else
                <div class="relative">
                    <button @click="open = !open" type="button" class="pk-input flex w-full items-center justify-between text-left text-slate-400">
                        <span>Filter by member...</span>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden">
                        <div class="border-b border-slate-100 p-2">
                            <input type="text" x-model="search" @click.stop
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#ed1c24] focus:border-[#ed1c24]"
                                   placeholder="Search members...">
                        </div>
                        <div class="max-h-60 overflow-y-auto">
                            @foreach($members as $m)
                                <button type="button"
                                        wire:click="selectMember({{ $m->id }})"
                                        @click="open = false; search = ''"
                                        x-show="search === '' || '{{ strtolower($m->full_name . ' ' . $m->email . ' ' . $m->phone) }}'.includes(search.toLowerCase())"
                                        class="w-full border-b border-slate-50 px-4 py-2.5 text-left text-sm hover:bg-slate-50 transition last:border-b-0">
                                    <div class="font-medium text-slate-700">{{ $m->full_name }}</div>
                                    <div class="text-xs text-slate-400">{{ $m->email }}{{ $m->phone ? ' · ' . $m->phone : '' }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Merchant searchable dropdown --}}
        <div class="w-full sm:max-w-xs" x-data="{ open: false, search: '' }">
            @if($selectedMerchant)
                <div class="pk-input flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-slate-700">{{ $selectedMerchant->company_name }}</div>
                        <div class="truncate text-xs text-slate-400">{{ $selectedMerchant->company_email }}</div>
                    </div>
                    <button type="button" wire:click="clearMerchant" class="shrink-0 text-slate-400 hover:text-rose-500 transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @else
                <div class="relative">
                    <button @click="open = !open" type="button" class="pk-input flex w-full items-center justify-between text-left text-slate-400">
                        <span>Filter by merchant...</span>
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" @click.away="open = false" x-cloak
                         class="absolute z-20 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg overflow-hidden">
                        <div class="border-b border-slate-100 p-2">
                            <input type="text" x-model="search" @click.stop
                                   class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#ed1c24] focus:border-[#ed1c24]"
                                   placeholder="Search merchants...">
                        </div>
                        <div class="max-h-60 overflow-y-auto">
                            @foreach($merchants as $mer)
                                <button type="button"
                                        wire:click="selectMerchant({{ $mer->id }})"
                                        @click="open = false; search = ''"
                                        x-show="search === '' || '{{ strtolower($mer->company_name . ' ' . $mer->company_email) }}'.includes(search.toLowerCase())"
                                        class="w-full border-b border-slate-50 px-4 py-2.5 text-left text-sm hover:bg-slate-50 transition last:border-b-0">
                                    <div class="font-medium text-slate-700">{{ $mer->company_name }}</div>
                                    <div class="text-xs text-slate-400">{{ $mer->company_email }}</div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Date range: stack on small screens; side-by-side from md --}}
        <div class="flex w-full flex-col gap-3 md:w-auto md:flex-row md:flex-nowrap md:items-center md:gap-3">
            <div class="flex w-full min-w-0 items-center gap-2 md:max-w-xs">
                <span class="shrink-0 text-sm font-medium text-slate-600">From</span>
                <input class="pk-input min-w-0 flex-1" type="date" wire:model.live="dateFrom" title="Date from">
            </div>
            <div class="flex w-full min-w-0 items-center gap-2 md:max-w-xs">
                <span class="shrink-0 text-sm font-medium text-slate-600">To</span>
                <input class="pk-input min-w-0 flex-1" type="date" wire:model.live="dateTo" title="Date to">
            </div>
        </div>

    </section>

    {{-- Mobile card view --}}
    <div class="pk-mobile-only space-y-3">
        @forelse($transactions as $transaction)
            <article class="pk-card p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800 truncate">{{ $transaction->member?->full_name ?? '-' }}</p>
                        <p class="text-xs text-slate-500 truncate">{{ $transaction->merchant?->company_name ?? ($transaction->reason ?? '-') }}</p>
                    </div>
                    @include('livewire.pages.admin.partials.transaction-type-badge', ['type' => $transaction->type])
                </div>
                <dl class="mt-3 space-y-1.5 text-xs text-slate-600">
                    @if($transaction->currency_amount)
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Amount</dt>
                        <dd>
                            <span class="text-xs font-medium text-rose-400">RM</span>
                            <span class="font-bold text-rose-600">{{ number_format((float) $transaction->currency_amount, 2) }}</span>
                        </dd>
                    </div>
                    @endif
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Points</dt>
                        <dd>
                            @if($transaction->points >= 0)
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700">
                                    +{{ number_format($transaction->points) }} pts
                                </span>
                            @else
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700">
                                    {{ number_format($transaction->points) }} pts
                                </span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Date</dt>
                        <dd class="text-right">
                            <div class="font-medium text-slate-700">{{ optional($transaction->occurred_at)->format('d M Y') }}</div>
                            <div class="text-slate-400">{{ optional($transaction->occurred_at)->format('h:i A') }}</div>
                        </dd>
                    </div>
                    @if($transaction->reason)
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">Note</dt><dd class="text-right max-w-[60%] truncate italic text-slate-500">{{ $transaction->reason }}</dd></div>
                    @endif
                </dl>
            </article>
        @empty
            <div class="pk-card p-10 text-center text-sm text-slate-500">No transactions found.</div>
        @endforelse
    </div>

    {{-- Desktop table view --}}
    <div class="pk-desktop-only">
        <x-admin.table.wrapper>
            <table class="pk-table w-full">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap">
                            <button type="button" wire:click="sortBy('type')" class="inline-flex items-center gap-1">Type <span>{{ $this->sortIcon('type') }}</span></button>
                        </th>
                        <th class="whitespace-nowrap">Member</th>
                        <th class="whitespace-nowrap">Merchant / Note</th>
                        <th class="whitespace-nowrap text-right">
                            <button type="button" wire:click="sortBy('currency_amount')" class="inline-flex items-center gap-1">Amount <span>{{ $this->sortIcon('currency_amount') }}</span></button>
                        </th>
                        <th class="whitespace-nowrap text-right">
                            <button type="button" wire:click="sortBy('points')" class="inline-flex items-center gap-1">Points <span>{{ $this->sortIcon('points') }}</span></button>
                        </th>
                        <th class="whitespace-nowrap">Reference</th>
                        <th class="whitespace-nowrap">
                            <button type="button" wire:click="sortBy('occurred_at')" class="inline-flex items-center gap-1">Date &amp; Time <span>{{ $this->sortIcon('occurred_at') }}</span></button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            {{-- Type --}}
                            <td class="whitespace-nowrap">
                                @include('livewire.pages.admin.partials.transaction-type-badge', ['type' => $transaction->type])
                            </td>

                            {{-- Member --}}
                            <td class="whitespace-nowrap">
                                <span class="font-semibold text-slate-800">{{ $transaction->member?->full_name ?? '-' }}</span>
                            </td>

                            {{-- Merchant / Note --}}
                            <td class="max-w-[220px]">
                                @if($transaction->merchant)
                                    <span class="text-slate-700">{{ $transaction->merchant->company_name }}</span>
                                @elseif($transaction->reason)
                                    <span class="italic text-slate-500 text-xs">{{ $transaction->reason }}</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>

                            {{-- Amount --}}
                            <td class="whitespace-nowrap text-right">
                                @if($transaction->currency_amount)
                                    <div class="inline-flex items-baseline gap-0.5">
                                        <span class="text-xs font-medium text-rose-400">RM</span>
                                        <span class="font-bold text-rose-600">{{ number_format((float) $transaction->currency_amount, 2) }}</span>
                                    </div>
                                @else
                                    <span class="text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Points --}}
                            <td class="whitespace-nowrap text-right">
                                @if($transaction->points >= 0)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-sm font-bold text-emerald-700">
                                        +{{ number_format($transaction->points) }}
                                        <span class="text-xs font-medium text-emerald-500">pts</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-sm font-bold text-rose-700">
                                        {{ number_format($transaction->points) }}
                                        <span class="text-xs font-medium text-rose-400">pts</span>
                                    </span>
                                @endif
                            </td>

                            {{-- Reference --}}
                            <td class="whitespace-nowrap">
                                <span class="font-mono text-xs text-slate-400 tracking-tight">{{ $transaction->reference_number ?? '—' }}</span>
                            </td>

                            {{-- Date --}}
                            <td class="whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-700">{{ optional($transaction->occurred_at)->format('d M Y') }}</div>
                                <div class="text-xs text-slate-400">{{ optional($transaction->occurred_at)->format('h:i A') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-sm text-slate-400">No transactions found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </x-admin.table.wrapper>
    </div>
    <div class="mt-3">{{ $transactions->links() }}</div>
</div>
