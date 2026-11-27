<?php

use App\Models\Member;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination;

    public ?int $memberId = null;
    public ?Member $selectedMember = null;
    public string $memberSearch = '';

    public function selectMember(int $id): void
    {
        $this->memberId = $id;
        $this->selectedMember = Member::find($id);
        $this->memberSearch = '';
        $this->resetPage();
    }

    public function with(): array
    {
        $qrToken = null;

        if ($this->selectedMember) {
            $hmac = hash_hmac('sha256', "PKPMS:MEMBER:{$this->selectedMember->id}", config('app.key'));
            $qrToken = "PKPMS:MEMBER:{$this->selectedMember->id}:{$hmac}";
        }

        $membersQuery = Member::orderBy('full_name');
        if ($this->memberSearch !== '') {
            $membersQuery->where(function ($q) {
                $q->where('full_name', 'like', '%' . $this->memberSearch . '%')
                  ->orWhere('email', 'like', '%' . $this->memberSearch . '%')
                  ->orWhere('phone', 'like', '%' . $this->memberSearch . '%');
            });
        }

        return [
            'members' => $membersQuery->limit(50)->get(),
            'qrToken' => $qrToken,
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <div>
            <h1 class="pk-page-title">{{ __('Member QR Codes') }}</h1>
            <p class="pk-subtitle">{{ __('Generate and display QR codes for members') }}</p>
        </div>
    </x-slot>

    <div class="space-y-6">
    <!-- Member Selection -->
    <div class="pk-card p-5">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Select Member') }}</h2>
        <div class="max-w-md" x-data="{ open: false, search: '' }">
            <label class="pk-label">{{ __('Choose a member') }}</label>
            <div class="relative">
                <button @click="open = !open" type="button" class="pk-input text-left flex items-center justify-between w-full">
                    <span class="{{ $selectedMember ? 'text-slate-700' : 'text-slate-400' }}">
                        {{ $selectedMember ? $selectedMember->full_name . ' (' . $selectedMember->email . ')' : __('-- Select Member --') }}
                    </span>
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" @click.away="open = false" x-cloak class="absolute z-10 mt-1 w-full bg-white border border-slate-200 rounded-xl shadow-lg max-h-80 overflow-hidden">
                    <div class="p-2 border-b border-slate-200">
                        <input type="text" x-model="search" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#9a7300] focus:border-[#9a7300]" placeholder="{{ __('Search members...') }}">
                    </div>
                    <div class="overflow-y-auto max-h-64">
                        @foreach($members as $member)
                            <button type="button" 
                                    wire:click="selectMember({{ $member->id }})" 
                                    @click="open = false"
                                    x-show="search === '' || '{{ strtolower($member->full_name . ' ' . $member->email) }}'.includes(search.toLowerCase())"
                                    class="w-full text-left px-4 py-2.5 hover:bg-slate-50 transition text-sm border-b border-slate-100 last:border-b-0">
                                <div class="font-medium text-slate-700">{{ $member->full_name }}</div>
                                <div class="text-xs text-slate-500">{{ $member->email }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($selectedMember)
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Member Information -->
            <div class="pk-card p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Member Information') }}</h2>
                <div class="space-y-3">
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Membership ID') }}</p>
                        <p class="text-sm font-bold text-[#9a7300]">{{ $selectedMember->membership_id }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Full Name') }}</p>
                        <p class="text-sm font-semibold text-slate-800">{{ $selectedMember->full_name }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Email') }}</p>
                        <p class="text-sm text-slate-700">{{ $selectedMember->email }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Phone') }}</p>
                        <p class="text-sm text-slate-700">{{ $selectedMember->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">{{ __('Status') }}</p>
                        @if($selectedMember->status === 'active')
                            <span class="pk-badge-success">{{ __('Active') }}</span>
                        @else
                            <span class="pk-badge-warning">{{ __(ucfirst($selectedMember->status)) }}</span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- QR Code Display -->
            <div class="pk-card p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('QR Code') }}</h2>
                <div class="flex flex-col items-center justify-center space-y-4">
                    <div class="bg-white p-6 rounded-xl border-2 border-slate-200">
                        {!! QrCode::size(250)->generate($qrToken) !!}
                    </div>
                    <p class="text-xs text-slate-500 text-center">{{ __('Scan this QR code to view your member details') }}</p>
                    
                    <!-- Action Buttons -->
                    <div class="flex gap-3 mt-4">
                        <button onclick="window.print()" class="pk-btn-secondary">
                            <svg class="w-4 h-4 mr-2 inline-block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Print QR Code
                        </button>
                        <a href="data:image/svg+xml;base64,{{ base64_encode(QrCode::size(250)->generate($qrToken)) }}" 
                           download="member-{{ $selectedMember->id }}-qr.svg" 
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
            <h2 class="text-base font-semibold text-slate-800 mb-2">{{ __('QR Code Data') }}</h2>
            <p class="text-sm text-slate-600 mb-3">{{ __('This QR code contains the following information:') }}</p>
            <div class="bg-slate-50 rounded-lg p-4 font-mono text-sm text-slate-700 break-all">
                {{ $qrToken }}
            </div>
            <p class="text-xs text-slate-500 mt-2">This QR code is signed with the application key. It is permanent and can be scanned by the 柔佛州羅氏公会.</p>
        </div>
    @else
        <div class="pk-card p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-slate-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
            </svg>
            <h3 class="text-lg font-semibold text-slate-700 mb-2">{{ __('No Member Selected') }}</h3>
            <p class="text-sm text-slate-500">{{ __('Please select a member from the dropdown above to generate their QR code') }}</p>
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
