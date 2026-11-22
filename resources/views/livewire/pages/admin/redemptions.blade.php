<?php

use App\Models\Member;
use App\Models\PointTransaction;
use App\Models\Redemption;
use App\Models\RedemptionItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public string $status = '';
    public string $search = '';
    public string $sortField = 'redeemed_at';
    public string $sortDirection = 'desc';

    public function with(): array
    {
        $allowed = ['points_used', 'redeemed_at', 'status'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'redeemed_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'redemptions' => Redemption::query()
                ->with(['member', 'item', 'fulfilledByMerchant:id,company_name'])
                ->when($this->search !== '', fn ($q) => $q->whereHas('member', fn ($sub) => $sub->where('full_name', 'like', '%'.$this->search.'%'))
                    ->orWhereHas('item', fn ($sub) => $sub->where('name', 'like', '%'.$this->search.'%')))
                ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
                ->orderBy($sortField, $sortDirection)
                ->paginate(12),
            'popularItems' => Redemption::query()
                ->selectRaw('redemption_item_id, count(*) as total')
                ->groupBy('redemption_item_id')
                ->with('item')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
        ];
    }

    public function sortBy(string $field): void
    {
        $allowed = ['points_used', 'redeemed_at', 'status'];
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

    public function markRedeemed(int $id): void
    {
        abort_unless(auth()->user()->can('redemptions.edit'), 403);

        $redemption = Redemption::findOrFail($id);

        if ($redemption->status !== 'pending') return;

        DB::transaction(function () use ($redemption) {
            $redemption->update([
                'status'       => 'completed',
                'fulfilled_at' => now(),
            ]);

            // Deduct points from the member now that the item has been given.
            $member = Member::find($redemption->member_id);
            if ($member) {
                PointTransaction::create([
                    'member_id'        => $member->id,
                    'merchant_id'      => null,
                    'type'             => 'redemption',
                    'currency_amount'  => null,
                    'points'           => -(int) $redemption->points_used,
                    'reference_number' => $redemption->reference_number,
                    'reason'           => 'Redeemed: ' . ($redemption->item?->name ?? 'item'),
                    'status'           => 'completed',
                    'occurred_at'      => now(),
                ]);

                $member->decrement('total_points', (int) $redemption->points_used);
            }
        });

        $this->dispatch('pk-toast', message: 'Redemption marked as completed. Member points deducted.');
    }

    public function markCancelled(int $id): void
    {
        abort_unless(auth()->user()->can('redemptions.edit'), 403);

        $redemption = Redemption::findOrFail($id);

        if ($redemption->status !== 'pending') return;

        DB::transaction(function () use ($redemption) {
            $redemption->update(['status' => 'cancelled']);

            RedemptionItem::where('id', $redemption->redemption_item_id)
                ->increment('quantity_available');
        });

        $this->dispatch('pk-toast', message: 'Redemption cancelled. Item quantity restored.');
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function exportCsv()
    {
        abort_unless(auth()->user()->can('redemptions.edit'), 403);

        $rows = Redemption::query()->with(['member', 'item'])->latest()->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Member', 'Item', 'Points Used', 'Date', 'Status']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->member?->full_name,
                    $row->item?->name,
                    $row->points_used,
                    optional($row->redeemed_at)->toDateTimeString(),
                    $row->status,
                ]);
            }
            fclose($handle);
        }, 'redemptions.csv');
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="pk-page-title">Redemption Monitoring</h1>
            <p class="pk-subtitle">Track redemptions, item popularity, and suspicious patterns.</p>
        </div>
    </div>
</x-slot>

