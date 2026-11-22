<?php

use App\Models\Merchant;
use App\Models\MerchantCategory;
use App\Models\MerchantGallery;
use App\Models\PaymentTransaction;
use App\Models\PointTransaction;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\AppUserWelcomeNotification;
use App\Services\AppUserPasswordService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component {
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $statusFilter = '';
    public string $subscriptionFilter = '';
    public string $joinedYearFilter = '';
    public string $cityFilter = '';
    public string $countryFilter = '';
    public string $sortField = 'company_name';
    public string $sortDirection = 'asc';
    public string $successMessage = '';
    public string $toastType = 'success';

    public ?int $merchantId = null;
    public string $company_name = '';
    public string $company_name_ms = '';
    public string $company_name_zh = '';
    public string $company_email = '';
    public array $categories = [];
    public string $phone = '';
    public string $whatsapp_number = '';
    public string $registration_number = '';
    public string $street_address = '';
    public string $city = '';
    public string $postcode = '';
    public string $country = 'Malaysia';
    public string $approval_notes = '';
    public string $short_description = '';
    public string $short_description_ms = '';
    public string $short_description_zh = '';
    public string $about_description = '';
    public string $about_description_ms = '';
    public string $about_description_zh = '';
    public ?string $existingCoverImage = null;
    public ?string $existingSsmDocument = null;
    public $coverImage = null;
    public $ssmDocument = null;
    public array $galleryImages = [];
    public string $galleryCaption = '';
    public array $benefits = [];
    public string $newBenefit = '';
    public ?int $selectedPlanId = null;
    public string $subscriptionNotes = '';
    public int $extensionDays = 30;
    public ?string $lastCreatedPassword = null;
    public ?string $lastResetTempPassword = null;

    public function with(): array
    {
        $allowed = ['company_name', 'status', 'subscription_state', 'subscription_ends_at'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'company_name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'merchants' => Merchant::query()
                ->with('activeSubscription.plan')
                ->when($this->search !== '', fn ($q) => $q->where('company_name', 'like', '%'.$this->search.'%')
                    ->orWhere('company_email', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->subscriptionFilter !== '', fn ($q) => $q->where('subscription_state', $this->subscriptionFilter))
                ->when($this->joinedYearFilter !== '', fn ($q) => $q->whereYear('created_at', (int) $this->joinedYearFilter))
                ->when($this->cityFilter !== '', fn ($q) => $q->where('city', $this->cityFilter))
                ->when($this->countryFilter !== '', fn ($q) => $q->where('country', $this->countryFilter))
                ->orderBy($sortField, $sortDirection)
                ->paginate(10),
            'plans' => SubscriptionPlan::query()
                ->whereIn('audience', ['merchant', 'both'])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(),
            'merchantCategories' => MerchantCategory::active()->orderBy('name')->get(),
            'savedGalleryImages' => $this->merchantId
                ? MerchantGallery::where('merchant_id', $this->merchantId)->orderByDesc('created_at')->get()
                : collect(),
            'joinedYears' => Merchant::query()
                ->selectRaw('YEAR(created_at) as joined_year, COUNT(*) as total')
                ->whereNotNull('created_at')
                ->groupBy('joined_year')
                ->orderByDesc('joined_year')
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->joined_year => (int) $row->total]),
            'merchantCities' => Merchant::query()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'merchantCountries' => Merchant::query()
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->distinct()
                ->orderBy('country')
                ->pluck('country'),
            'merchantPointStats' => $this->merchantId
                ? [
                    'total_points_granted' => (int) PointTransaction::where('merchant_id', $this->merchantId)
                        ->where('type', 'allocation')
                        ->sum('points'),
                    'today_granted_points' => (int) PointTransaction::where('merchant_id', $this->merchantId)
                        ->where('type', 'allocation')
                        ->whereDate('occurred_at', now()->toDateString())
                        ->sum('points'),
                    'total_transactions' => (int) PointTransaction::where('merchant_id', $this->merchantId)
                        ->where('type', 'allocation')
                        ->count(),
                    'total_customers' => (int) PointTransaction::where('merchant_id', $this->merchantId)
                        ->where('type', 'allocation')
                        ->distinct('member_id')
                        ->count('member_id'),
                ]
                : null,
            'merchantRecentTransactions' => $this->merchantId
                ? PointTransaction::with('member:id,full_name,email')
                    ->where('merchant_id', $this->merchantId)
                    ->where('type', 'allocation')
                    ->latest('occurred_at')
                    ->limit(5)
                    ->get(['id', 'member_id', 'points', 'occurred_at', 'status', 'reference_number'])
                : collect(),
            'merchantSubscriptionHistory' => $this->merchantId
                ? Subscription::query()
                    ->where('subscribable_type', Merchant::class)
                    ->where('subscribable_id', $this->merchantId)
                    ->with(['plan.descriptions', 'assignedBy:id,name'])
                    ->orderByDesc('starts_at')
                    ->orderByDesc('id')
                    ->get()
                : collect(),
            'merchantSubscriptionPayments' => $this->merchantId
                ? PaymentTransaction::query()
                    ->where('subscribable_type', Merchant::class)
                    ->where('subscribable_id', $this->merchantId)
                    ->where('transaction_type', 'subscription')
                    ->with('plan')
                    ->orderByDesc('created_at')
                    ->limit(50)
                    ->get()
                : collect(),
        ];
    }

    public function sortBy(string $field): void
    {
        $allowed = ['company_name', 'status', 'subscription_state', 'subscription_ends_at'];
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
    public function updatedSubscriptionFilter(): void { $this->resetPage(); }
    public function updatedJoinedYearFilter(): void { $this->resetPage(); }
    public function updatedCityFilter(): void { $this->resetPage(); }
    public function updatedCountryFilter(): void { $this->resetPage(); }

    public function saveMerchant(): void
    {
        $isNew = ! $this->merchantId;
        abort_unless(auth()->user()->can($isNew ? 'merchants.create' : 'merchants.edit'), 403);

        $categoryRules = $isNew ? ['required', 'array', 'min:1'] : ['nullable', 'array'];

        $this->validate([
            'company_name' => ['required', 'string'],
            'company_name_ms' => ['required', 'string', 'max:255'],
            'company_name_zh' => ['required', 'string', 'max:255'],
            'company_email' => ['required', 'email', \Illuminate\Validation\Rule::unique('merchants', 'company_email')->ignore($this->merchantId)],
            'categories' => $categoryRules,
            'categories.*' => ['string'],
            'coverImage' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'ssmDocument' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'short_description_ms' => ['nullable', 'required_with:short_description', 'string', 'max:1000'],
            'short_description_zh' => ['nullable', 'required_with:short_description', 'string', 'max:1000'],
            'about_description' => ['nullable', 'string', 'max:5000'],
            'about_description_ms' => ['nullable', 'required_with:about_description', 'string', 'max:5000'],
            'about_description_zh' => ['nullable', 'required_with:about_description', 'string', 'max:5000'],
            'benefits' => ['array'],
            'benefits.*' => ['required', 'string', 'max:255'],
        ]);

        $payload = [
            'company_name' => $this->company_name,
            'company_name_ms' => $this->company_name_ms,
            'company_name_zh' => $this->company_name_zh,
            'company_email' => $this->company_email,
            'categories' => $this->categories,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'registration_number' => $this->registration_number,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'short_description' => $this->short_description,
            'short_description_ms' => $this->short_description_ms,
            'short_description_zh' => $this->short_description_zh,
            'about_description' => $this->about_description,
            'about_description_ms' => $this->about_description_ms,
            'about_description_zh' => $this->about_description_zh,
            'approval_notes' => $this->approval_notes,
        ];

        if ($this->coverImage) {
            if ($this->existingCoverImage) {
                Storage::disk('public')->delete($this->existingCoverImage);
            }
            $payload['cover_image'] = $this->coverImage->store('merchants/covers', 'public');
        }

        if ($this->ssmDocument) {
            if ($this->existingSsmDocument) {
                Storage::disk('public')->delete($this->existingSsmDocument);
            }
            $payload['ssm_document'] = $this->ssmDocument->store('ssm_documents', 'public');
        }

        if (! $this->merchantId) {
            $payload['status'] = 'pending_subscription';
            $payload['subscription_state'] = 'pending_subscription';
            $generatedPassword = $this->generatePasswordFromEmail($this->company_email);
            $payload['password'] = $generatedPassword;
            $payload['temp_password_issued_at'] = now();
        }

        $isNew = ! $this->merchantId;
        $merchant = Merchant::updateOrCreate(['id' => $this->merchantId], $payload);
        $merchant->benefits()->delete();
        foreach ($this->benefits as $index => $benefit) {
            $value = trim($benefit);
            if ($value === '') {
                continue;
            }

            $merchant->benefits()->create([
                'description' => $value,
                'display_order' => $index,
            ]);
        }
        $this->showToast($isNew ? 'Merchant created successfully.' : 'Merchant updated successfully.');

        if ($isNew) {
            $temporaryPassword = $generatedPassword ?? null;
            try {
                $token = Password::broker('merchants')->createToken($merchant);
                $merchant->notify(new AppUserWelcomeNotification($token, 'merchant'));
            } catch (\Exception) {
                // Welcome email failure should not block the record creation
            }
            $this->resetForm();
            $this->lastCreatedPassword = $temporaryPassword;
        } else {
            $this->lastCreatedPassword = null;
            // Refresh the loaded merchant data (e.g. updated cover image path)
            $this->existingCoverImage = $merchant->cover_image;
            $this->existingSsmDocument = $merchant->ssm_document;
            $this->coverImage = null;
            $this->ssmDocument = null;
        }
    }

    public function editMerchant(int $id): void
    {
        $merchant = Merchant::with(['benefits', 'activeSubscription'])->findOrFail($id);
        $this->merchantId = $id;
        $this->company_name = $merchant->company_name;
        $this->company_name_ms = (string) ($merchant->company_name_ms ?? '');
        $this->company_name_zh = (string) ($merchant->company_name_zh ?? '');
        $this->company_email = $merchant->company_email;
        $this->categories = is_array($merchant->categories) ? $merchant->categories : [];
        $this->phone = (string) $merchant->phone;
        $this->whatsapp_number = (string) $merchant->whatsapp_number;
        $this->registration_number = (string) $merchant->registration_number;
        $this->street_address = (string) $merchant->street_address;
        $this->city = (string) $merchant->city;
        $this->postcode = (string) $merchant->postcode;
        $this->country = $merchant->country ?: 'Malaysia';
        $this->short_description = (string) $merchant->short_description;
        $this->short_description_ms = (string) ($merchant->short_description_ms ?? '');
        $this->short_description_zh = (string) ($merchant->short_description_zh ?? '');
        $this->about_description = (string) ($merchant->about_description ?? '');
        $this->about_description_ms = (string) ($merchant->about_description_ms ?? '');
        $this->about_description_zh = (string) ($merchant->about_description_zh ?? '');
        $this->existingCoverImage = $merchant->cover_image;
        $this->existingSsmDocument = $merchant->ssm_document;
        $this->coverImage = null;
        $this->ssmDocument = null;
        $this->approval_notes = (string) $merchant->approval_notes;
        $this->benefits = $merchant->benefits->pluck('description')->all();
        $this->selectedPlanId = $merchant->activeSubscription?->subscription_plan_id;
        $this->subscriptionNotes = '';
        $this->extensionDays = 30;
        $this->newBenefit = '';
        $this->lastCreatedPassword = $merchant->temp_password_issued_at
            ? $this->generatePasswordFromEmail($merchant->company_email)
            : null;
        $this->showToast('Merchant details loaded.');
        $this->js("
            window.dispatchEvent(new CustomEvent('merchant-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function approve(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.approve'), 403);

        $merchant = Merchant::findOrFail($id);
        if ($merchant->requiresActivation()) {
            $merchant->status = 'pending_subscription';
            $merchant->save();
            $this->showToast('Merchant approved — pending subscription assignment.');
            return;
        }
        $merchant->status = 'active';
        $merchant->save();
        $this->showToast('Merchant approved successfully.');
    }

    public function reject(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.reject'), 403);

        Merchant::whereKey($id)->update(['status' => 'rejected', 'approval_notes' => $this->approval_notes ?: 'Rejected by admin']);
        $this->showToast('Merchant rejected.');
    }

    public function suspend(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.suspend'), 403);

        Merchant::whereKey($id)->update(['status' => 'suspended']);
        $this->showToast('Merchant suspended.');
    }

    public function activate(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.activate'), 403);

        $merchant = Merchant::findOrFail($id);
        if ($merchant->requiresActivation()) {
            $merchant->status = 'pending_subscription';
            $merchant->save();
            $this->showToast('Merchant activated — pending subscription assignment.');
            return;
        }

        $merchant->status = 'active';
        $merchant->save();
        $this->showToast('Merchant activated successfully.');
    }

    public function resetPassword(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.edit'), 403);
        $merchant = Merchant::findOrFail($id);

        $result = AppUserPasswordService::resetByAdmin($merchant, 'merchant');
        $this->lastResetTempPassword = $result['password'];

        if (! $result['email_sent']) {
            $this->showToast('Password reset. Email could not be sent — share the temporary password manually.', 'error');

            return;
        }

        $this->showToast('Password reset. Temporary password emailed to merchant.');
    }

    private function resetForm(): void
    {
        $this->reset([
            'merchantId', 'company_name', 'company_name_ms', 'company_name_zh', 'company_email', 'phone', 'whatsapp_number',
            'registration_number', 'street_address', 'city', 'postcode',
            'short_description', 'short_description_ms', 'short_description_zh',
            'about_description', 'about_description_ms', 'about_description_zh',
            'coverImage', 'existingCoverImage', 'ssmDocument', 'existingSsmDocument',
            'galleryCaption', 'approval_notes', 'lastCreatedPassword', 'benefits', 'newBenefit',
        ]);
        $this->categories = [];
        $this->galleryImages = [];
        $this->country = 'Malaysia';
    }

    public function addGalleryImages(): void
    {
        abort_unless(auth()->user()->can('merchants.edit'), 403);
        abort_unless($this->merchantId, 403);
        $this->validate([
            'galleryImages'   => ['required', 'array', 'min:1'],
            'galleryImages.*' => ['file', 'mimes:jpg,jpeg,png,gif,webp,mp4,mov,avi,webm,m4v', 'max:20480'],
        ]);

        $count = count($this->galleryImages);
        foreach ($this->galleryImages as $image) {
            $path = $image->store('merchants/gallery', 'public');
            $mimeType = (string) ($image->getMimeType() ?? '');
            $mediaType = str_starts_with($mimeType, 'video/') ? 'video' : 'image';
            MerchantGallery::create([
                'merchant_id' => $this->merchantId,
                'image_path'  => $path,
                'media_type'  => $mediaType,
                'caption'     => $this->galleryCaption ?: null,
                'status'      => 'approved',
            ]);
        }

        $this->galleryImages = [];
        $this->reset(['galleryCaption']);
        $this->showToast($count === 1 ? '1 media file added to gallery.' : "{$count} media files added to gallery.");
    }

    public function updatedCoverImage(): void
    {
        if (! $this->coverImage) {
            return;
        }

        $this->validateOnly('coverImage', [
            'coverImage' => ['image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        // Existing merchant: save cover image immediately on selection.
        if ($this->merchantId) {
            abort_unless(auth()->user()->can('merchants.edit'), 403);

            $merchant = Merchant::findOrFail($this->merchantId);
            if ($this->existingCoverImage) {
                Storage::disk('public')->delete($this->existingCoverImage);
            }

            $path = $this->coverImage->store('merchants/covers', 'public');
            $merchant->update(['cover_image' => $path]);

            $this->existingCoverImage = $path;
            $this->coverImage = null;
            $this->showToast('Cover image updated.');
            return;
        }

        // New merchant: file is kept in form state and saved on Create Merchant.
        $this->showToast('Cover image selected. It will be saved when creating the merchant.');
    }

    public function deleteGalleryImage(int $galleryId): void
    {
        abort_unless(auth()->user()->can('merchants.edit'), 403);

        $image = MerchantGallery::findOrFail($galleryId);
        Storage::disk('public')->delete($image->image_path);
        $image->delete();
        $this->showToast('Gallery image removed.');
    }

    public function assignSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.edit'), 403);

        $this->validate([
            'selectedPlanId' => ['required', 'exists:subscription_plans,id'],
        ]);

        $merchant = Merchant::findOrFail($id);
        $plan = SubscriptionPlan::findOrFail($this->selectedPlanId);
        $startsAt = now();
        $endsAt = $plan->calculateEndDate($startsAt);

        $subscription = Subscription::create([
            'subscription_plan_id' => $plan->id,
            'subscribable_type' => Merchant::class,
            'subscribable_id' => $merchant->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
            'assigned_by' => auth()->id(),
            'notes' => $this->subscriptionNotes ?: 'Assigned by admin',
        ]);

        $merchant->refreshSubscriptionState();
        if (empty($merchant->membership_id)) {
            $merchant->membership_id = Merchant::generateMembershipId();
            $merchant->save();
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'merchant_subscription_assigned',
            'entity_type' => Merchant::class,
            'entity_id' => $merchant->id,
            'after_data' => ['subscription_id' => $subscription->id, 'plan' => $plan->name],
            'reason' => $this->subscriptionNotes ?: null,
        ]);
        $this->showToast('Subscription plan assigned successfully.');
    }

    public function extendSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.edit'), 403);

        $this->validate([
            'extensionDays' => ['required', 'integer', 'min:1'],
        ]);

        $merchant = Merchant::findOrFail($id);
        $active = $merchant->activeSubscription()->first();
        if (! $active) return;

        $beforeEndsAt = $active->ends_at;
        $active->ends_at = ($active->ends_at ?? now())->addDays($this->extensionDays);
        $active->save();

        $merchant->refreshSubscriptionState();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'merchant_subscription_extended',
            'entity_type' => Merchant::class,
            'entity_id' => $merchant->id,
            'before_data' => ['ends_at' => $beforeEndsAt],
            'after_data' => ['ends_at' => $active->ends_at],
        ]);
        $this->showToast('Subscription extended successfully.');
    }

    public function cancelSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('merchants.edit'), 403);

        $merchant = Merchant::findOrFail($id);
        $active = $merchant->activeSubscription()->first();
        if (! $active) return;

        $active->status = 'cancelled';
        $active->save();

        $merchant->refreshSubscriptionState();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'merchant_subscription_cancelled',
            'entity_type' => Merchant::class,
            'entity_id' => $merchant->id,
            'after_data' => ['subscription_id' => $active->id, 'status' => 'cancelled'],
        ]);
        $this->showToast('Subscription cancelled.');
    }

    public function startCreate(): void
    {
        $this->resetForm();
        $this->reset(['selectedPlanId', 'subscriptionNotes', 'extensionDays', 'galleryCaption']);
        $this->galleryImages = [];
        $this->extensionDays = 30;
        $this->showToast('Ready to create a new merchant.');
        $this->js("
            window.dispatchEvent(new CustomEvent('merchant-form-open'));
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        ");
    }

    public function addBenefit(): void
    {
        $value = trim($this->newBenefit);
        if ($value === '') {
            return;
        }

        $this->benefits[] = $value;
        $this->newBenefit = '';
    }

    public function removeBenefit(int $index): void
    {
        if (! array_key_exists($index, $this->benefits)) {
            return;
        }

        unset($this->benefits[$index]);
        $this->benefits = array_values($this->benefits);
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = $message;
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }

    private function generatePasswordFromEmail(string $email): string
    {
        return AppUserPasswordService::generateFromEmail($email, 'merchant');
    }

    public function temporaryPasswordFor(Merchant $merchant): ?string
    {
        if (! $merchant->temp_password_issued_at) {
            return null;
        }

        return $this->generatePasswordFromEmail($merchant->company_email);
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="pk-page-title">Merchant Management</h1>
            <p class="pk-subtitle">Approval workflow, profile updates, document requests, and performance governance.</p>
        </div>
        @can('merchants.create')
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('merchant-create-request'))"
            class="pk-btn-primary shrink-0"
        >
            New Merchant
        </button>
        @endcan
    </div>
</x-slot>

<div
    class="space-y-6"
    x-data="{ showForm: false }"
    @merchant-create-request.window="$wire.startCreate()"
    @merchant-form-open.window="$dispatch('merchant-form-show')"
>
    <div
        x-show="showForm"
        x-cloak
        class="fixed inset-0 z-[115] bg-black/40 backdrop-blur-sm"
        @click="showForm = false"
    ></div>

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

    @if($lastResetTempPassword)
        <div class="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <div>
                <p class="font-semibold">Password reset</p>
                <p class="mt-1">Temporary password: <span class="font-mono font-bold">{{ $lastResetTempPassword }}</span> — also emailed to the merchant.</p>
            </div>
            <button type="button" wire:click="$set('lastResetTempPassword', null)" class="shrink-0 text-xs font-semibold text-emerald-700 hover:text-emerald-900">Dismiss</button>
        </div>
    @endif

    <section class="pk-filter-bar">
        <input wire:model.live="search" class="pk-input w-full sm:max-w-sm" placeholder="Search merchant">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="deactivated">Deactivated</option>
            <option value="rejected">Rejected</option>
            <option value="pending_subscription">Pending Subscription</option>
        </select>
        <select wire:model.live="subscriptionFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Subscription State</option>
            <option value="active_subscription">Active Subscription</option>
            <option value="pending_subscription">Pending Subscription</option>
        </select>
        <select wire:model.live="cityFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All City</option>
            @foreach($merchantCities as $city)
                <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>
        <select wire:model.live="countryFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Country</option>
            @foreach($merchantCountries as $country)
                <option value="{{ $country }}">{{ $country }}</option>
            @endforeach
        </select>
        <select wire:model.live="joinedYearFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Joined Year</option>
            @foreach($joinedYears as $year => $count)
                <option value="{{ $year }}">{{ $year }} ({{ $count }})</option>
            @endforeach
        </select>
    </section>

    <section class="grid gap-6 xl:grid-cols-3">
        {{-- Form card (collapsible on mobile) --}}
        <div
            class="pk-card fixed left-1/2 top-4 z-[120] flex max-h-[92dvh] w-[calc(100%-1.5rem)] max-w-5xl -translate-x-1/2 flex-col overflow-hidden p-5"
            x-data="{ open: window.matchMedia('(min-width: 768px)').matches, activeLang: 'en', activeTab: 'profile' }"
            x-show="showForm"
            x-cloak
            x-init="
                const mq = window.matchMedia('(min-width: 768px)');
                const sync = () => { if (mq.matches) open = true; };
                if (mq.addEventListener) mq.addEventListener('change', sync);
                else mq.addListener(sync);
            "
            @merchant-form-show.window="showForm = true; open = true; activeTab = 'profile'; activeLang = 'en'"
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
                <h2 class="text-base font-semibold text-slate-800">{{ $merchantId ? 'Edit Merchant' : 'Create Merchant' }}</h2>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="flex items-center justify-center rounded-xl border p-2 text-slate-500 transition hover:bg-slate-50"
                        @click="showForm = false"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="mt-3 border-b border-slate-100">
                <nav class="-mb-px flex gap-1 overflow-x-auto">
                    @foreach([
                        'profile' => 'Profile',
                        'merchant' => 'Merchant',
                        'subscription' => 'Subscription',
                        'points' => 'Points',
                    ] as $key => $label)
                    <button
                        type="button"
                        @click="
                            if (!{{ $merchantId ? 'true' : 'false' }} && ( '{{ $key }}' === 'subscription' || '{{ $key }}' === 'points')) return;
                            activeTab = '{{ $key }}'
                        "
                        :class="activeTab === '{{ $key }}'
                            ? 'border-[#E31E24] text-[#E31E24] bg-rose-50/50'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                        class="rounded-t-lg border-b-2 px-4 py-2.5 text-sm font-medium transition whitespace-nowrap disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:text-slate-500 disabled:hover:bg-transparent"
                        @if(!$merchantId && in_array($key, ['subscription', 'points'])) title="Available after merchant is created" @endif
                        @if(!$merchantId && in_array($key, ['subscription', 'points'])) disabled @endif
                    >
                        {{ $label }}
                    </button>
                    @endforeach
                </nav>
            </div>

            <div x-show="open" x-collapse class="mt-3 flex-1 overflow-y-auto pr-1">
                <form wire:submit="saveMerchant" class="mt-4 space-y-3">

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

                    <div x-show="activeTab === 'profile'" x-cloak class="space-y-3">
                    <div x-show="activeLang === 'en'" x-cloak>
                        <x-admin.form.group label="Company Name *">
                            <input class="{{ $errors->has('company_name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="company_name">
                            @error('company_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </x-admin.form.group>
                    </div>

                    <div x-show="activeLang === 'ms'" x-cloak>
                        <x-admin.form.group label="Company Name (Malay)">
                            <input class="pk-input" wire:model="company_name_ms">
                            @error('company_name_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </x-admin.form.group>
                    </div>

                    <div x-show="activeLang === 'zh'" x-cloak>
                        <x-admin.form.group label="Company Name (Chinese)">
                            <input class="pk-input" wire:model="company_name_zh">
                            @error('company_name_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </x-admin.form.group>
                    </div>
                    <x-admin.form.group label="Company Email *">
                        <input class="{{ $errors->has('company_email') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" wire:model="company_email" type="email">
                        @error('company_email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </x-admin.form.group>
                    <x-admin.form.group label="Categories{{ $merchantId ? '' : ' *' }}">
                        @if($merchantCategories->isEmpty())
                            <p class="text-xs text-slate-400">No categories configured yet. <a href="{{ route('admin.merchant-categories') }}" class="text-indigo-600 underline" wire:navigate>Manage categories</a></p>
                        @else
                            <div class="max-h-40 overflow-y-auto rounded-xl border border-slate-200 p-2 space-y-1">
                                @foreach($merchantCategories as $cat)
                                    @php
                                        $catLabels = $cat->name_i18n;
                                    @endphp
                                    <label class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 cursor-pointer">
                                        <input type="checkbox" wire:model="categories" value="{{ $cat->name }}" class="rounded">
                                        <span class="min-w-0" x-show="activeLang === 'en'" x-cloak>{{ $catLabels['en'] }}</span>
                                        <span class="min-w-0" x-show="activeLang === 'ms'" x-cloak>{{ $catLabels['ms'] }}</span>
                                        <span class="min-w-0" x-show="activeLang === 'zh'" x-cloak>{{ $catLabels['zh'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('categories')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        @endif
                    </x-admin.form.group>
                    <x-admin.form.group label="Phone"><input class="pk-input" wire:model="phone"></x-admin.form.group>
                    <x-admin.form.group label="Registration Number"><input class="pk-input" wire:model="registration_number"></x-admin.form.group>
                    <x-admin.form.group label="Street Address">
                        <input class="pk-input" wire:model="street_address" placeholder="No. & Street name">
                    </x-admin.form.group>
                    <div class="grid grid-cols-2 gap-3">
                        <x-admin.form.group label="City">
                            <input class="pk-input" wire:model="city">
                        </x-admin.form.group>
                        <x-admin.form.group label="Postcode">
                            <input class="pk-input" wire:model="postcode">
                        </x-admin.form.group>
                    </div>
                    <x-admin.form.group label="Country">
                        <select class="pk-input" wire:model="country">
                            <option value="">— Select Country —</option>
                            <option value="Malaysia">Malaysia</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Indonesia">Indonesia</option>
                            <option value="Thailand">Thailand</option>
                            <option value="Philippines">Philippines</option>
                            <option value="Vietnam">Vietnam</option>
                            <option value="Myanmar">Myanmar</option>
                            <option value="Cambodia">Cambodia</option>
                            <option value="Brunei">Brunei</option>
                            <option value="Laos">Laos</option>
                            <option value="China">China</option>
                            <option value="Japan">Japan</option>
                            <option value="South Korea">South Korea</option>
                            <option value="India">India</option>
                            <option value="Bangladesh">Bangladesh</option>
                            <option value="Pakistan">Pakistan</option>
                            <option value="Sri Lanka">Sri Lanka</option>
                            <option value="Nepal">Nepal</option>
                            <option value="Australia">Australia</option>
                            <option value="New Zealand">New Zealand</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="Canada">Canada</option>
                            <option value="Germany">Germany</option>
                            <option value="France">France</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="Other">Other</option>
                        </select>
                    </x-admin.form.group>
                    <x-admin.form.group label="Notes/Reason"><textarea class="pk-input" wire:model="approval_notes"></textarea></x-admin.form.group>
                    </div>

                    <div x-show="activeTab === 'merchant'" x-cloak class="space-y-3">
                    <div class="border-t border-slate-100 pt-4 space-y-4">
                        <h3 class="text-sm font-semibold text-slate-700">Merchant Profile</h3>
                        <x-admin.form.group label="WhatsApp Number">
                            <input class="pk-input" wire:model="whatsapp_number" placeholder="e.g. +60123456789">
                            @error('whatsapp_number')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </x-admin.form.group>
                        <div x-show="activeLang === 'en'" x-cloak>
                            <x-admin.form.group label="Short description (member detail)">
                                <textarea class="pk-input" wire:model="short_description" rows="3" placeholder="Brief line shown on the member merchant detail screen"></textarea>
                            </x-admin.form.group>
                        </div>

                        <div x-show="activeLang === 'ms'" x-cloak>
                            <x-admin.form.group label="Short description — Malay">
                                <textarea class="pk-input" wire:model="short_description_ms" rows="3"></textarea>
                                @error('short_description_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </x-admin.form.group>
                        </div>

                        <div x-show="activeLang === 'zh'" x-cloak>
                            <x-admin.form.group label="Short description — Chinese">
                                <textarea class="pk-input" wire:model="short_description_zh" rows="3"></textarea>
                                @error('short_description_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </x-admin.form.group>
                        </div>

                        <div x-show="activeLang === 'en'" x-cloak>
                            <x-admin.form.group label="About (listing — About &amp; Member Benefits)">
                                <textarea class="pk-input" wire:model="about_description" rows="5" placeholder="Longer copy for the merchant app listing About section"></textarea>
                                @error('about_description')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </x-admin.form.group>
                        </div>

                        <div x-show="activeLang === 'ms'" x-cloak>
                            <x-admin.form.group label="About (listing) — Malay">
                                <textarea class="pk-input" wire:model="about_description_ms" rows="5"></textarea>
                                @error('about_description_ms')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </x-admin.form.group>
                        </div>

                        <div x-show="activeLang === 'zh'" x-cloak>
                            <x-admin.form.group label="About (listing) — Chinese">
                                <textarea class="pk-input" wire:model="about_description_zh" rows="5"></textarea>
                                @error('about_description_zh')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </x-admin.form.group>
                        </div>
                        <x-admin.form.group label="Merchant Offer Benefits">
                            <div class="space-y-2">
                                <div class="flex items-center gap-2">
                                    <input class="pk-input min-w-0 flex-1" wire:model.live="newBenefit" placeholder="e.g. Free delivery above RM50">
                                    <button type="button" class="pk-btn-secondary shrink-0 px-3 py-2 text-xs" wire:click="addBenefit">Add</button>
                                </div>
                                @foreach($benefits as $index => $benefit)
                                    <div class="flex items-center justify-between rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                                        <span>{{ $benefit }}</span>
                                        <button type="button" class="text-xs font-semibold text-rose-600" wire:click="removeBenefit({{ $index }})">Delete</button>
                                    </div>
                                @endforeach
                            </div>
                            @error('benefits')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            @error('benefits.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </x-admin.form.group>

                        {{-- Cover Image --}}
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-slate-700">Cover Image</h3>
                        @if($coverImage)
                            <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                                <img src="{{ $coverImage->temporaryUrl() }}" alt="Preview" class="h-32 w-full object-cover">
                            </div>
                        @elseif($existingCoverImage)
                            <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                                <img src="{{ Storage::url($existingCoverImage) }}" alt="Cover" class="h-32 w-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';">
                                <div style="display:none" class="h-32 w-full place-items-center bg-slate-100 px-3 py-2 text-center text-xs font-medium text-slate-500">
                                    Cover image unavailable
                                </div>
                            </div>
                        @endif
                        <div
                            x-data="{
                                isDragging: false,
                                handleDrop(e) {
                                    this.isDragging = false;
                                    const files = e.dataTransfer.files;
                                    if (!files.length) return;
                                    const input = $refs.coverInput;
                                    const dt = new DataTransfer();
                                    dt.items.add(files[0]);
                                    input.files = dt.files;
                                    input.dispatchEvent(new Event('change'));
                                }
                            }"
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
                            <input x-ref="coverInput" type="file" wire:model="coverImage" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                        </div>
                        @error('coverImage')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>

                        {{-- Media Gallery — only shown when editing an existing merchant --}}
                        @if($merchantId)
                        <div>
                            <h3 class="mb-2 text-sm font-semibold text-slate-700">Media Gallery</h3>
                            <p class="mb-3 text-xs font-medium text-indigo-600">{{ $company_name }}</p>

                            {{-- Existing gallery media --}}
                            @if($savedGalleryImages->isNotEmpty())
                                <div class="grid grid-cols-4 gap-2 xl:grid-cols-5">
                                    @foreach($savedGalleryImages as $img)
                                        <div class="group relative overflow-hidden rounded-xl border border-slate-200">
                                            @if(($img->media_type ?? 'image') === 'video')
                                                <div class="h-16 bg-white sm:h-20">
                                                    <video src="{{ Storage::url($img->image_path) }}" class="h-full w-full object-cover" controls preload="metadata"></video>
                                                </div>
                                            @else
                                                <div class="h-16 bg-white sm:h-20">
                                                    <img src="{{ Storage::url($img->image_path) }}" alt="{{ $img->caption }}" class="h-full w-full object-cover">
                                                </div>
                                            @endif
                                            @if($img->caption)
                                                <p class="truncate bg-white/80 px-2 py-1 text-xs text-slate-600">{{ $img->caption }}</p>
                                            @endif
                                            @can('merchants.edit')
                                            <button
                                                type="button"
                                                wire:click="deleteGalleryImage({{ $img->id }})"
                                                wire:confirm="Remove this image?"
                                                class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-rose-600 text-white opacity-0 transition group-hover:opacity-100"
                                            >
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            @endcan
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-slate-400">No gallery images yet.</p>
                            @endif

                            {{-- Upload new media --}}
                            <div class="mt-3 space-y-2 rounded-xl border border-slate-200 bg-slate-50/80 p-3"
                                x-data="{
                                    isDragging: false,
                                    handleDrop(e) {
                                        this.isDragging = false;
                                        const files = e.dataTransfer.files;
                                        if (!files.length) return;
                                        const input = $refs.galleryInput;
                                        const dt = new DataTransfer();
                                        for (const f of files) dt.items.add(f);
                                        input.files = dt.files;
                                        input.dispatchEvent(new Event('change'));
                                    }
                                }"
                            >
                                <p class="text-xs font-semibold text-slate-700">Add Images / Videos</p>
                                <p class="text-[11px] text-slate-500">Upload area for the media gallery section.</p>
                                <div
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="handleDrop($event)"
                                    @click="$refs.galleryInput.click()"
                                    :class="isDragging ? 'border-[#ed1c24] bg-rose-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                                    class="cursor-pointer rounded-xl border-2 border-dashed p-5 text-center transition"
                                >
                                    <svg class="mx-auto mb-2 h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                    <p class="text-xs font-medium text-slate-600">Drag & drop or <span class="text-[#ed1c24]">click to browse</span></p>
                                    <p class="mt-0.5 text-xs text-slate-400">Multiple files supported — JPG, PNG, GIF, WEBP, MP4, MOV, AVI, WEBM — max 20 MB each</p>
                                    <input x-ref="galleryInput" type="file" wire:model="galleryImages" accept="image/jpeg,image/png,image/gif,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-m4v" multiple class="hidden">
                                </div>
                                @error('galleryImages')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                @error('galleryImages.*')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror

                                {{-- Selected files preview (Livewire-driven, survives re-render) --}}
                                @if(count($galleryImages) > 0)
                                    <div class="mt-2 grid grid-cols-4 gap-2 xl:grid-cols-5">
                                        @foreach($galleryImages as $selectedFile)
                                            @php
                                                $selectedMime = (string) ($selectedFile->getMimeType() ?? '');
                                                $selectedIsVideo = str_starts_with($selectedMime, 'video/');
                                            @endphp
                                            <div class="h-16 overflow-hidden rounded-xl border border-slate-200 bg-white sm:h-20">
                                                @if($selectedIsVideo)
                                                    <div class="flex h-full w-full items-center justify-center bg-slate-100 px-2 text-center text-[11px] font-medium text-slate-500">
                                                        {{ $selectedFile->getClientOriginalName() }}
                                                    </div>
                                                @else
                                                    <img src="{{ $selectedFile->temporaryUrl() }}" class="h-full w-full object-cover" alt="Selected media preview">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ count($galleryImages) }} file(s) selected</p>
                                @endif

                                <x-admin.form.group label="Caption (optional — applies to all selected files)">
                                    <input class="pk-input" wire:model="galleryCaption" placeholder="e.g. Store interior, Promo video...">
                                </x-admin.form.group>
                                @can('merchants.edit')
                                <button type="button" wire:click="addGalleryImages" class="pk-btn-primary w-full"
                                >{{ count($galleryImages) > 0 ? 'Upload '.count($galleryImages).' File(s)' : 'Upload Media' }}</button>
                                @endcan
                            </div>
                        </div>
                        @endif

                        {{-- SSM Document --}}
                        <div>
                            <h3 class="mb-3 text-sm font-semibold text-slate-700">SSM Document</h3>
                            @if($ssmDocument)
                                <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                                    <img src="{{ $ssmDocument->temporaryUrl() }}" alt="SSM Preview" class="h-32 w-full object-cover">
                                </div>
                            @elseif($existingSsmDocument)
                                <a href="{{ Storage::url($existingSsmDocument) }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl border border-slate-200">
                                    <img src="{{ Storage::url($existingSsmDocument) }}" alt="SSM Document" class="h-32 w-full object-cover">
                                </a>
                                <p class="mt-1 text-xs text-slate-400">Click image to open full document.</p>
                            @else
                                <p class="text-xs text-slate-400">No SSM document uploaded yet. Document will appear here after merchant upload.</p>
                            @endif
                            <div
                                x-data="{
                                    isDragging: false,
                                    handleDrop(e) {
                                        this.isDragging = false;
                                        const files = e.dataTransfer.files;
                                        if (!files.length) return;
                                        const input = $refs.ssmInput;
                                        const dt = new DataTransfer();
                                        dt.items.add(files[0]);
                                        input.files = dt.files;
                                        input.dispatchEvent(new Event('change'));
                                    }
                                }"
                                @dragover.prevent="isDragging = true"
                                @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop($event)"
                                @click="$refs.ssmInput.click()"
                                :class="isDragging ? 'border-[#ed1c24] bg-rose-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                                class="mt-3 cursor-pointer rounded-xl border-2 border-dashed p-5 text-center transition"
                            >
                                <svg class="mx-auto mb-2 h-7 w-7 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/></svg>
                                <p class="text-xs font-medium text-slate-600">Drag & drop or <span class="text-[#ed1c24]">click to browse</span></p>
                                <p class="mt-0.5 text-xs text-slate-400">JPG, PNG, GIF, WEBP — max 10 MB</p>
                                <input x-ref="ssmInput" type="file" wire:model="ssmDocument" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                            </div>
                            @error('ssmDocument')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    </div>

                    <div x-show="activeTab === 'profile' || activeTab === 'merchant'" x-cloak>
                    @if($merchantId)
                        @can('merchants.edit')
                        <button class="pk-btn-primary w-full relative" wire:loading.attr="disabled" wire:target="saveMerchant">
                            <span wire:loading.remove wire:target="saveMerchant">Update Merchant</span>
                            <span wire:loading wire:target="saveMerchant">
                                <svg class="animate-spin h-4 w-4 text-white inline-block align-middle mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span class="align-middle">Saving...</span>
                            </span>
                        </button>
                        @endcan
                    @else
                        @can('merchants.create')
                        <button class="pk-btn-primary w-full relative" wire:loading.attr="disabled" wire:target="saveMerchant">
                            <span wire:loading.remove wire:target="saveMerchant">Create Merchant</span>
                            <span wire:loading wire:target="saveMerchant">
                                <svg class="animate-spin h-4 w-4 text-white inline-block align-middle mr-1" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{-- <span class="align-middle">Sending welcome email...</span> --}}
                            </span>
                        </button>
                        @endcan
                    @endif
                    </div>
                </form>

                <div x-show="activeTab === 'profile' || activeTab === 'merchant'" x-cloak>
                    @if($lastCreatedPassword)
                    <div class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Temporary login password</p>
                        <p class="mt-1 text-sm text-emerald-800">Temporary password: <span class="font-mono font-semibold">{{ $lastCreatedPassword }}</span></p>
                    </div>
                    @endif
                </div>

                <div x-show="activeTab === 'subscription'" x-cloak class="mt-6 border-t border-slate-100 pt-4 space-y-5">
                    <h3 class="text-sm font-semibold text-slate-700">Subscription Assignment</h3>
                    @if($merchantId)
                        <p class="mt-1 text-xs font-medium text-indigo-600">Applying to: {{ $company_name }}</p>
                    @else
                        <p class="mt-1 text-xs text-slate-400">Load a merchant via View/Edit to use this section.</p>
                    @endif

                    <x-admin.form.group label="Plan *" class="mt-3">
                        <select wire:model="selectedPlanId" class="pk-input">
                            <option value="">Select plan</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">
                                    {{ $plan->name }} (RM {{ number_format((float) $plan->price, 2) }}){{ $plan->is_active ? '' : ' — Inactive' }}
                                </option>
                            @endforeach
                        </select>
                        @if($plans->isEmpty())
                            <p class="mt-1 text-xs text-rose-600">No subscription plans found for Merchant/Both audience.</p>
                        @endif
                    </x-admin.form.group>
                    <x-admin.form.group label="Subscription Notes" class="mt-3"><input class="pk-input" wire:model="subscriptionNotes" placeholder="Optional notes"></x-admin.form.group>
                    @if($merchantId)
                        @can('merchants.edit')
                        <button type="button" wire:click="assignSubscription({{ $merchantId }})" class="pk-btn-primary mt-3 w-full">Assign Plan</button>
                        @endcan
                    @endif

                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <h4 class="text-xs font-semibold text-slate-600">Extend / Cancel Active Subscription</h4>
                        <x-admin.form.group label="Extension Days *" class="mt-2"><input type="number" class="pk-input" wire:model="extensionDays"></x-admin.form.group>
                        @if($merchantId)
                            @can('merchants.edit')
                            <div class="mt-2 grid grid-cols-2 gap-2">
                                <button type="button" wire:click="extendSubscription({{ $merchantId }})" class="pk-btn-secondary py-2 text-xs">Extend</button>
                                <button type="button" wire:click="cancelSubscription({{ $merchantId }})" class="pk-btn-secondary py-2 text-xs" style="color:#dc2626">Cancel Plan</button>
                            </div>
                            @endcan
                        @endif
                    </div>

                    @if($merchantId)
                    <div class="mt-4 border-t border-slate-100 pt-4 space-y-2">
                        <p class="text-sm font-semibold text-slate-800">Subscription records &amp; plan details</p>
                        <p class="text-xs text-slate-500">All subscription rows assigned to this merchant, with linked plan information.</p>
                        @if($merchantSubscriptionHistory->isEmpty())
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center text-xs text-slate-500">No subscription records yet.</p>
                        @else
                            <div class="max-h-72 overflow-y-auto overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="sticky top-0 bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Plan</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Audience</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Price</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Duration</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Status</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Starts</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Ends</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Admin fee</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($merchantSubscriptionHistory as $subRow)
                                            @php
                                                $p = $subRow->plan;
                                                $dur = $p ? ($p->duration_value.' '.ucfirst($p->duration_unit).($p->duration_value > 1 ? 's' : '')) : '—';
                                            @endphp
                                            <tr class="align-top">
                                                <td class="px-3 py-2 font-medium text-slate-800">{{ $p?->name ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $p?->audience ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">@if($p)RM {{ number_format((float) ($p->final_price ?? $p->price), 2) }}@else — @endif</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $dur }}</td>
                                                <td class="px-3 py-2"><x-admin.status-badge :status="$subRow->status" class="text-[11px]" /></td>
                                                <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $subRow->starts_at?->format('d M Y H:i') ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600 whitespace-nowrap">{{ $subRow->ends_at?->format('d M Y H:i') ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $subRow->admin_fee_amount !== null ? 'RM '.number_format((float) $subRow->admin_fee_amount, 2) : '—' }}</td>
                                            </tr>
                                            @if($p && $p->relationLoaded('descriptions') && $p->descriptions->isNotEmpty())
                                                <tr>
                                                    <td colspan="8" class="bg-slate-50/80 px-3 py-2 text-slate-600">
                                                        <span class="font-semibold text-slate-700">Plan descriptions:</span>
                                                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                                                            @foreach($p->descriptions as $d)
                                                                <li>{{ $d->description }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </td>
                                                </tr>
                                            @endif
                                            @if($subRow->notes)
                                                <tr>
                                                    <td colspan="8" class="bg-slate-50/50 px-3 py-1.5 text-[11px] text-slate-500"><span class="font-medium text-slate-600">Notes:</span> {{ $subRow->notes }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-4 space-y-2">
                        <p class="text-sm font-semibold text-slate-800">Payment gateway transactions</p>
                        <p class="text-xs text-slate-500">Gateway subscription payments for this merchant.</p>
                        @if($merchantSubscriptionPayments->isEmpty())
                            <p class="rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-4 text-center text-xs text-slate-500">No subscription payment transactions yet.</p>
                        @else
                            <div class="max-h-64 overflow-y-auto overflow-x-auto rounded-xl border border-slate-200">
                                <table class="min-w-full text-left text-xs">
                                    <thead class="sticky top-0 bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Date</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Status</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Plan</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Plan amt.</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Admin fee</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Total</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Order ID</th>
                                            <th class="whitespace-nowrap px-3 py-2 font-semibold">Payment ID</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($merchantSubscriptionPayments as $pay)
                                            <tr>
                                                <td class="px-3 py-2 whitespace-nowrap text-slate-700">{{ $pay->created_at?->format('d M Y H:i') }}</td>
                                                <td class="px-3 py-2"><x-admin.status-badge :status="$pay->status" class="text-[11px]" /></td>
                                                <td class="px-3 py-2 text-slate-700">{{ $pay->plan?->name ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">RM {{ number_format((float) $pay->amount_plan, 2) }}</td>
                                                <td class="px-3 py-2 text-slate-600">RM {{ number_format((float) $pay->amount_admin_fee, 2) }}</td>
                                                <td class="px-3 py-2 font-medium text-slate-800">RM {{ number_format((float) $pay->amount_total, 2) }}</td>
                                                <td class="px-3 py-2 font-mono text-[10px] text-slate-600">{{ Str::limit($pay->gateway_order_id ?? '—', 24) }}</td>
                                                <td class="px-3 py-2 font-mono text-[10px] text-slate-600">{{ $pay->gateway_payment_id ? Str::limit($pay->gateway_payment_id, 24) : '—' }}</td>
                                            </tr>
                                            @if($pay->fee_reason)
                                                <tr>
                                                    <td colspan="8" class="bg-slate-50/80 px-3 py-1 text-[11px] text-slate-500">Fee reason: {{ $pay->fee_reason }}</td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endif
                </div>

                <div x-show="activeTab === 'points'" x-cloak class="mt-6 border-t border-slate-100 pt-4 space-y-4">
                    <h3 class="text-sm font-semibold text-slate-700">Points Overview</h3>
                    @if($merchantId && $merchantPointStats)
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Total Points Granted</p>
                                <p class="mt-1 text-lg font-semibold text-slate-800">{{ number_format($merchantPointStats['total_points_granted']) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Today Granted</p>
                                <p class="mt-1 text-lg font-semibold text-slate-800">{{ number_format($merchantPointStats['today_granted_points']) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Total Transactions</p>
                                <p class="mt-1 text-lg font-semibold text-slate-800">{{ number_format($merchantPointStats['total_transactions']) }}</p>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Total Customers</p>
                                <p class="mt-1 text-lg font-semibold text-slate-800">{{ number_format($merchantPointStats['total_customers']) }}</p>
                            </div>
                        </div>

                        <div class="rounded-xl border border-slate-200">
                            <div class="border-b border-slate-100 px-3 py-2">
                                <p class="text-xs font-semibold text-slate-600">Recent Point Transactions</p>
                            </div>
                            <div class="divide-y divide-slate-100">
                                @forelse($merchantRecentTransactions as $txn)
                                    <div class="flex items-center justify-between gap-2 px-3 py-2 text-xs">
                                        <div class="min-w-0">
                                            <p class="truncate font-medium text-slate-700">
                                                {{ $txn->member?->full_name ?: 'Member #'.$txn->member_id }}
                                            </p>
                                            <p class="truncate text-slate-500">
                                                {{ optional($txn->occurred_at)->format('d M Y, h:i A') ?: '-' }} · Ref: {{ $txn->reference_number ?: '-' }}
                                            </p>
                                        </div>
                                        <span class="shrink-0 font-semibold text-emerald-700">+{{ number_format((int) $txn->points) }}</span>
                                    </div>
                                @empty
                                    <p class="px-3 py-4 text-xs text-slate-400">No point transactions yet.</p>
                                @endforelse
                            </div>
                        </div>
                    @else
                        <p class="text-xs text-slate-400">Load a merchant via View/Edit to see point stats.</p>
                    @endif
                </div>

            </div>
        </div>

        {{-- List --}}
        <div class="xl:col-span-3">
            {{-- Mobile card view --}}
            <div class="pk-mobile-only space-y-3">
                @foreach($merchants as $merchant)
                    <article class="pk-card p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $merchant->company_name }}</p>
                                <p class="text-xs text-slate-500">{{ $merchant->company_email }}</p>
                                <p class="text-xs font-mono font-semibold text-[#ed1c24] mt-1">{{ $merchant->membership_id }}</p>
                            </div>
                            <x-admin.status-badge :status="$merchant->status" />
                        </div>
                        <dl class="mt-3 space-y-1 text-xs text-slate-600">
                            <div class="flex justify-between gap-3"><dt>Category</dt><dd>{{ is_array($merchant->categories) ? (count($merchant->categories) ? implode(', ', $merchant->categories) : 'Not set') : ($merchant->categories ?: 'Not set') }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Phone</dt><dd>{{ $merchant->phone ?: '-' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt>Joined</dt><dd>{{ optional($merchant->created_at)->format('d M Y') ?: '-' }}</dd></div>
                            <div class="flex justify-between gap-3 items-center"><dt>Temp password</dt><dd>@if($temp = $this->temporaryPasswordFor($merchant))<span class="pk-badge-warning font-mono text-[11px] tracking-tight">{{ $temp }}</span>@else<span class="text-slate-400">—</span>@endif</dd></div>
                            <div class="flex justify-between gap-3 items-start"><dt class="shrink-0 pt-0.5">Subscription</dt>
                                <dd class="text-right">
                                    @php
                                        $mSub = $merchant->activeSubscription;
                                        $mPlan = $mSub?->plan;
                                        $mEnds = $merchant->subscription_ends_at;
                                        $mDays = $mEnds ? (int) now()->diffInDays($mEnds, false) : null;
                                        $mDuration = $mPlan ? $mPlan->duration_value . ' ' . ucfirst($mPlan->duration_unit) . ($mPlan->duration_value > 1 ? 's' : '') : null;
                                        $mIsActive = $mEnds && $mDays > 0;
                                        $mIsExpired = $mEnds && $mDays <= 0;
                                    @endphp
                                    <x-admin.status-badge :status="$merchant->subscription_state" />
                                    @if($mEnds)
                                        <div class="mt-1.5 rounded-lg border px-2 py-1.5 text-left {{ $mIsActive ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                                            @if($mPlan)
                                                <p class="text-xs font-semibold {{ $mIsActive ? 'text-emerald-800' : 'text-rose-700' }}">{{ $mPlan->name }}@if($mDuration)<span class="font-normal {{ $mIsActive ? 'text-emerald-600' : 'text-rose-500' }}"> · {{ $mDuration }}</span>@endif</p>
                                            @endif
                                            <p class="text-xs {{ $mIsActive ? 'text-emerald-700' : 'text-rose-600' }}">Ends {{ $mEnds->format('d M Y') }}</p>
                                            @if($mIsActive)
                                                <div class="mt-0.5 flex items-center gap-1 text-xs font-medium text-emerald-600">
                                                    <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                    {{ $mDays }} days remaining
                                                </div>
                                            @else
                                                <p class="mt-0.5 text-xs font-medium text-rose-600">Expired</p>
                                            @endif
                                        </div>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                        <div class="mt-3 flex items-center gap-2">
                            <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" wire:click="editMerchant({{ $merchant->id }})">View/Edit</button>
                            @if(in_array($merchant->status, ['active', 'pending_subscription']))
                                @can('merchants.suspend')
                                <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" style="color:#dc2626" wire:click="suspend({{ $merchant->id }})">Suspend</button>
                                @endcan
                            @else
                                @if($merchant->status === 'suspended')
                                    @can('merchants.activate')
                                    <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" style="color:#16a34a" wire:click="activate({{ $merchant->id }})">Activate</button>
                                    @endcan
                                @elseif($merchant->status === 'deactivated')
                                    @can('merchants.activate')
                                    <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" style="color:#16a34a" wire:click="activate({{ $merchant->id }})">Reactivate</button>
                                    @endcan
                                @else
                                    @can('merchants.approve')
                                    <button class="pk-btn-secondary flex-1 px-2 py-2 text-xs" style="color:#16a34a" wire:click="approve({{ $merchant->id }})">Approve</button>
                                    @endcan
                                @endif
                            @endif
                            <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                <button type="button" @click="open = !open" class="pk-btn-secondary px-2 py-2 text-xs">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                                </button>
                                <div x-show="open" x-cloak class="absolute right-0 z-50 mt-1 w-48 rounded-xl border border-slate-100 bg-white py-1 shadow-lg">
                                    @can('merchants.reject')
                                    <button type="button" wire:click="reject({{ $merchant->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50">Reject</button>
                                    @endcan
                                    @can('merchants.edit')
                                    <button
                                        type="button"
                                        wire:click="resetPassword({{ $merchant->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resetPassword({{ $merchant->id }})"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                                        <svg wire:loading.remove wire:target="resetPassword({{ $merchant->id }})" class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                        <svg wire:loading wire:target="resetPassword({{ $merchant->id }})" class="h-3.5 w-3.5 shrink-0 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span wire:loading.remove wire:target="resetPassword({{ $merchant->id }})">Reset Password</span>
                                        <span wire:loading wire:target="resetPassword({{ $merchant->id }})">Resetting…</span>
                                    </button>
                                    <button type="button" wire:click="cancelSubscription({{ $merchant->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50">Cancel Plan</button>
                                    @endcan
                                </div>
                            </div>
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
                                    <button type="button" wire:click="sortBy('company_name')" class="inline-flex items-center gap-1">Merchant <span>{{ $this->sortIcon('company_name') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">Membership ID</th>
                                <th class="whitespace-nowrap">Category</th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1">Status <span>{{ $this->sortIcon('status') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">
                                    <button type="button" wire:click="sortBy('subscription_ends_at')" class="inline-flex items-center gap-1">Subscription <span>{{ $this->sortIcon('subscription_ends_at') }}</span></button>
                                </th>
                                <th class="whitespace-nowrap">Phone</th>
                                <th class="whitespace-nowrap">Joined Date</th>
                                <th class="whitespace-nowrap">Temp Password</th>
                                <th class="whitespace-nowrap text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($merchants as $merchant)
                                <tr>
                                    <td class="whitespace-nowrap">
                                        <p class="font-semibold text-slate-700">{{ $merchant->company_name }}</p>
                                        <p class="text-xs text-slate-500">{{ $merchant->company_email }}</p>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span class="text-xs font-mono font-semibold text-[#ed1c24]">{{ $merchant->membership_id }}</span>
                                    </td>
                                    <td class="whitespace-nowrap">{{ is_array($merchant->categories) ? (count($merchant->categories) ? implode(', ', $merchant->categories) : 'Not set') : ($merchant->categories ?: 'Not set') }}</td>
                                    <td class="whitespace-nowrap"><x-admin.status-badge :status="$merchant->status" /></td>
                                    <td>
                                        @php
                                            $tSub = $merchant->activeSubscription;
                                            $tPlan = $tSub?->plan;
                                            $tEnds = $merchant->subscription_ends_at;
                                            $tDays = $tEnds ? (int) now()->diffInDays($tEnds, false) : null;
                                            $tDuration = $tPlan ? $tPlan->duration_value . ' ' . ucfirst($tPlan->duration_unit) . ($tPlan->duration_value > 1 ? 's' : '') : null;
                                            $tIsActive = $tEnds && $tDays > 0;
                                            $tIsExpired = $tEnds && $tDays <= 0;
                                        @endphp
                                        <div class="min-w-[180px]">
                                            <x-admin.status-badge :status="$merchant->subscription_state" />
                                            @if($tEnds)
                                                <div class="mt-1.5 rounded-lg border px-2.5 py-2 {{ $tIsActive ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                                                    @if($tPlan)
                                                        <p class="text-xs font-semibold {{ $tIsActive ? 'text-emerald-800' : 'text-rose-700' }}">{{ $tPlan->name }}@if($tDuration)<span class="font-normal {{ $tIsActive ? 'text-emerald-600' : 'text-rose-500' }}"> · {{ $tDuration }}</span>@endif</p>
                                                    @endif
                                                    <p class="text-xs {{ $tIsActive ? 'text-emerald-700' : 'text-rose-600' }}">Ends {{ $tEnds->format('d M Y') }}</p>
                                                    @if($tIsActive)
                                                        <div class="mt-0.5 flex items-center gap-1 text-xs font-medium text-emerald-600">
                                                            <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                                            {{ $tDays }} days remaining
                                                        </div>
                                                    @else
                                                        <p class="mt-0.5 text-xs font-medium text-rose-600">Expired</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">{{ $merchant->phone ?: '-' }}</td>
                                    <td class="whitespace-nowrap text-xs text-slate-600">{{ optional($merchant->created_at)->format('d M Y') ?: '-' }}</td>
                                    <td class="whitespace-nowrap">
                                        @if($temp = $this->temporaryPasswordFor($merchant))
                                            <span class="pk-badge-warning font-mono text-[11px] tracking-tight">{{ $temp }}</span>
                                        @else
                                            <span class="text-xs text-slate-400">—</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <button class="pk-btn-secondary px-3 py-1.5 text-xs" wire:click="editMerchant({{ $merchant->id }})">View/Edit</button>
                                            @if(in_array($merchant->status, ['active', 'pending_subscription']))
                                                @can('merchants.suspend')
                                                <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#dc2626" wire:click="suspend({{ $merchant->id }})">Suspend</button>
                                                @endcan
                                            @else
                                                @if($merchant->status === 'suspended')
                                                    @can('merchants.activate')
                                                    <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="activate({{ $merchant->id }})">Activate</button>
                                                    @endcan
                                                @elseif($merchant->status === 'deactivated')
                                                    @can('merchants.activate')
                                                    <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="activate({{ $merchant->id }})">Reactivate</button>
                                                    @endcan
                                                @else
                                                    @can('merchants.approve')
                                                    <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="approve({{ $merchant->id }})">Approve</button>
                                                    @endcan
                                                @endif
                                            @endif
                                            <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                                <button type="button" @click="open = !open" class="pk-btn-secondary px-2 py-1.5 text-xs">
                                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                                                </button>
                                                <div x-show="open" x-cloak class="absolute right-0 z-50 mt-1 w-48 rounded-xl border border-slate-100 bg-white py-1 shadow-lg">
                                                    @can('merchants.reject')
                                                    <button type="button" wire:click="reject({{ $merchant->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50">Reject</button>
                                                    @endcan
                                                    @can('merchants.edit')
                                                    <button
                                        type="button"
                                        wire:click="resetPassword({{ $merchant->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="resetPassword({{ $merchant->id }})"
                                        class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                                        <svg wire:loading.remove wire:target="resetPassword({{ $merchant->id }})" class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                        <svg wire:loading wire:target="resetPassword({{ $merchant->id }})" class="h-3.5 w-3.5 shrink-0 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span wire:loading.remove wire:target="resetPassword({{ $merchant->id }})">Reset Password</span>
                                        <span wire:loading wire:target="resetPassword({{ $merchant->id }})">Resetting…</span>
                                    </button>
                                                    <button type="button" wire:click="cancelSubscription({{ $merchant->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-slate-50">Cancel Plan</button>
                                                    @endcan
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-admin.table.wrapper>
            </div>
            <div class="mt-3">{{ $merchants->links() }}</div>
        </div>
    </section>
</div>
