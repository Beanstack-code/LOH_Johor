<?php

use App\Models\Donation;
use App\Models\FundraisingCampaign;
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
    public string $successMessage = '';
    public string $toastType = 'success';

    public ?int $campaignId = null;
    public string $campaign_name = '';
    public string $campaign_name_ms = '';
    public string $campaign_name_zh = '';
    public string $organization_name = '';
    public string $organization_name_ms = '';
    public string $organization_name_zh = '';
    public string $description = '';
    public string $description_ms = '';
    public string $description_zh = '';
    public string $sub_description = '';
    public string $sub_description_ms = '';
    public string $sub_description_zh = '';
    public string $impact = '';
    public string $impact_ms = '';
    public string $impact_zh = '';
    public string $how_it_helps = '';
    public string $how_it_helps_ms = '';
    public string $how_it_helps_zh = '';
    public float $target_amount = 0;
    public float $current_collected_amount = 0;
    public string $starts_at = '';
    public string $ends_at = '';
    public string $status = 'active';

    // Image upload
    public ?string $existingCoverImage = null;
    public $coverImageFile = null;

    // Donations modal state
    public bool $showDonationsModal = false;
    public ?int $viewingDonationsForCampaign = null;
    public string $viewingDonationsCampaignName = '';

    public function with(): array
    {
        $allowed = ['campaign_name', 'target_amount', 'current_collected_amount', 'starts_at', 'ends_at', 'status', 'created_at'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'created_at';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        $donations = collect();
        if ($this->showDonationsModal) {
            $donationsQuery = Donation::query()->with('campaign')->latest('donated_at');
            if ($this->viewingDonationsForCampaign) {
                $donationsQuery->where('fundraising_campaign_id', $this->viewingDonationsForCampaign);
            }
            $donations = $donationsQuery->get();
        }

        return [
            'campaigns' => FundraisingCampaign::query()
                ->when($this->search !== '', fn ($q) => $q->where('campaign_name', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->orderBy($sortField, $sortDirection)
                ->paginate(8),
            'donations' => $donations,
            'viewingCampaign' => $this->viewingDonationsForCampaign ? FundraisingCampaign::find($this->viewingDonationsForCampaign) : null,
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
        $allowed = ['campaign_name', 'target_amount', 'current_collected_amount', 'starts_at', 'ends_at', 'status', 'created_at'];
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
        $this->successMessage = __($message);
        $this->toastType = $type;
        $this->js("
            window.dispatchEvent(new CustomEvent('pk-campaign-toast', { detail: { message: " . json_encode($message) . ", type: " . json_encode($type) . " } }));
        ");
    }

    public function saveCampaign(): void
    {
        $isNew = ! $this->campaignId;
        abort_unless(auth()->user()->can($isNew ? 'fundraising.create' : 'fundraising.edit'), 403);

        $rules = [
            'campaign_name' => ['required', 'string'],
            'campaign_name_ms' => ['required', 'string'],
            'campaign_name_zh' => ['required', 'string'],
            'organization_name' => ['required', 'string'],
            'organization_name_ms' => ['required', 'string'],
            'organization_name_zh' => ['required', 'string'],
            'description' => ['required', 'string'],
            'description_ms' => ['required', 'string'],
            'description_zh' => ['required', 'string'],
            'sub_description' => ['nullable', 'string'],
            'sub_description_ms' => ['nullable', 'string'],
            'sub_description_zh' => ['nullable', 'string'],
            'impact' => ['nullable', 'string'],
            'impact_ms' => ['nullable', 'string'],
            'impact_zh' => ['nullable', 'string'],
            'how_it_helps' => ['nullable', 'string'],
            'how_it_helps_ms' => ['nullable', 'string'],
            'how_it_helps_zh' => ['nullable', 'string'],
            'target_amount' => ['required', 'numeric', 'min:0'],
            'current_collected_amount' => ['required', 'numeric', 'min:0'],
        ];

        // Cover image is required for new campaigns, optional for updates if one already exists
        if (!$this->campaignId || (!$this->existingCoverImage && !$this->coverImageFile)) {
            $rules['coverImageFile'] = ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];
        } else {
            $rules['coverImageFile'] = ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'];
        }

        $this->validate($rules);

        // Convert "How It Helps" textarea (one bullet per line) to JSON array
        $howItHelpsArray = array_values(array_filter(
            array_map('trim', explode("\n", $this->how_it_helps))
        ));

        $howItHelpsMsArray = array_values(array_filter(
            array_map('trim', explode("\n", $this->how_it_helps_ms))
        ));

        $howItHelpsZhArray = array_values(array_filter(
            array_map('trim', explode("\n", $this->how_it_helps_zh))
        ));

        $payload = [
            'campaign_name'              => $this->campaign_name,
            'campaign_name_ms'           => $this->campaign_name_ms,
            'campaign_name_zh'           => $this->campaign_name_zh,
            'organization_name'          => $this->organization_name,
            'organization_name_ms'      => $this->organization_name_ms,
            'organization_name_zh'      => $this->organization_name_zh,
            'description'                => $this->description,
            'description_ms'             => $this->description_ms,
            'description_zh'             => $this->description_zh,
            'sub_description'           => $this->sub_description,
            'sub_description_ms'        => $this->sub_description_ms,
            'sub_description_zh'        => $this->sub_description_zh,
            'impact'                     => $this->impact,
            'impact_ms'                  => $this->impact_ms,
            'impact_zh'                  => $this->impact_zh,
            'how_it_helps'               => $howItHelpsArray,
            'how_it_helps_ms'            => $howItHelpsMsArray,
            'how_it_helps_zh'            => $howItHelpsZhArray,
            'target_amount'              => $this->target_amount,
            'current_collected_amount'   => $this->current_collected_amount,
            'starts_at'                  => $this->starts_at ?: null,
            'ends_at'                    => $this->ends_at ?: null,
            'status'                     => $this->status,
        ];

        if ($this->coverImageFile) {
            if ($this->existingCoverImage) {
                Storage::disk('public')->delete($this->existingCoverImage);
            }
            $payload['cover_image'] = $this->coverImageFile->store('fundraising', 'public');
        }

        $isNew = ! $this->campaignId;
        $campaign = FundraisingCampaign::updateOrCreate(['id' => $this->campaignId], $payload);
        $this->showToast($isNew ? 'Campaign created successfully.' : 'Campaign updated successfully.');

        if ($isNew) {
            $this->resetForm();
        } else {
            $this->existingCoverImage = $campaign->cover_image;
            $this->coverImageFile = null;
        }
    }

    public function edit(int $id): void
    {
        $campaign = FundraisingCampaign::findOrFail($id);
        $this->campaignId = $campaign->id;
        $this->campaign_name = $campaign->campaign_name;
        $this->campaign_name_ms = (string) ($campaign->campaign_name_ms ?? '');
        $this->campaign_name_zh = (string) ($campaign->campaign_name_zh ?? '');
        $this->organization_name = (string) $campaign->organization_name;
        $this->organization_name_ms = (string) ($campaign->organization_name_ms ?? '');
        $this->organization_name_zh = (string) ($campaign->organization_name_zh ?? '');
        $this->description = (string) $campaign->description;
        $this->description_ms = (string) ($campaign->description_ms ?? '');
        $this->description_zh = (string) ($campaign->description_zh ?? '');
        $this->sub_description = (string) ($campaign->sub_description ?? '');
        $this->sub_description_ms = (string) ($campaign->sub_description_ms ?? '');
        $this->sub_description_zh = (string) ($campaign->sub_description_zh ?? '');
        $this->impact = (string) $campaign->impact;
        $this->impact_ms = (string) ($campaign->impact_ms ?? '');
        $this->impact_zh = (string) ($campaign->impact_zh ?? '');
        // Convert JSON array back to one-bullet-per-line for the textarea
        $this->how_it_helps = is_array($campaign->how_it_helps)
            ? implode("\n", $campaign->how_it_helps)
            : (string) $campaign->how_it_helps;

        $this->how_it_helps_ms = is_array($campaign->how_it_helps_ms)
            ? implode("\n", $campaign->how_it_helps_ms)
            : (string) ($campaign->how_it_helps_ms ?? '');

        $this->how_it_helps_zh = is_array($campaign->how_it_helps_zh)
            ? implode("\n", $campaign->how_it_helps_zh)
            : (string) ($campaign->how_it_helps_zh ?? '');
        $this->target_amount = (float) $campaign->target_amount;
        $this->current_collected_amount = (float) $campaign->current_collected_amount;
        $this->starts_at = optional($campaign->starts_at)->format('Y-m-d\TH:i') ?? '';
        $this->ends_at = optional($campaign->ends_at)->format('Y-m-d\TH:i') ?? '';
        $this->status = $campaign->status;
        $this->existingCoverImage = $campaign->cover_image;
        $this->coverImageFile = null;
        $this->showToast('Campaign details loaded.');
        $this->js("
            window.dispatchEvent(new CustomEvent('campaign-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function quickActivate(int $id): void
    {
        abort_unless(auth()->user()->can('fundraising.activate'), 403);

        FundraisingCampaign::whereKey($id)->update(['status' => 'active']);
        $this->showToast('Campaign activated.');
    }

    public function quickDeactivate(int $id): void
    {
        abort_unless(auth()->user()->can('fundraising.activate'), 403);

        FundraisingCampaign::whereKey($id)->update(['status' => 'ended']);
        $this->showToast('Campaign deactivated.');
    }

    public function openAllDonationsModal(): void
    {
        $this->showDonationsModal = true;
        $this->viewingDonationsForCampaign = null;
        $this->viewingDonationsCampaignName = '';
        $this->showToast('Viewing all donation logs.');
    }

    public function viewDonations(int $campaignId): void
    {
        $campaign = FundraisingCampaign::query()->find($campaignId);
        if (! $campaign) {
            return;
        }

        $this->showDonationsModal = true;
        $this->viewingDonationsForCampaign = $campaignId;
        $this->viewingDonationsCampaignName = (string) $campaign->campaign_name;
        $this->showToast('Viewing donations for selected campaign.');
    }

    public function viewAllDonations(): void
    {
        $this->showDonationsModal = true;
        $this->viewingDonationsForCampaign = null;
        $this->viewingDonationsCampaignName = '';
        $this->showToast('Viewing all donations.');
    }

    public function closeDonationsModal(): void
    {
        $this->showDonationsModal = false;
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->showToast('Ready to create a new campaign.');
        $this->js("window.dispatchEvent(new CustomEvent('campaign-form-open'));");
    }

    private function resetForm(): void
    {
        $this->reset([
            'campaignId',
            'campaign_name',
            'campaign_name_ms',
            'campaign_name_zh',
            'organization_name',
            'organization_name_ms',
            'organization_name_zh',
            'description',
            'description_ms',
            'description_zh',
            'sub_description',
            'sub_description_ms',
            'sub_description_zh',
            'impact',
            'impact_ms',
            'impact_zh',
            'how_it_helps',
            'how_it_helps_ms',
            'how_it_helps_zh',
            'target_amount',
            'current_collected_amount',
            'starts_at',
            'ends_at',
            'existingCoverImage',
            'coverImageFile',
        ]);
        $this->status = 'active';
    }

    public function exportDonors()
    {
        abort_unless(auth()->user()->can('fundraising.edit'), 403);

        $query = Donation::query()->with('campaign')->latest('donated_at');
        
        if ($this->viewingDonationsForCampaign) {
            $query->where('fundraising_campaign_id', $this->viewingDonationsForCampaign);
        }
        
        $rows = $query->get();

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Campaign', 'Donor Name', 'Donor Email', 'Amount', 'Status', 'Gateway Order', 'Gateway Payment', 'Donated At']);
            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->campaign?->campaign_name,
                    $row->member_name,
                    $row->member_email,
                    $row->amount,
                    $row->payment_status ?? 'captured',
                    $row->gateway_order_id,
                    $row->gateway_payment_id,
                    optional($row->donated_at)->toDateTimeString(),
                ]);
            }
            fclose($handle);
        }, 'donor-list.csv');
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="pk-page-title">{{ __('Fundraising Campaign Management') }}</h1>
            <p class="pk-subtitle">{{ __('Campaign setup, progress tracking, and donor log export.') }}</p>
        </div>
        @can('fundraising.edit')
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('fundraising-donation-logs-open'))"
            class="pk-btn-primary"
        >
            Donation Logs
        </button>
        @endcan
    </div>
</x-slot>

<div class="space-y-6" x-data @fundraising-donation-logs-open.window="$wire.openAllDonationsModal()">
    {{-- Inline toast --}}
    <div
        x-data="{ show: false, message: '', type: 'success' }"
        @pk-campaign-toast.window="message = $event.detail.message; type = $event.detail.type; show = true; setTimeout(() => show = false, 3500)"
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
        <input type="text" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by campaign name...') }}" class="pk-input w-full sm:max-w-sm">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">{{ __('All Status') }}</option>
            <option value="draft">{{ __('Draft') }}</option>
            <option value="active">{{ __('Active') }}</option>
            <option value="ended">{{ __('Ended') }}</option>
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
        @campaign-form-open.window="open = true"
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
            <h2 class="text-base font-semibold text-slate-800">{{ $campaignId ? 'Edit Campaign' : 'Create Campaign' }}</h2>
            <div class="flex items-center gap-2">
                @if($campaignId)
                    @can('fundraising.create')
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

            <p class="mt-2 text-xs text-slate-500">Use the tabs for English, Malay (MS), and Chinese (ZH). Campaign name, organization name, and description must be filled in all three languages. Sections marked “optional” may be left blank.</p>

            <form wire:submit="saveCampaign" class="mt-4 space-y-3">
                <x-admin.form.group label="Campaign Name *">
                        <div x-show="activeLang === 'en'" x-cloak>
                            <input class="{{ $errors->has('campaign_name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="campaign_name">
                            @error('campaign_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="activeLang === 'ms'" x-cloak>
                            <input class="{{ $errors->has('campaign_name_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="campaign_name_ms" placeholder="{{ __('Malay campaign name') }}">
                            @error('campaign_name_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="activeLang === 'zh'" x-cloak>
                            <input class="{{ $errors->has('campaign_name_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="campaign_name_zh" placeholder="{{ __('Chinese campaign name') }}">
                            @error('campaign_name_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                </x-admin.form.group>

                <x-admin.form.group label="Organization Name *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <input class="{{ $errors->has('organization_name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="organization_name" placeholder="e.g., Red Crescent, UNICEF">
                        @error('organization_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <input class="{{ $errors->has('organization_name_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="organization_name_ms" placeholder="{{ __('Organization (MS)') }}">
                        @error('organization_name_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <input class="{{ $errors->has('organization_name_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="organization_name_zh" placeholder="{{ __('Organization (ZH)') }}">
                        @error('organization_name_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </x-admin.form.group>

                <x-admin.form.group label="Description *">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <textarea class="{{ $errors->has('description') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="description" rows="6" placeholder="{{ __('Enter campaign description. Use line breaks for paragraphs.') }}"></textarea>
                        @error('description')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <textarea class="{{ $errors->has('description_ms') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="description_ms" rows="6" placeholder="{{ __('Malay description') }}"></textarea>
                        @error('description_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <textarea class="{{ $errors->has('description_zh') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="description_zh" rows="6" placeholder="{{ __('Chinese description') }}"></textarea>
                        @error('description_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Use Enter key to create new paragraphs. Content will preserve line breaks.') }}</p>
                </x-admin.form.group>

                <x-admin.form.group label="Sub Description (optional)">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <input class="pk-input" wire:model="sub_description" placeholder="{{ __('Short sub-description') }}">
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <input class="pk-input" wire:model="sub_description_ms" placeholder="{{ __('Sub-description (MS)') }}">
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <input class="pk-input" wire:model="sub_description_zh" placeholder="{{ __('Sub-description (ZH)') }}">
                    </div>
                </x-admin.form.group>

                <x-admin.form.group label="Impact Statement (optional)">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <input class="pk-input" wire:model="impact" placeholder="{{ __('e.g., RM10 funds school supplies for 1 child') }}">
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <input class="pk-input" wire:model="impact_ms" placeholder="{{ __('Impact (MS)') }}">
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <input class="pk-input" wire:model="impact_zh" placeholder="{{ __('Impact (ZH)') }}">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('One-line summary shown on the donation card (e.g., RM50 covers basic medicine for 1 week)') }}</p>
                </x-admin.form.group>

                <x-admin.form.group label="How It Helps (optional)">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <textarea class="pk-input" wire:model="how_it_helps" rows="4" placeholder="{{ __('Enter each bullet point on a new line') }}"></textarea>
                    </div>
                    <div x-show="activeLang === 'ms'" x-cloak>
                        <textarea class="pk-input" wire:model="how_it_helps_ms" rows="4" placeholder="MS bullets"></textarea>
                    </div>
                    <div x-show="activeLang === 'zh'" x-cloak>
                        <textarea class="pk-input" wire:model="how_it_helps_zh" rows="4" placeholder="{{ __('ZH bullets') }}"></textarea>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('One bullet point per line. These appear as the "How your donation helps" list in the Flutter app.') }}</p>
                </x-admin.form.group>

                {{-- Cover Image upload --}}
                <x-admin.form.group label="Cover Image *">
                    @if($existingCoverImage)
                        <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                            <img src="{{ Storage::url($existingCoverImage) }}" alt="Current cover" class="h-32 w-full object-cover">
                            <p class="px-2 py-1 text-xs text-slate-500">{{ __('Current cover image') }}</p>
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
                            :class="isDragging ? 'border-[#9a7300] bg-amber-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                            class="cursor-pointer rounded-xl border-2 border-dashed p-5 text-center transition"
                        >
                            <svg class="mx-auto mb-2 h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                            <p class="text-xs font-medium text-slate-600">{{ __('Drag & drop or') }} <span class="text-[#9a7300]">{{ __('click to browse') }}</span></p>
                            <p class="mt-0.5 text-xs text-slate-400">{{ __('JPG, PNG, GIF, WEBP — max 10 MB') }}</p>
                            <input x-ref="coverInput" type="file" wire:model="coverImageFile" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" @change="handleChange($event)">
                        </div>
                        @error('coverImageFile')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <div x-show="preview !== null" x-cloak class="mt-2 overflow-hidden rounded-xl border border-slate-200">
                            <img :src="preview" class="h-32 w-full object-cover">
                            <p class="px-2 py-1 text-xs text-slate-500">{{ __('New cover preview') }}</p>
                        </div>
                    </div>
                </x-admin.form.group>

                <x-admin.form.group label="Target Amount *">
                    <input type="number" step="0.01" class="{{ $errors->has('target_amount') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="target_amount">
                    @error('target_amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>

                <x-admin.form.group label="Current Collected *">
                    <input type="number" step="0.01" class="{{ $errors->has('current_collected_amount') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="current_collected_amount">
                    @error('current_collected_amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-admin.form.group>

                <x-admin.form.group label="Start Date (optional)">
                    <input type="datetime-local" class="pk-input" wire:model="starts_at">
                </x-admin.form.group>

                <x-admin.form.group label="End Date (optional)">
                    <input type="datetime-local" class="pk-input" wire:model="ends_at">
                </x-admin.form.group>

                <x-admin.form.group label="Status">
                    <select class="pk-input" wire:model="status">
                        <option value="draft">{{ __('Draft') }}</option>
                        <option value="active">{{ __('Active') }}</option>
                        <option value="ended">{{ __('Ended') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ __('The member app only lists') }} <strong>{{ __('Active') }}</strong> {{ __('campaigns. Empty start/end dates do not hide a campaign—set status to') }} <strong>{{ __('Ended') }}</strong> {{ __('(or use End on the list) to remove it from the app.') }}</p>
                </x-admin.form.group>

                @if($campaignId)
                    @can('fundraising.edit')
                    <button class="pk-btn-primary w-full">{{ __('Update Campaign') }}</button>
                    @endcan
                @else
                    @can('fundraising.create')
                    <button class="pk-btn-primary w-full">{{ __('Save Campaign') }}</button>
                    @endcan
                @endif
            </form>
        </div>
    </section>

    <section class="space-y-6 xl:col-span-2">
        {{-- Mobile card view --}}
        <div class="pk-mobile-only space-y-3">
            @foreach($campaigns as $campaign)
                <article class="pk-card p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-3">
                            @if($campaign->cover_image)
                                <img src="{{ Storage::url($campaign->cover_image) }}" class="h-10 w-10 rounded-xl object-cover border border-slate-200 shrink-0">
                            @endif
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $campaign->campaign_name }}</p>
                                @if($campaign->organization_name)
                                    <p class="text-xs text-slate-500">{{ $campaign->organization_name }}</p>
                                @endif
                            </div>
                        </div>
                        <x-admin.status-badge :status="$campaign->status" />
                    </div>
                    @if($campaign->impact)
                        <p class="mt-2 text-xs text-slate-600 italic">{{ $campaign->impact }}</p>
                    @endif
                    <dl class="mt-3 space-y-1 text-xs text-slate-600">
                        <div class="flex justify-between gap-3"><dt>{{ __('Target') }}</dt><dd>RM {{ number_format((float) $campaign->target_amount, 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Collected') }}</dt><dd>RM {{ number_format((float) $campaign->current_collected_amount, 2) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('Start') }}</dt><dd>{{ optional($campaign->starts_at)->format('d M Y') ?: '-' }}</dd></div>
                        <div class="flex justify-between gap-3"><dt>{{ __('End') }}</dt><dd>{{ optional($campaign->ends_at)->format('d M Y') ?: '-' }}</dd></div>
                    </dl>
                    <div class="mt-3 flex gap-2">
                        <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" wire:click="edit({{ $campaign->id }})">{{ __('View') }}</button>
                        @can('fundraising.edit')
                        <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" wire:click="edit({{ $campaign->id }})">{{ __('Edit') }}</button>
                        @endcan
                        <button class="pk-btn-secondary px-2 py-2 text-xs pk-btn-accent" wire:click="viewDonations({{ $campaign->id }})">{{ __('Donations') }}</button>
                        @can('fundraising.activate')
                        @if($campaign->status === 'active')
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-rose-600" wire:click="quickDeactivate({{ $campaign->id }})">{{ __('End') }}</button>
                        @else
                            <button class="pk-btn-secondary px-2 py-2 text-xs text-emerald-600" wire:click="quickActivate({{ $campaign->id }})">{{ __('Activate') }}</button>
                        @endif
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Desktop table view --}}
        <div class="pk-desktop-only" style="margin-top: 0px;">
            <x-admin.table.wrapper>
                <table class="pk-table w-full">
                    <thead>
                        <tr>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('campaign_name')" class="inline-flex items-center gap-1">{{ __('Campaign') }} <span>{{ $this->sortIcon('campaign_name') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('target_amount')" class="inline-flex items-center gap-1">{{ __('Target') }} <span>{{ $this->sortIcon('target_amount') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('current_collected_amount')" class="inline-flex items-center gap-1">{{ __('Collected') }} <span>{{ $this->sortIcon('current_collected_amount') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('starts_at')" class="inline-flex items-center gap-1">{{ __('Start') }} <span>{{ $this->sortIcon('starts_at') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('ends_at')" class="inline-flex items-center gap-1">{{ __('End') }} <span>{{ $this->sortIcon('ends_at') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap">
                                <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">{{ __('Status') }} <span>{{ $this->sortIcon('status') }}</span></button>
                            </th>
                            <th class="whitespace-nowrap text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($campaigns as $campaign)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        @if($campaign->cover_image)
                                            <img src="{{ Storage::url($campaign->cover_image) }}" class="h-9 w-9 rounded-xl object-cover border border-slate-200 shrink-0">
                                        @endif
                                        <div>
                                            <p class="font-semibold text-slate-700">{{ $campaign->campaign_name }}</p>
                                            @if($campaign->organization_name)
                                                <p class="text-xs text-slate-500">{{ $campaign->organization_name }}</p>
                                            @endif
                                            @if($campaign->impact)
                                                <p class="text-xs text-slate-600 italic mt-0.5">{{ $campaign->impact }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap">RM {{ number_format((float) $campaign->target_amount, 2) }}</td>
                                <td class="whitespace-nowrap">RM {{ number_format((float) $campaign->current_collected_amount, 2) }}</td>
                                <td class="whitespace-nowrap">{{ optional($campaign->starts_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                <td class="whitespace-nowrap">{{ optional($campaign->ends_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                <td class="whitespace-nowrap"><x-admin.status-badge :status="$campaign->status" /></td>
                                <td class="whitespace-nowrap text-right space-x-1">
                                    <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="edit({{ $campaign->id }})">{{ __('View') }}</button>
                                    @can('fundraising.edit')
                                    <button class="pk-btn-secondary px-3 py-2 text-xs" wire:click="edit({{ $campaign->id }})">{{ __('Edit') }}</button>
                                    @endcan
                                    <button class="pk-btn-secondary px-3 py-2 text-xs pk-btn-accent" wire:click="viewDonations({{ $campaign->id }})">{{ __('Donations') }}</button>
                                    @can('fundraising.activate')
                                    @if($campaign->status === 'active')
                                        <button class="pk-btn-secondary px-3 py-2 text-xs text-rose-600" wire:click="quickDeactivate({{ $campaign->id }})">{{ __('End') }}</button>
                                    @else
                                        <button class="pk-btn-secondary px-3 py-2 text-xs text-emerald-600" wire:click="quickActivate({{ $campaign->id }})">{{ __('Activate') }}</button>
                                    @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-admin.table.wrapper>
        </div>
        <div>{{ $campaigns->links() }}</div>

    </section>
</div>

    {{-- Donation logs modal --}}
    <div
        x-data="{ open: @entangle('showDonationsModal').live }"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[130] flex items-end justify-center sm:items-center sm:p-4"
        style="isolation: isolate;"
    >
        <div
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
            wire:click="closeDonationsModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0"
        ></div>

        <div
            class="relative flex w-full flex-col bg-white shadow-2xl sm:max-w-5xl sm:rounded-2xl"
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
                    <h2 class="text-base font-bold text-slate-800">{{ __('Donation logs') }}</h2>
                    @if($viewingDonationsForCampaign && $viewingDonationsCampaignName !== '')
                        <p class="mt-0.5 text-xs text-slate-500">Campaign: {{ $viewingDonationsCampaignName }}</p>
                    @else
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('All campaigns') }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if($viewingDonationsForCampaign)
                        <button type="button" class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="viewAllDonations">{{ __('View all') }}</button>
                    @endif
                    <button
                        type="button"
                        wire:click="closeDonationsModal"
                        class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        aria-label="{{ __('Close') }}"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-4">
                <div class="overflow-auto rounded-xl border border-slate-200">
                    <table class="min-w-full text-left text-xs">
                        <thead class="sticky top-0 bg-slate-50 text-slate-600">
                            <tr>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Donor') }}</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Campaign') }}</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Amount') }}</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Status') }}</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Order ID') }}</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Payment ID') }}</th>
                                <th class="whitespace-nowrap px-3 py-2 font-semibold">{{ __('Donated at') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($donations as $donation)
                                <tr class="align-top">
                                    <td class="px-3 py-2">
                                        <p class="font-medium text-slate-800">{{ $donation->member_name ?: '-' }}</p>
                                        <p class="text-[11px] text-slate-500">{{ $donation->member_email ?: '-' }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-slate-600">{{ $donation->campaign?->campaign_name ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 font-semibold text-emerald-600">RM {{ number_format((float) $donation->amount, 2) }}</td>
                                    <td class="whitespace-nowrap px-3 py-2"><x-admin.status-badge :status="($donation->payment_status ?? 'captured')" class="text-[11px]" /></td>
                                    <td class="max-w-[120px] truncate px-3 py-2 font-mono text-[11px] text-slate-600" title="{{ $donation->gateway_order_id }}">{{ $donation->gateway_order_id ?: '-' }}</td>
                                    <td class="max-w-[120px] truncate px-3 py-2 font-mono text-[11px] text-slate-600" title="{{ $donation->gateway_payment_id }}">{{ $donation->gateway_payment_id ?: '-' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-slate-500">{{ optional($donation->donated_at)->format('d M Y, h:i A') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">{{ __('No donations yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
