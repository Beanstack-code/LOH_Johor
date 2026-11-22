<?php

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\PaymentTransaction;
use App\Models\PointTransaction;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Notifications\AppUserWelcomeNotification;
use App\Services\AppUserPasswordService;
use App\Support\MemberContactValidation;
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
    public string $ageFilter = '';
    public string $cityFilter = '';
    public string $countryFilter = '';
    public string $insuranceClaimFilter = '';
    public string $sortField = 'full_name';
    public string $sortDirection = 'asc';
    public string $successMessage = '';
    public string $toastType = 'success';

    public ?int $memberId = null;
    public string $full_name = '';
    public string $display_name = '';
    public string $email = '';
    public string $phone = '';

    public string $phonePrefix = '+60';

    public string $phoneLocal = '';

    public string $ic_number = '';
    public string $street_address = '';
    public string $city = '';
    public string $postcode = '';
    public string $country = 'Malaysia';
    public string $insurance_claim_status = 'unclaimed';
    public int $pointsAdjustment = 0;
    public string $adjustReason = '';
    public string $referenceNumber = '';
    public ?int $selectedPlanId = null;
    public string $subscriptionNotes = '';
    public int $extensionDays = 30;
    public ?string $existingIcFrontImageUrl = null;
    public ?string $existingIcBackImageUrl = null;
    public $icFrontImage = null;
    public $icBackImage = null;
    public ?string $lastCreatedPassword = null;
    public ?string $lastResetTempPassword = null;

    public function with(): array
    {
        $allowed = ['full_name', 'status', 'subscription_state', 'subscription_ends_at', 'total_points'];
        $sortField = in_array($this->sortField, $allowed, true) ? $this->sortField : 'full_name';
        $sortDirection = $this->sortDirection === 'desc' ? 'desc' : 'asc';

        return [
            'members' => Member::query()
                ->with('activeSubscription.plan')
                ->when($this->search !== '', fn ($q) => $q->where('full_name', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%'))
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->subscriptionFilter !== '', fn ($q) => $q->where('subscription_state', $this->subscriptionFilter))
                ->when($this->joinedYearFilter !== '', fn ($q) => $q->whereYear('created_at', (int) $this->joinedYearFilter))
                ->when($this->cityFilter !== '', fn ($q) => $q->where('city', $this->cityFilter))
                ->when($this->countryFilter !== '', fn ($q) => $q->where('country', $this->countryFilter))
                ->when(
                    $this->insuranceClaimFilter !== '' && in_array($this->insuranceClaimFilter, Member::INSURANCE_STATUSES, true),
                    fn ($q) => $q->where('insurance_claim_status', $this->insuranceClaimFilter)
                )
                ->when($this->ageFilter !== '', function ($q) {
                    $ageExpr = "TIMESTAMPDIFF(YEAR, STR_TO_DATE(CONCAT(CASE WHEN CAST(SUBSTRING(REPLACE(ic_number, '-', ''), 1, 2) AS UNSIGNED) <= CAST(DATE_FORMAT(CURDATE(), '%y') AS UNSIGNED) THEN '20' ELSE '19' END, SUBSTRING(REPLACE(ic_number, '-', ''), 1, 2), '-', SUBSTRING(REPLACE(ic_number, '-', ''), 3, 2), '-', SUBSTRING(REPLACE(ic_number, '-', ''), 5, 2)), '%Y-%m-%d'), CURDATE())";
                    $q->whereNotNull('ic_number')
                        ->whereRaw("CHAR_LENGTH(REPLACE(ic_number, '-', '')) >= 6");

                    if ($this->ageFilter === '60_plus') {
                        $q->whereRaw($ageExpr.' >= 60');
                    } elseif (str_contains($this->ageFilter, '-')) {
                        [$minAge, $maxAge] = array_map('intval', explode('-', $this->ageFilter, 2));
                        $q->whereRaw($ageExpr.' BETWEEN ? AND ?', [$minAge, $maxAge]);
                    }
                })
                ->orderBy($sortField, $sortDirection)
                ->paginate(10),
            'plans' => SubscriptionPlan::query()
                ->whereIn('audience', ['member', 'both'])
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'joinedYears' => Member::query()
                ->selectRaw('YEAR(created_at) as joined_year, COUNT(*) as total')
                ->whereNotNull('created_at')
                ->groupBy('joined_year')
                ->orderByDesc('joined_year')
                ->get()
                ->mapWithKeys(fn ($row) => [(string) $row->joined_year => (int) $row->total]),
            'memberCities' => Member::query()
                ->whereNotNull('city')
                ->where('city', '!=', '')
                ->distinct()
                ->orderBy('city')
                ->pluck('city'),
            'memberCountries' => Member::query()
                ->whereNotNull('country')
                ->where('country', '!=', '')
                ->distinct()
                ->orderBy('country')
                ->pluck('country'),
            'memberSubscriptionHistory' => $this->memberId
                ? Subscription::query()
                    ->where('subscribable_type', Member::class)
                    ->where('subscribable_id', $this->memberId)
                    ->with(['plan.descriptions', 'assignedBy:id,name'])
                    ->orderByDesc('starts_at')
                    ->orderByDesc('id')
                    ->get()
                : collect(),
            'memberSubscriptionPayments' => $this->memberId
                ? PaymentTransaction::query()
                    ->where('subscribable_type', Member::class)
                    ->where('subscribable_id', $this->memberId)
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
        $allowed = ['full_name', 'status', 'subscription_state', 'subscription_ends_at', 'total_points'];
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
        if ($this->sortField !== $field) return '';
        return $this->sortDirection === 'asc' ? '↑' : '↓';
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedSubscriptionFilter(): void { $this->resetPage(); }
    public function updatedJoinedYearFilter(): void { $this->resetPage(); }
    public function updatedAgeFilter(): void { $this->resetPage(); }
    public function updatedCityFilter(): void { $this->resetPage(); }
    public function updatedCountryFilter(): void { $this->resetPage(); }
    public function updatedInsuranceClaimFilter(): void { $this->resetPage(); }

    private function syncComposedPhoneFromParts(): void
    {
        $this->phone = MemberContactValidation::composePhoneFromPrefixAndLocal($this->phonePrefix, $this->phoneLocal);
        $this->phone = MemberContactValidation::normalizeMalaysianPhone($this->phone);
    }

    private function revalidatePhoneField(): void
    {
        $this->resetValidation('phone');
        if ($this->phone !== '') {
            $msg = MemberContactValidation::phoneError($this->phone);
            if ($msg !== null) {
                $this->addError('phone', $msg);
            }
        }
    }

    private function revalidateIcField(): void
    {
        $this->resetValidation('ic_number');
        if (trim($this->ic_number) !== '') {
            $msg = MemberContactValidation::icNumberError($this->ic_number);
            if ($msg !== null) {
                $this->addError('ic_number', $msg);
            }
        }
    }

    public function updatedPhoneLocal(): void
    {
        $max = $this->phonePrefix === '+65' ? 8 : 10;
        $formatted = MemberContactValidation::formatPhoneLocalDigits($this->phoneLocal, $max);
        if ($formatted !== $this->phoneLocal) {
            $this->phoneLocal = $formatted;
        }
        $this->syncComposedPhoneFromParts();
        $this->revalidatePhoneField();
    }

    public function updatedPhonePrefix(): void
    {
        $max = $this->phonePrefix === '+65' ? 8 : 10;
        $digits = preg_replace('/\D/', '', $this->phoneLocal) ?? '';
        if (strlen($digits) > $max) {
            $digits = substr($digits, 0, $max);
        }
        $this->phoneLocal = MemberContactValidation::formatPhoneLocalDigits($digits, $max);
        $this->syncComposedPhoneFromParts();
        $this->revalidatePhoneField();
    }

    public function updatedIcNumber(): void
    {
        $n = MemberContactValidation::normalizeMalaysianIc($this->ic_number);
        if ($n !== $this->ic_number) {
            $this->ic_number = $n;
        }
        $this->revalidateIcField();
    }

    public function saveMember(): void
    {
        $isNew = ! $this->memberId;
        abort_unless(auth()->user()->can($isNew ? 'members.create' : 'members.edit'), 403);

        if (is_array($this->icFrontImage)) {
            $this->icFrontImage = $this->icFrontImage[0] ?? null;
        }
        if (is_array($this->icBackImage)) {
            $this->icBackImage = $this->icBackImage[0] ?? null;
        }

        $this->syncComposedPhoneFromParts();
        $this->ic_number = MemberContactValidation::normalizeMalaysianIc($this->ic_number);

        $this->validate([
            'full_name' => ['required', 'string'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', \Illuminate\Validation\Rule::unique('members', 'email')->ignore($this->memberId)],
            'phone' => [
                'nullable',
                'string',
                'max:25',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $msg = MemberContactValidation::phoneError($value);
                    if ($msg !== null) {
                        $fail($msg);
                    }
                },
            ],
            'ic_number' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $msg = MemberContactValidation::icNumberError(is_string($value) ? $value : null);
                    if ($msg !== null) {
                        $fail($msg);
                    }
                },
            ],
            'insurance_claim_status' => ['required', \Illuminate\Validation\Rule::in(\App\Models\Member::INSURANCE_STATUSES)],
            'icFrontImage' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
            'icBackImage' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:10240'],
        ]);

        $existingMember = $this->memberId ? Member::find($this->memberId) : null;
        $payload = [
            'full_name' => $this->full_name,
            'display_name' => trim($this->display_name) !== '' ? trim($this->display_name) : null,
            'email' => $this->email,
            'phone' => $this->phone,
            'ic_number' => trim($this->ic_number) !== '' ? trim($this->ic_number) : null,
            'street_address' => $this->street_address,
            'city' => $this->city,
            'postcode' => $this->postcode,
            'country' => $this->country,
            'insurance_claim_status' => $this->insurance_claim_status,
        ];

        if ($this->icFrontImage) {
            if ($existingMember?->ic_front_image) {
                Storage::disk('public')->delete($existingMember->ic_front_image);
            }
            $payload['ic_front_image'] = $this->icFrontImage->store('members/ic', 'public');
        }

        if ($this->icBackImage) {
            if ($existingMember?->ic_back_image) {
                Storage::disk('public')->delete($existingMember->ic_back_image);
            }
            $payload['ic_back_image'] = $this->icBackImage->store('members/ic', 'public');
        }

        if (! $this->memberId) {
            $payload['status'] = 'pending_subscription';
            $payload['subscription_state'] = 'pending_subscription';
            $generatedPassword = $this->generatePasswordFromEmail($this->email);
            $payload['password'] = $generatedPassword;
            $payload['temp_password_issued_at'] = now();
        }

        $member = Member::updateOrCreate(['id' => $this->memberId], $payload);
        $this->showToast($isNew ? 'Member created successfully.' : 'Member updated successfully.');

        if ($isNew) {
            $this->lastCreatedPassword = $generatedPassword ?? null;
            try {
                $token = Password::broker('members')->createToken($member);
                $member->notify(new AppUserWelcomeNotification($token, 'member'));
            } catch (\Exception) {
            }
            // Keep modal open to show the generated password
        } else {
            $this->lastCreatedPassword = null;
            $this->js("window.dispatchEvent(new CustomEvent('member-modal-close'))");
        }

        $this->reset(['memberId', 'full_name', 'display_name', 'email', 'phone', 'phonePrefix', 'phoneLocal', 'ic_number', 'street_address', 'city', 'postcode', 'insurance_claim_status', 'icFrontImage', 'icBackImage']);
        $this->country = 'Malaysia';
        $this->insurance_claim_status = 'unclaimed';
        $this->existingIcFrontImageUrl = null;
        $this->existingIcBackImageUrl = null;
    }

    public function editMember(int $id): void
    {
        $member = Member::findOrFail($id);
        $this->resetValidation();
        $this->memberId = $id;
        $this->full_name = $member->full_name;
        $this->display_name = (string) ($member->display_name ?? '');
        $this->email = $member->email;
        [$this->phonePrefix, $this->phoneLocal] = MemberContactValidation::splitStoredPhoneForForm((string) $member->phone);
        $this->syncComposedPhoneFromParts();
        $this->ic_number = MemberContactValidation::normalizeMalaysianIc((string) ($member->ic_number ?? ''));
        $this->street_address = (string) $member->street_address;
        $this->city = (string) $member->city;
        $this->postcode = (string) $member->postcode;
        $this->country = $member->country ?: 'Malaysia';
        $this->insurance_claim_status = $member->insurance_claim_status ?? 'unclaimed';
        $this->existingIcFrontImageUrl = $member->ic_front_image_url;
        $this->existingIcBackImageUrl = $member->ic_back_image_url;
        $this->icFrontImage = null;
        $this->icBackImage = null;
        $this->lastCreatedPassword = $member->temp_password_issued_at
            ? $this->generatePasswordFromEmail($member->email)
            : null;
        $this->revalidatePhoneField();
        $this->revalidateIcField();
        $this->js("window.dispatchEvent(new CustomEvent('member-modal-open', { detail: { tab: 'profile' } }))");
    }

    public function toggleStatus(int $id): void
    {
        $member = Member::findOrFail($id);
        if ($member->status === 'active') {
            abort_unless(auth()->user()->can('members.suspend'), 403);
            $member->status = 'suspended';
            $member->save();
            // Start the 365-day admin-fee clock from the moment of admin suspension.
            $member->recordEligibilityEnded();
            $this->showToast('Member suspended.', 'success');
        } elseif ($member->status === 'suspended') {
            abort_unless(auth()->user()->can('members.activate'), 403);
            $member->status = 'active';
            $member->save();
            $this->showToast('Member activated.', 'success');
        } elseif ($member->status === 'deactivated') {
            abort_unless(auth()->user()->can('members.activate'), 403);
            $member->status = 'active';
            $member->save();
            $this->showToast('Member reactivated.', 'success');
        }
    }

    public function resetPassword(int $id): void
    {
        abort_unless(auth()->user()->can('members.edit'), 403);
        $member = Member::findOrFail($id);

        $result = AppUserPasswordService::resetByAdmin($member, 'member');
        $this->lastResetTempPassword = $result['password'];

        if (! $result['email_sent']) {
            $this->showToast('Password reset. Email could not be sent — share the temporary password manually.', 'error');

            return;
        }

        $this->showToast('Password reset. Temporary password emailed to member.');
    }

    public function adjustPoints(int $id): void
    {
        abort_unless(auth()->user()->can('members.edit'), 403);

        $this->validate([
            'pointsAdjustment' => ['required', 'integer', 'not_in:0'],
            'adjustReason' => ['required', 'string'],
            'referenceNumber' => ['required', 'string'],
        ]);

        $member = Member::findOrFail($id);
        $before = $member->total_points;
        $member->total_points += $this->pointsAdjustment;
        $member->save();

        PointTransaction::create([
            'member_id' => $member->id,
            'type' => 'manual_adjustment',
            'points' => $this->pointsAdjustment,
            'reference_number' => $this->referenceNumber,
            'reason' => $this->adjustReason,
            'status' => 'completed',
            'occurred_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'member_points_adjusted',
            'entity_type' => Member::class,
            'entity_id' => $member->id,
            'before_data' => ['total_points' => $before],
            'after_data' => ['total_points' => $member->total_points],
            'reason' => $this->adjustReason,
            'reference_number' => $this->referenceNumber,
        ]);

        $this->reset(['pointsAdjustment', 'adjustReason', 'referenceNumber']);
        $this->showToast('Points adjusted successfully.');
    }

    public function assignSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('members.edit'), 403);

        $this->validate([
            'selectedPlanId' => ['required', 'exists:subscription_plans,id'],
        ]);

        $member = Member::findOrFail($id);
        $plan = SubscriptionPlan::findOrFail($this->selectedPlanId);
        $startsAt = now();
        $endsAt = $plan->calculateEndDate($startsAt);

        $subscription = Subscription::create([
            'subscription_plan_id' => $plan->id,
            'subscribable_type' => Member::class,
            'subscribable_id' => $member->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'active',
            'assigned_by' => auth()->id(),
            'notes' => $this->subscriptionNotes ?: 'Assigned by admin',
        ]);

        $member->refreshSubscriptionState();
        if (empty($member->membership_id)) {
            $member->membership_id = Member::generateMembershipId();
            $member->save();
        }

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'member_subscription_assigned',
            'entity_type' => Member::class,
            'entity_id' => $member->id,
            'after_data' => ['subscription_id' => $subscription->id, 'plan' => $plan->name],
            'reason' => $this->subscriptionNotes ?: null,
        ]);
        $this->showToast('Subscription plan assigned successfully.');
    }

    public function extendSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('members.edit'), 403);

        $this->validate([
            'extensionDays' => ['required', 'integer', 'min:1'],
        ]);

        $member = Member::findOrFail($id);
        $active = $member->activeSubscription()->first();
        if (! $active) {
            return;
        }

        $beforeEndsAt = $active->ends_at;
        $active->ends_at = ($active->ends_at ?? now())->addDays($this->extensionDays);
        $active->save();

        $member->refreshSubscriptionState();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'member_subscription_extended',
            'entity_type' => Member::class,
            'entity_id' => $member->id,
            'before_data' => ['ends_at' => $beforeEndsAt],
            'after_data' => ['ends_at' => $active->ends_at],
        ]);
        $this->showToast('Subscription extended successfully.');
    }

    public function cancelSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('members.edit'), 403);

        $member = Member::findOrFail($id);
        $active = $member->activeSubscription()->first();
        if (! $active) {
            return;
        }

        $active->status = 'cancelled';
        $active->save();

        $member->refreshSubscriptionState();

        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'member_subscription_cancelled',
            'entity_type' => Member::class,
            'entity_id' => $member->id,
            'after_data' => ['subscription_id' => $active->id, 'status' => 'cancelled'],
        ]);
        $this->showToast('Subscription cancelled.');
    }

    public function startCreate(): void
    {
        $this->resetValidation();
        $this->reset(['memberId', 'full_name', 'display_name', 'email', 'phone', 'phonePrefix', 'phoneLocal', 'ic_number',
            'street_address', 'city', 'postcode', 'insurance_claim_status',
            'selectedPlanId', 'subscriptionNotes', 'extensionDays',
            'pointsAdjustment', 'adjustReason', 'referenceNumber',
            'existingIcFrontImageUrl', 'existingIcBackImageUrl',
            'icFrontImage', 'icBackImage', 'lastCreatedPassword']);
        $this->country = 'Malaysia';
        $this->insurance_claim_status = 'unclaimed';
        $this->extensionDays = 30;
        $this->js("window.dispatchEvent(new CustomEvent('member-modal-open', { detail: { tab: 'profile' } }))");
    }

    private function showToast(string $message, string $type = 'success'): void
    {
        $this->successMessage = $message;
        $this->toastType = $type === 'error' ? 'error' : 'success';
    }

    private function generatePasswordFromEmail(string $email): string
    {
        return AppUserPasswordService::generateFromEmail($email, 'member');
    }

    public function temporaryPasswordFor(Member $member): ?string
    {
        if (! $member->temp_password_issued_at) {
            return null;
        }

        return $this->generatePasswordFromEmail($member->email);
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="pk-page-title">Member Management</h1>
            <p class="pk-subtitle">Search, manage profiles, subscriptions, and points.</p>
        </div>
        @can('members.create')
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('member-create-request'))"
            class="pk-btn-primary shrink-0"
        >
            New Member
        </button>
        @endcan
    </div>
