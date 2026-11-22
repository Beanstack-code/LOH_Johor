<?php

use App\Models\Merchant;
use App\Models\PointTransaction;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public ?int $merchantId = null;
    public ?Merchant $selectedMerchant = null;
    public string $merchantSearch = '';

    public function selectMerchant(int $id): void
    {
        $this->merchantId = $id;
        $this->selectedMerchant = Merchant::find($id);
        $this->merchantSearch = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $transactions = null;
        if ($this->selectedMerchant) {
            $transactions = PointTransaction::with(['member'])
                ->where('merchant_id', $this->selectedMerchant->id)
                ->latest('occurred_at')
                ->paginate(10);
        }

        $merchantsQuery = Merchant::orderBy('company_name');
        if ($this->merchantSearch !== '') {
            $merchantsQuery->where(function ($q) {
                $q->where('company_name', 'like', '%' . $this->merchantSearch . '%')
                  ->orWhere('company_email', 'like', '%' . $this->merchantSearch . '%')
                  ->orWhere('phone', 'like', '%' . $this->merchantSearch . '%');
            });
        }

        return [
            'merchants' => $merchantsQuery->limit(50)->get(),
            'transactions' => $transactions,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div>
            <h1 class="pk-page-title">Merchant QR Codes</h1>
            <p class="pk-subtitle">Generate and display QR codes for merchants</p>
        </div>
    </x-slot>

    <div class="space-y-6">
    <!-- Merchant Selection -->
    <div class="pk-card p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">Select Merchant</h2>
        <div class="max-w-md" x-data="{ open: false, search: '' }">
            <label class="pk-label">Choose a merchant</label>
            <div class="relative">
                <button @click="open = !open" type="button" class="pk-input text-left flex items-center justify-between w-full">
                    <span class="{{ $selectedMerchant ? 'text-slate-700' : 'text-slate-400' }}">
                        {{ $selectedMerchant ? $selectedMerchant->company_name . ' (' . $selectedMerchant->company_email . ')' : '-- Select Merchant --' }}
                    </span>
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" @click.away="open = false" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-hidden">
                    <div class="p-2 border-b border-slate-200">
                        <input type="text" x-model="search" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#ed1c24] focus:border-[#ed1c24]" placeholder="Search merchants...">
                    </div>
                    <div class="overflow-y-auto max-h-64">
                        @foreach($merchants as $merchant)
                            <button type="button" 
                                    wire:click="selectMerchant({{ $merchant->id }})" 
                                    @click="open = false"
                                    x-show="search === '' || '{{ strtolower($merchant->company_name . ' ' . $merchant->company_email) }}'.includes(search.toLowerCase())"
                                    class="w-full text-left px-4 py-2.5 hover:bg-slate-50 transition text-sm border-b border-slate-100 last:border-b-0">
                                <div class="font-medium text-slate-700">{{ $merchant->company_name }}</div>
                                <div class="text-xs text-slate-500">{{ $merchant->company_email }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($selectedMerchant)
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Merchant Information -->
            <div class="pk-card p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-4">Merchant Information</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Membership ID</p>
                        <p class="text-sm font-bold text-[#ed1c24]">{{ $selectedMerchant->membership_id }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Company Name</p>
                        <p class="text-sm font-semibold text-slate-800">{{ $selectedMerchant->company_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Email</p>
                        <p class="text-sm text-slate-700">{{ $selectedMerchant->company_email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Phone</p>
                        <p class="text-sm text-slate-700">{{ $selectedMerchant->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Categories</p>
                        <div class="flex flex-wrap gap-2 mt-1">
                            @if(is_array($selectedMerchant->categories))
                                @foreach($selectedMerchant->categories as $category)
                                    <span class="inline-flex items-center rounded-full bg-[#edf4fd] px-2.5 py-0.5 text-xs font-medium text-[#0d4f8b]">
                                        {{ $category }}
                                    </span>
                                @endforeach
                            @else
                                <span class="text-sm text-slate-500">N/A</span>
                            @endif
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Registration Number</p>
                        <p class="text-sm text-slate-700">{{ $selectedMerchant->registration_number ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Status</p>
                        @if($selectedMerchant->status === 'active')
                            <span class="pk-badge-success">Active</span>
                        @elseif($selectedMerchant->status === 'pending')
                            <span class="pk-badge-warning">Pending</span>
                        @else
                            <span class="pk-badge-danger">{{ ucfirst($selectedMerchant->status) }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- QR Code Display -->
            <div class="pk-card p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-4">QR Code</h2>
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="bg-white p-6 rounded-xl border-2 border-slate-200">
                        {!! QrCode::size(250)->generate('MERCHANT:' . $selectedMerchant->id . ':' . $selectedMerchant->company_email) !!}
                    </div>
                    <p class="text-xs text-slate-500 text-center">Merchant identification QR code</p>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 mt-4">
                        <button onclick="window.print()" class="pk-btn-secondary">
                            <svg class="w-4 h-4 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Print QR Code
                        </button>
                        <a href="data:image/svg+xml;base64,{{ base64_encode(QrCode::size(250)->generate('MERCHANT:' . $selectedMerchant->id . ':' . $selectedMerchant->company_email)) }}" 
                           download="merchant-{{ $selectedMerchant->id }}-qr.svg" 
                           class="pk-btn-primary">
                            <svg class="w-4 h-4 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download QR Code
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- QR Code Payload Info -->
        <div class="pk-card p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-2">QR Code Data</h2>
            <p class="text-sm text-slate-600 mb-3">This QR code contains the following information:</p>
            <div class="bg-slate-50 rounded-lg p-4 font-mono text-sm text-slate-700">
                MERCHANT:{{ $selectedMerchant->id }}:{{ $selectedMerchant->company_email }}
            </div>
        </div>

        <!-- Transactions Section -->
        <div class="pk-card p-5">
            <h2 class="text-base font-semibold text-slate-800 mb-4">Transactions</h2>
            
            @if($transactions && $transactions->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 tracking-wide">Date & Time</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 tracking-wide">Member</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 tracking-wide">Currency Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 tracking-wide">Points Granted</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 tracking-wide">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 tracking-wide">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            @foreach($transactions as $transaction)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <div class="text-sm font-medium text-slate-700">{{ $transaction->occurred_at->format('d M Y') }}</div>
                                        <div class="text-xs text-slate-500">{{ $transaction->occurred_at->format('h:i A') }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <div class="h-8 w-8 rounded-full bg-red-100 flex items-center justify-center text-xs font-semibold text-[#ed1c24]">
                                                {{ substr($transaction->member->full_name ?? 'N', 0, 1) }}
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-slate-700">{{ $transaction->member->full_name ?? 'N/A' }}</div>
                                                <div class="text-xs text-slate-500">{{ $transaction->member->email ?? '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm font-medium text-slate-700">RM {{ number_format($transaction->currency_amount, 2) }}</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-sm font-bold text-[#ed1c24]">{{ number_format($transaction->points) }} pts</span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        @if($transaction->status === 'completed')
                                            <span class="pk-badge-success">Completed</span>
                                        @elseif($transaction->status === 'pending')
                                            <span class="pk-badge-warning">Pending</span>
                                        @else
                                            <span class="pk-badge-danger">{{ ucfirst($transaction->status) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-xs font-mono text-slate-600">
                                            {{ $transaction->reference_number ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($transactions->hasPages())
                    <div class="mt-4">
                        {{ $transactions->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-8">
                    <svg class="w-12 h-12 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm font-medium text-slate-500">No transactions yet</p>
                    <p class="text-xs text-slate-400">Transactions will appear here</p>
                </div>
            @endif
        </div>
    @else
        <div class="pk-card p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
            <h3 class="text-lg font-semibold text-slate-700 mb-2">No Merchant Selected</h3>
            <p class="text-sm text-slate-500">Please select a merchant from the dropdown above to generate their QR code</p>
        </div>
    @endif
</div>

<style>
    @media print {
        body * {
            visibility: hidden;
        }
        .pk-card:has(h2:contains("QR Code")), .pk-card:has(h2:contains("QR Code")) * {
            visibility: visible;
        }
        .pk-card:has(h2:contains("QR Code")) {
            position: absolute;
            left: 0;
            top: 0;
        }
    }
</style>
</div>