<div class="space-y-6">
    <section class="pk-filter-bar">
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by member or item..." class="pk-input w-full sm:max-w-sm">
        <select wire:model.live="status" class="pk-input w-full sm:max-w-xs">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
        </select>
    </section>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="xl:col-span-2">
            {{-- Mobile card view --}}
            <div class="pk-mobile-only space-y-3">
                @foreach($redemptions as $redemption)
                    <article class="pk-card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $redemption->member?->full_name ?? '-' }}</p>
                                <p class="text-xs text-slate-500">{{ $redemption->item?->name ?? '-' }}</p>
                            </div>
                            <x-admin.status-badge :status="$redemption->status" />
                        </div>
                        <dl class="mt-3 space-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3"><dt>Points Used</dt><dd>{{ number_format($redemption->points_used) }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Date</dt><dd>{{ optional($redemption->redeemed_at)->format('d M Y') }}</dd></div>
                            @if($redemption->fulfilledByMerchant)
                            <div class="flex justify-between gap-3"><dt>Fulfilled By</dt><dd class="text-indigo-700 font-medium">{{ $redemption->fulfilledByMerchant->company_name }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Fulfilled At</dt><dd>{{ optional($redemption->fulfilled_at)->format('d M Y H:i') }}</dd></div>
                            @if($redemption->evidence_image_url)
                            <div class="flex justify-between gap-3"><dt>Evidence</dt><dd><a href="{{ $redemption->evidence_image_url }}" target="_blank" rel="noopener noreferrer" class="text-[#2E5F8A] underline">View</a></dd></div>
                            @endif
                            @endif
                        </dl>
                        @if($redemption->status === 'pending')
                            @can('redemptions.edit')
                            <div class="mt-3 flex gap-2 border-t border-slate-100 pt-3">
                                <button type="button"
                                        wire:click="markRedeemed({{ $redemption->id }})"
                                        wire:confirm="Mark this redemption as completed (item given)?"
                                        class="flex-1 inline-flex items-center justify-center gap-1 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    Given
                                </button>
                                <button type="button"
                                        wire:click="markCancelled({{ $redemption->id }})"
                                        wire:confirm="Cancel this redemption? The item quantity will be restored."
                                        class="flex-1 inline-flex items-center justify-center gap-1 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    Not Given
                                </button>
                            </div>
                            @endcan
                        @endif
                    </article>
                @endforeach
            </div>

            {{-- Desktop table view --}}
            <div class="pk-desktop-only">
                <x-admin.table.wrapper>
                    <table class="pk-table w-full">
                        <thead>
                            <tr>
                                <th class="whitespace-nowrap">Member</th>
                                <th class="whitespace-nowrap">Item</th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('points_used')" class="inline-flex items-center gap-1">Points Used <span>{{ $this->sortIcon('points_used') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('redeemed_at')" class="inline-flex items-center gap-1">Date <span>{{ $this->sortIcon('redeemed_at') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">Status <span>{{ $this->sortIcon('status') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">Fulfilled By</th>
                                <th class="whitespace-nowrap">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($redemptions as $redemption)
                                <tr>
                                    <td class="whitespace-nowrap">{{ $redemption->member?->full_name ?? '-' }}</td>
                                    <td class="whitespace-nowrap">{{ $redemption->item?->name ?? '-' }}</td>
                                    <td class="whitespace-nowrap">{{ number_format($redemption->points_used) }}</td>
                                    <td class="whitespace-nowrap">{{ optional($redemption->redeemed_at)->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap"><x-admin.status-badge :status="$redemption->status" /></td>
                                    <td class="whitespace-nowrap text-xs text-slate-600">
                                        @if($redemption->fulfilledByMerchant)
                                            <p class="font-medium text-indigo-700">{{ $redemption->fulfilledByMerchant->company_name }}</p>
                                            <p class="text-slate-400">{{ optional($redemption->fulfilled_at)->format('d M Y H:i') }}</p>
                                            @if($redemption->evidence_image_url)
                                                <a href="{{ $redemption->evidence_image_url }}" target="_blank" rel="noopener noreferrer" class="text-[#2E5F8A] underline text-[10px]">View Evidence</a>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap">
                                        @if($redemption->status === 'pending')
                                            @can('redemptions.edit')
                                            <div class="flex items-center gap-2">
                                                <button type="button"
                                                        wire:click="markRedeemed({{ $redemption->id }})"
                                                        wire:confirm="Mark this redemption as completed (item given)?"
                                                        class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                    Given
                                                </button>
                                                <button type="button"
                                                        wire:click="markCancelled({{ $redemption->id }})"
                                                        wire:confirm="Cancel this redemption? The item quantity will be restored."
                                                        class="inline-flex items-center gap-1 rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100 transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    Not Given
                                                </button>
                                            </div>
                                            @endcan
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.table.wrapper>
            </div>
            <div class="mt-3">{{ $redemptions->links() }}</div>
        </div>

        <aside class="pk-card p-5">
            <h2 class="text-base font-semibold text-slate-800">Item Popularity</h2>
            <div class="mt-4 space-y-3">
                @forelse($popularItems as $row)
                    <div class="rounded-lg border border-slate-100 p-3">
                        <p class="text-sm font-semibold text-slate-700">{{ $row->item?->name ?? 'Unknown' }}</p>
                        <p class="text-xs text-slate-500">{{ $row->total }} redemptions</p>
                    </div>
                @empty
                    <p class="text-sm text-slate-500">No records yet.</p>
                @endforelse
            </div>
        </aside>
    </div>
</div>