</x-slot>

<div class="space-y-5" x-data @member-create-request.window="$wire.startCreate()">

    {{-- ── Toast ──────────────────────────────────────────────── --}}
    <div
        x-data="{ message: $wire.entangle('successMessage'), type: $wire.entangle('toastType') }"
        x-cloak
        class="pointer-events-none fixed right-3 z-[150]"
        style="bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px)); width: calc(100vw - 1.5rem); max-width: 22rem;"
    >
        <div
            x-show="message"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="pointer-events-auto flex items-center justify-between gap-3 rounded-xl border p-3.5 text-sm shadow-lg"
            :style="type === 'error'
                ? 'border-color:#fecdd3;background:#fff1f2;color:#9f1239;'
                : 'border-color:#bbf7d0;background:#f0fdf4;color:#166534;'"
            x-init="$watch('message', v => { if (v) setTimeout(() => message = '', 3000) })"
        >
            <div class="flex items-center gap-2">
                <svg x-show="type !== 'error'" class="h-4 w-4 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="type === 'error'" class="h-4 w-4 shrink-0 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <p x-text="message" class="font-medium"></p>
            </div>
            <button type="button" @click="message = ''" class="shrink-0 text-xs font-semibold opacity-60 hover:opacity-100">✕</button>
        </div>
    </div>

    @if($lastResetTempPassword)
        <div class="flex items-start justify-between gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <div>
                <p class="font-semibold">Password reset</p>
                <p class="mt-1">Temporary password: <span class="font-mono font-bold">{{ $lastResetTempPassword }}</span> — also emailed to the member.</p>
            </div>
            <button type="button" wire:click="$set('lastResetTempPassword', null)" class="shrink-0 text-xs font-semibold text-emerald-700 hover:text-emerald-900">Dismiss</button>
        </div>
    @endif

    {{-- ── Filters ─────────────────────────────────────────────── --}}
    <section class="pk-filter-bar">
        <input wire:model.live="search" class="pk-input w-full sm:max-w-sm" placeholder="Search by name or email">
        <select wire:model.live="statusFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="suspended">Suspended</option>
            <option value="deactivated">Deactivated</option>
            <option value="pending_subscription">Pending Subscription</option>
        </select>
        <select wire:model.live="subscriptionFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Subscription State</option>
            <option value="active_subscription">Active Subscription</option>
            <option value="pending_subscription">Pending Subscription</option>
        </select>
        <select wire:model.live="insuranceClaimFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Insurance Claim</option>
            <option value="unclaimed">Not Claimed</option>
            <option value="claimed">Claimed</option>
            <option value="unavailable">Unavailable</option>
        </select>
        <select wire:model.live="ageFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Age</option>
            <option value="18-24">18 - 24</option>
            <option value="25-34">25 - 34</option>
            <option value="35-44">35 - 44</option>
            <option value="45-54">45 - 54</option>
            <option value="55-59">55 - 59</option>
            <option value="60_plus">60+</option>
        </select>
        <select wire:model.live="cityFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All City</option>
            @foreach($memberCities as $city)
                <option value="{{ $city }}">{{ $city }}</option>
            @endforeach
        </select>
        <select wire:model.live="countryFilter" class="pk-input w-full sm:max-w-xs">
            <option value="">All Country</option>
            @foreach($memberCountries as $country)
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

    {{-- ── Table Card ──────────────────────────────────────────── --}}
    <div class="pk-card overflow-hidden">

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/70 text-left text-xs font-semibold tracking-wide text-slate-500">
                        <th class="px-5 py-3.5">
                            <button type="button" wire:click="sortBy('full_name')" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Member <span class="text-[#E31E24]">{{ $this->sortIcon('full_name') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Membership ID</th>
                        <th class="px-4 py-3.5">
                            <button type="button" wire:click="sortBy('status')" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Status <span class="text-[#E31E24]">{{ $this->sortIcon('status') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3.5">
                            <button type="button" wire:click="sortBy('subscription_ends_at')" class="inline-flex items-center gap-1 hover:text-slate-700">
                                Subscription <span class="text-[#E31E24]">{{ $this->sortIcon('subscription_ends_at') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3.5 text-right whitespace-nowrap">
                            <button type="button" wire:click="sortBy('total_points')" class="inline-flex items-center gap-1 hover:text-slate-700 ml-auto">
                                Points <span class="text-[#E31E24]">{{ $this->sortIcon('total_points') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Insurance Claim</th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Joined Date</th>
                        <th class="px-4 py-3.5 whitespace-nowrap">Temp Password</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse($members as $member)
                    @php
                        $sub  = $member->activeSubscription;
                        $plan = $sub?->plan;
                        $ends = $member->subscription_ends_at;
                        $days = $ends ? (int) now()->diffInDays($ends, false) : null;
                        $dur  = $plan ? $plan->duration_value.' '.ucfirst($plan->duration_unit).($plan->duration_value > 1 ? 's' : '') : null;
                        $isActive  = $ends && $days > 0;
                    @endphp
                    <tr class="group transition hover:bg-slate-50/60">
                        {{-- Member --}}
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-bold text-slate-500">
                                    {{ strtoupper(substr($member->full_name, 0, 1)) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-slate-800">{{ $member->full_name }}</p>
                                    <p class="truncate text-xs text-slate-500">{{ $member->email }}</p>
                                </div>
                            </div>
                        </td>
                        {{-- Membership ID --}}
                        <td class="px-4 py-4 whitespace-nowrap">
                            @if($member->membership_id)
                                <span class="rounded-md bg-rose-50 px-2 py-1 font-mono text-xs font-bold text-[#E31E24]">{{ $member->membership_id }}</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        {{-- Status --}}
                        <td class="px-4 py-4 whitespace-nowrap">
                            <x-admin.status-badge :status="$member->status" />
                        </td>
                        {{-- Subscription --}}
                        <td class="px-4 py-4" style="min-width:200px;">
                            <x-admin.status-badge :status="$member->subscription_state" />
                            @if($ends)
                            <div class="mt-1.5 rounded-lg border px-2.5 py-1.5 {{ $isActive ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                                @if($plan)
                                    <p class="text-xs font-semibold {{ $isActive ? 'text-emerald-800' : 'text-rose-700' }}">
                                        {{ $plan->name }}@if($dur)<span class="font-normal {{ $isActive ? 'text-emerald-600' : 'text-rose-500' }}"> · {{ $dur }}</span>@endif
                                    </p>
                                @endif
                                <p class="text-xs {{ $isActive ? 'text-emerald-700' : 'text-rose-600' }}">Ends {{ $ends->format('d M Y') }}</p>
                                @if($isActive)
                                    <div class="mt-0.5 flex items-center gap-1 text-xs font-medium text-emerald-600">
                                        <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        {{ $days }} days remaining
                                    </div>
                                @else
                                    <p class="mt-0.5 text-xs font-medium text-rose-600">Expired</p>
                                @endif
                            </div>
                            @endif
                        </td>
                        {{-- Points --}}
                        <td class="px-4 py-4 text-right whitespace-nowrap">
                            <span class="font-semibold text-slate-700">{{ number_format($member->total_points) }}</span>
                            <span class="text-xs text-slate-400"> pts</span>
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            @if($member->insurance_claim_status === 'claimed')
                                <span class="pk-badge-success">Claimed</span>
                            @elseif($member->insurance_claim_status === 'unavailable')
                                <span class="pk-badge-warning">Unavailable</span>
                            @else
                                <span class="pk-badge-danger">Not Claimed</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap text-xs text-slate-600">
                            {{ optional($member->created_at)->format('d M Y') ?: '-' }}
                        </td>
                        <td class="px-4 py-4 whitespace-nowrap">
                            @if($temp = $this->temporaryPasswordFor($member))
                                <span class="pk-badge-warning font-mono text-[11px] tracking-tight">{{ $temp }}</span>
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                        {{-- Actions --}}
                        <td class="px-5 py-4 text-right whitespace-nowrap">
                            <div class="inline-flex items-center gap-1.5">
                                <button
                                    wire:click="editMember({{ $member->id }})"
                                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm transition hover:bg-slate-50 hover:border-slate-300">
                                    View / Edit
                                </button>
                                @if($member->status === 'active')
                                    @can('members.suspend')
                                    <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#dc2626" wire:click="toggleStatus({{ $member->id }})">Suspend</button>
                                    @endcan
                                @elseif($member->status === 'suspended')
                                    @can('members.activate')
                                    <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="toggleStatus({{ $member->id }})">Activate</button>
                                    @endcan
                                @elseif($member->status === 'deactivated')
                                    @can('members.activate')
                                    <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="toggleStatus({{ $member->id }})">Reactivate</button>
                                    @endcan
                                @endif
                                {{-- More menu --}}
                                <div class="relative" x-data="{ open: false }" @click.away="open = false">
                                    <button @click="open = !open"
                                        class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-500 shadow-sm transition hover:bg-slate-50">
                                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/>
                                        </svg>
                                    </button>
                                    <div x-show="open" x-cloak class="absolute right-0 z-50 mt-1 w-48 rounded-xl border border-slate-100 bg-white py-1 shadow-xl">
                                        @can('members.edit')
                                        <button
                                            type="button"
                                            wire:click="resetPassword({{ $member->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="resetPassword({{ $member->id }})"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                                            <svg wire:loading.remove wire:target="resetPassword({{ $member->id }})" class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                            <svg wire:loading wire:target="resetPassword({{ $member->id }})" class="h-3.5 w-3.5 shrink-0 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            <span wire:loading.remove wire:target="resetPassword({{ $member->id }})">Reset Password</span>
                                            <span wire:loading wire:target="resetPassword({{ $member->id }})">Resetting…</span>
                                        </button>
                                        <div class="my-1 border-t border-slate-100"></div>
                                        <button wire:click="cancelSubscription({{ $member->id }})" @click="open = false"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            Cancel Plan
                                        </button>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-5 py-16 text-center text-sm text-slate-400">
                            <div class="flex flex-col items-center gap-2">
                                <svg class="h-10 w-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <p class="font-medium">No members found</p>
                                <p class="text-xs">Try adjusting your search or filters</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Mobile cards --}}
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($members as $member)
            @php
                $mSub  = $member->activeSubscription;
                $mPlan = $mSub?->plan;
                $mEnds = $member->subscription_ends_at;
                $mDays = $mEnds ? (int) now()->diffInDays($mEnds, false) : null;
                $mDur  = $mPlan ? $mPlan->duration_value.' '.ucfirst($mPlan->duration_unit).($mPlan->duration_value > 1 ? 's' : '') : null;
                $mIsActive = $mEnds && $mDays > 0;
            @endphp
            <article class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 font-bold text-slate-500">
                            {{ strtoupper(substr($member->full_name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-800">{{ $member->full_name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $member->email }}</p>
                            @if($member->membership_id)
                                <span class="mt-0.5 inline-block font-mono text-xs font-bold text-[#E31E24]">{{ $member->membership_id }}</span>
                            @endif
                        </div>
                    </div>
                    <x-admin.status-badge :status="$member->status" />
                </div>

                @if($mEnds)
                <div class="mt-3 rounded-lg border px-3 py-2 {{ $mIsActive ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }}">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            @if($mPlan)<p class="text-xs font-semibold {{ $mIsActive ? 'text-emerald-800' : 'text-rose-700' }}">{{ $mPlan->name }}@if($mDur)<span class="font-normal"> · {{ $mDur }}</span>@endif</p>@endif
                            <p class="text-xs {{ $mIsActive ? 'text-emerald-700' : 'text-rose-600' }}">Ends {{ $mEnds->format('d M Y') }}</p>
                        </div>
                        @if($mIsActive)
                            <span class="shrink-0 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $mDays }}d left</span>
                        @else
                            <span class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-700">Expired</span>
                        @endif
                    </div>
                </div>
                @endif

                <div class="mt-3 flex items-center justify-between gap-2">
                    <div class="space-y-1">
                        <span class="block text-xs text-slate-500"><span class="font-semibold text-slate-700">{{ number_format($member->total_points) }}</span> pts</span>
                        @if($member->insurance_claim_status === 'claimed')
                            <span class="pk-badge-success">Insurance: Claimed</span>
                        @elseif($member->insurance_claim_status === 'unavailable')
                            <span class="pk-badge-warning">Insurance: Unavailable</span>
                        @else
                            <span class="pk-badge-danger">Insurance: Not Claimed</span>
                        @endif
                        <span class="block text-xs text-slate-500">Joined: {{ optional($member->created_at)->format('d M Y') ?: '-' }}</span>
                        <span class="block text-xs text-slate-500">Temp password:
                            @if($temp = $this->temporaryPasswordFor($member))
                                <span class="pk-badge-warning ml-1 font-mono text-[11px] tracking-tight">{{ $temp }}</span>
                            @else
                                —
                            @endif
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button wire:click="editMember({{ $member->id }})"
                            class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-sm">
                            View / Edit
                        </button>
                        @if($member->status === 'active')
                            @can('members.suspend')
                            <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#dc2626" wire:click="toggleStatus({{ $member->id }})">Suspend</button>
                            @endcan
                        @elseif($member->status === 'suspended')
                            @can('members.activate')
                            <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="toggleStatus({{ $member->id }})">Activate</button>
                            @endcan
                        @elseif($member->status === 'deactivated')
                            @can('members.activate')
                            <button class="pk-btn-secondary px-3 py-1.5 text-xs" style="color:#16a34a" wire:click="toggleStatus({{ $member->id }})">Reactivate</button>
                            @endcan
                        @endif
                        <div class="relative" x-data="{ open: false }" @click.away="open = false">
                            <button @click="open = !open" class="rounded-lg border border-slate-200 bg-white p-1.5 text-slate-500">
                                <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>
                            </button>
                            <div x-show="open" x-cloak class="absolute right-0 z-50 mt-1 w-48 rounded-xl border border-slate-100 bg-white py-1 shadow-xl">
                                @can('members.edit')
                                <button
                                    type="button"
                                    wire:click="resetPassword({{ $member->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="resetPassword({{ $member->id }})"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60">
                                    <svg wire:loading.remove wire:target="resetPassword({{ $member->id }})" class="h-3.5 w-3.5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    <svg wire:loading wire:target="resetPassword({{ $member->id }})" class="h-3.5 w-3.5 shrink-0 animate-spin text-slate-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                    <span wire:loading.remove wire:target="resetPassword({{ $member->id }})">Reset Password</span>
                                    <span wire:loading wire:target="resetPassword({{ $member->id }})">Resetting…</span>
                                </button>
                                <button wire:click="cancelSubscription({{ $member->id }})" @click="open = false" class="block w-full px-4 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Cancel Plan</button>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            </article>
            @empty
            <div class="py-16 text-center text-sm text-slate-400">No members found.</div>
            @endforelse
        </div>

        {{-- Pagination --}}
        <div class="border-t border-slate-100 px-5 py-3">{{ $members->links() }}</div>
    </div>

    {{-- ── Modal ───────────────────────────────────────────────── --}}
    <div
        x-data="{
            show: false,
            tab: 'profile'
        }"
        @member-modal-open.window="tab = ($event.detail && $event.detail.tab) ? $event.detail.tab : 'profile'; show = true"
        @member-modal-close.window="show = false"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 flex items-end justify-center sm:items-center sm:p-4"
        style="isolation: isolate;"
    >
        {{-- Backdrop --}}
        <div
            class="absolute inset-0 bg-black/40 backdrop-blur-sm"
            @click="show = false"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-end="opacity-0"
        ></div>

        {{-- Dialog --}}
        <div
            class="relative flex w-full flex-col bg-white shadow-2xl sm:max-w-2xl sm:rounded-2xl"
            style="max-height: 92dvh;"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0 translate-y-4"
            @click.stop
        >
            {{-- Modal Header --}}
            <div class="flex shrink-0 items-center justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800">
                        {{ $memberId ? 'Edit Member' : 'Create New Member' }}
                    </h2>
                    @if($memberId && $full_name)
                        <p class="mt-0.5 text-xs text-slate-500">{{ $full_name }}</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <button @click="show = false" class="flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            {{-- Tabs (edit mode only) --}}
            @if($memberId)
            <div class="shrink-0 border-b border-slate-100 px-6">
                <nav class="-mb-px flex gap-1">
                    @foreach(['profile' => 'Profile', 'subscription' => 'Subscription', 'points' => 'Points'] as $key => $label)
                    <button
                        @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}'
                            ? 'border-[#E31E24] text-[#E31E24] bg-rose-50/50'
                            : 'border-transparent text-slate-500 hover:text-slate-700 hover:bg-slate-50'"
                        class="rounded-t-lg border-b-2 px-4 py-3 text-sm font-medium transition">
                        {{ $label }}
                    </button>
                    @endforeach
                </nav>
            </div>
            @endif

            {{-- Scrollable body --}}
            <div class="flex-1 overflow-y-auto">

                {{-- ─── Profile Tab ─── --}}
                <div x-show="tab === 'profile'" class="space-y-4 p-6">
                    @if($errors->any())
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        Please fix the errors below.
                    </div>
                    @endif

                    @if($lastCreatedPassword)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Member created — temporary password</p>
                        <p class="mt-1 text-sm text-emerald-800">
                            Share this with the member:
                            <span class="ml-1 rounded bg-white px-2 py-0.5 font-mono font-bold text-emerald-900 shadow-sm">{{ $lastCreatedPassword }}</span>
                        </p>
                    </div>
                    @endif

                    <form wire:submit="saveMember" class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Full Name *</label>
                                <input wire:model="full_name" class="{{ $errors->has('full_name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="Full name">
                                @error('full_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Display Name</label>
                                <input wire:model="display_name" class="{{ $errors->has('display_name') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="Nickname or preferred name">
                                @error('display_name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Email *</label>
                                <input wire:model="email" type="email" class="{{ $errors->has('email') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="member@email.com">
                                @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Phone</label>
                                <div class="flex gap-2">
                                    <select
                                        wire:model.live="phonePrefix"
                                        class="pk-input w-[5.75rem] shrink-0 font-mono text-sm"
                                        aria-label="Country code"
                                    >
                                        <option value="+60">🇲🇾 +60</option>
                                        <option value="+65">🇸🇬 +65</option>
                                    </select>
                                    <div class="min-w-0 flex-1">
                                        <input
                                            wire:model.live.debounce.120ms="phoneLocal"
                                            type="tel"
                                            class="{{ $errors->has('phone') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }} w-full"
                                            placeholder="11-1681-0080"
                                            autocomplete="tel-national"
                                        >
                                        @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">Digits only; hyphens are added as you type (same as member registration).</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-slate-600">IC Number</label>
                                <input wire:model.live.debounce.120ms="ic_number" class="{{ $errors->has('ic_number') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }} w-full" placeholder="900101-01-1234" inputmode="numeric" autocomplete="off">
                                @error('ic_number')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-slate-600">Insurance Claim Status</label>
                                <select wire:model="insurance_claim_status" class="pk-input">
                                    <option value="unclaimed">Not Claimed</option>
                                    <option value="claimed">Claimed</option>
                                    <option value="unavailable">Unavailable</option>
                                </select>
                                <p class="mt-1 text-xs text-slate-400">Mark whether this member has claimed insurance.</p>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Address</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Street Address</label>
                                    <input wire:model="street_address" class="pk-input" placeholder="No. & Street name">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">City</label>
                                        <input wire:model="city" class="pk-input">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">Postcode</label>
                                        <input wire:model="postcode" class="pk-input">
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Country</label>
                                    <select wire:model="country" class="pk-input">
                                        <option value="">— Select —</option>
                                        @foreach(['Malaysia','Singapore','Indonesia','Thailand','Philippines','Vietnam','Myanmar','Cambodia','Brunei','Laos','China','Japan','South Korea','India','Bangladesh','Pakistan','Sri Lanka','Nepal','Australia','New Zealand','United Kingdom','United States','Canada','Germany','France','Netherlands','Other'] as $c)
                                            <option value="{{ $c }}">{{ $c }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">IC Documents</p>
                            <p class="mb-3 text-xs text-slate-400">Max 1 image per side (JPG, PNG, GIF, WEBP — 10 MB)</p>
                            <div class="grid grid-cols-2 gap-3">
                                @foreach(['Front' => ['icFrontImage', 'icFrontInput', 'existingIcFrontImageUrl'], 'Back' => ['icBackImage', 'icBackInput', 'existingIcBackImageUrl']] as $side => [$model, $ref, $existing])
                                <div>
                                    <p class="mb-1.5 text-xs font-medium text-slate-600">{{ $side }}</p>
                                    @if($model === 'icFrontImage' && $icFrontImage)
                                        <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                                            <img src="{{ $icFrontImage->temporaryUrl() }}" class="h-28 w-full object-cover">
                                        </div>
                                    @elseif($model === 'icBackImage' && $icBackImage)
                                        <div class="mb-2 overflow-hidden rounded-xl border border-slate-200">
                                            <img src="{{ $icBackImage->temporaryUrl() }}" class="h-28 w-full object-cover">
                                        </div>
                                    @elseif($model === 'icFrontImage' && $existingIcFrontImageUrl)
                                        <a href="{{ $existingIcFrontImageUrl }}" target="_blank" class="mb-2 block overflow-hidden rounded-xl border border-slate-200">
                                            <img src="{{ $existingIcFrontImageUrl }}" class="h-28 w-full object-cover">
                                        </a>
                                    @elseif($model === 'icBackImage' && $existingIcBackImageUrl)
                                        <a href="{{ $existingIcBackImageUrl }}" target="_blank" class="mb-2 block overflow-hidden rounded-xl border border-slate-200">
                                            <img src="{{ $existingIcBackImageUrl }}" class="h-28 w-full object-cover">
                                        </a>
                                    @endif
                                    <div
                                        x-data="{
                                            isDragging: false,
                                            handleDrop(e) {
                                                this.isDragging = false;
                                                const files = e.dataTransfer.files;
                                                if (!files.length) return;
                                                const input = $refs.{{ $ref }};
                                                const dt = new DataTransfer();
                                                dt.items.add(files[0]);
                                                input.files = dt.files;
                                                input.dispatchEvent(new Event('change'));
                                            }
                                        }"
                                        @dragover.prevent="isDragging = true"
                                        @dragleave.prevent="isDragging = false"
                                        @drop.prevent="handleDrop($event)"
                                        @click="$refs.{{ $ref }}.click()"
                                        :class="isDragging ? 'border-[#E31E24] bg-rose-50' : 'border-slate-200 bg-slate-50 hover:bg-slate-100'"
                                        class="cursor-pointer rounded-xl border-2 border-dashed p-4 text-center transition"
                                    >
                                        <svg class="mx-auto mb-1.5 h-6 w-6 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                        <p class="text-xs text-slate-500">Drop or <span class="font-semibold text-[#E31E24]">browse</span></p>
                                        <input x-ref="{{ $ref }}" type="file" wire:model="{{ $model }}" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden">
                                    </div>
                                    @if($model === 'icFrontImage')
                                        @error('icFrontImage')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    @else
                                        @error('icBackImage')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t border-slate-100 pt-4">
                            @if($memberId)
                                @can('members.edit')
                                <button type="submit" class="pk-btn-primary w-full" wire:loading.attr="disabled" wire:target="saveMember">
                                    <span wire:loading.remove wire:target="saveMember">Save Changes</span>
                                    <span wire:loading wire:target="saveMember" class="flex items-center justify-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Saving…
                                    </span>
                                </button>
                                @endcan
                            @else
                                @can('members.create')
                                <button type="submit" class="pk-btn-primary w-full" wire:loading.attr="disabled" wire:target="saveMember">
                                    <span wire:loading.remove wire:target="saveMember">Create Member</span>
                                    <span wire:loading wire:target="saveMember" class="flex items-center justify-center gap-2">
                                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        Creating…
                                    </span>
                                </button>
                                @endcan
                            @endif
                        </div>
                    </form>
                </div>

                {{-- ─── Subscription Tab ─── --}}
                @if($memberId)
                <div x-show="tab === 'subscription'" class="space-y-5 p-6">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Applying to member: <span class="font-semibold text-slate-800">{{ $full_name }}</span></p>
                    </div>

                    <div class="space-y-3">
                        <p class="text-sm font-semibold text-slate-700">Assign Plan</p>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Plan *</label>
                            <select wire:model="selectedPlanId" class="pk-input">
                                <option value="">Select plan…</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }} (RM {{ number_format((float) $plan->price, 2) }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Notes</label>
                            <input wire:model="subscriptionNotes" class="pk-input" placeholder="Optional notes">
                        </div>
                        @can('members.edit')
                        <button type="button" wire:click="assignSubscription({{ $memberId }})" class="pk-btn-primary w-full">Assign Plan</button>
                        @endcan
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-3">
                        <p class="text-sm font-semibold text-slate-700">Extend / Cancel Active Subscription</p>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Extension Days *</label>
                            <input type="number" wire:model="extensionDays" class="pk-input" min="1">
                        </div>
                        @can('members.edit')
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" wire:click="extendSubscription({{ $memberId }})" class="pk-btn-secondary py-2.5 text-sm">Extend</button>
                            <button type="button" wire:click="cancelSubscription({{ $memberId }})"
                                class="rounded-xl border border-rose-200 bg-rose-50 py-2.5 text-sm font-semibold text-rose-600 transition hover:bg-rose-100">
                                Cancel Plan
                            </button>
                        </div>
                        @endcan
                    </div>

                    <div class="border-t border-slate-100 pt-4 space-y-2">
                        <p class="text-sm font-semibold text-slate-800">Subscription records &amp; plan details</p>
                        <p class="text-xs text-slate-500">All subscription rows assigned to this member, with linked plan information.</p>
                        @if($memberSubscriptionHistory->isEmpty())
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
                                        @foreach($memberSubscriptionHistory as $subRow)
                                            @php
                                                $p = $subRow->plan;
                                                $dur = $p ? ($p->duration_value.' '.ucfirst($p->duration_unit).($p->duration_value > 1 ? 's' : '')) : '—';
                                            @endphp
                                            <tr class="align-top">
                                                <td class="px-3 py-2 font-medium text-slate-800">{{ $p?->name ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $p?->audience ?? '—' }}</td>
                                                <td class="px-3 py-2 text-slate-600">@if($p)RM {{ number_format((float) ($p->final_price ?? $p->price), 2) }}@else — @endif</td>
                                                <td class="px-3 py-2 text-slate-600">{{ $dur }}</td>
                                                <td class="px-3 py-2"><span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">{{ $subRow->status }}</span></td>
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

                    <div class="border-t border-slate-100 pt-4 space-y-2">
                        <p class="text-sm font-semibold text-slate-800">Payment gateway transactions</p>
                        <p class="text-xs text-slate-500">Gateway subscription payments for this member.</p>
                        @if($memberSubscriptionPayments->isEmpty())
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
                                        @foreach($memberSubscriptionPayments as $pay)
                                            <tr>
                                                <td class="px-3 py-2 whitespace-nowrap text-slate-700">{{ $pay->created_at?->format('d M Y H:i') }}</td>
                                                <td class="px-3 py-2"><span class="rounded-md bg-slate-100 px-1.5 py-0.5 font-medium">{{ $pay->status }}</span></td>
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
                </div>

                {{-- ─── Points Tab ─── --}}
                <div x-show="tab === 'points'" class="space-y-4 p-6">
                    <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">Adjusting points for: <span class="font-semibold text-slate-800">{{ $full_name }}</span></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Points (+/-) *</label>
                        <input type="number" wire:model="pointsAdjustment" class="{{ $errors->has('pointsAdjustment') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="e.g. 100 or -50">
                        @error('pointsAdjustment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Reason *</label>
                        <input wire:model="adjustReason" class="{{ $errors->has('adjustReason') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="e.g. Referral bonus">
                        @error('adjustReason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-slate-600">Reference No. *</label>
                        <input wire:model="referenceNumber" class="{{ $errors->has('referenceNumber') ? 'pk-input border-rose-400 ring-1 ring-rose-200' : 'pk-input' }}" placeholder="ADJ-{{ Str::upper(Str::random(4)) }}">
                        @error('referenceNumber')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    @can('members.edit')
                    <button type="button" wire:click="adjustPoints({{ $memberId }})" class="pk-btn-primary w-full">Apply Adjustment</button>
                    @endcan
                </div>
                @endif

            </div>{{-- end scrollable body --}}
        </div>{{-- end dialog --}}
    </div>{{-- end modal --}}

</div>
