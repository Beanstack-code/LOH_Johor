<?php

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\PaymentTransaction;
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
        $allowed = ['full_name', 'status', 'subscription_state', 'subscription_ends_at'];
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
                ->selectRaw("strftime('%Y', created_at) as joined_year, COUNT(*) as total")
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
        $allowed = ['full_name', 'status', 'subscription_state', 'subscription_ends_at'];
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

    public function assignSubscription(int $id): void
    {
        abort_unless(auth()->user()->can('members.edit'), 403);
        $this->validate(['selectedPlanId' => ['required', 'exists:subscription_plans,id']]);

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
        $this->validate(['extensionDays' => ['required', 'integer', 'min:1']]);

        $member = Member::findOrFail($id);
        $active = $member->activeSubscription()->first();
        if (! $active) return;

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
        if (! $active) return;

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
        $this->reset(['memberId', 'full_name', 'display_name', 'email', 'phone', 'phonePrefix', 'phoneLocal', 'ic_number', 'street_address', 'city', 'postcode', 'insurance_claim_status', 'selectedPlanId', 'subscriptionNotes', 'extensionDays', 'existingIcFrontImageUrl', 'existingIcBackImageUrl', 'icFrontImage', 'icBackImage', 'lastCreatedPassword']);
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
        return $member->temp_password_issued_at
            ? $this->generatePasswordFromEmail($member->email)
            : null;
    }
}; ?>

<x-slot name="header">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="pk-page-title">Member Management</h1>
            <p class="pk-subtitle">Search and manage member profiles and subscriptions.</p>
        </div>
        @can('members.create')
        <button type="button" onclick="window.dispatchEvent(new CustomEvent('member-create-request'))" class="pk-btn-primary shrink-0">New Member</button>
        @endcan
    </div>
</x-slot>

<div class="space-y-5" x-data @member-create-request.window="$wire.startCreate()">
    @if($successMessage)
        <div class="rounded-xl border px-4 py-3 text-sm {{ $toastType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700' }}">
            {{ $successMessage }}
        </div>
    @endif

    <section class="pk-card p-4">
        <div class="grid gap-3 md:grid-cols-5">
            <input type="text" wire:model.live.debounce.300ms="search" class="pk-input" placeholder="Search name or email">
            <select wire:model.live="statusFilter" class="pk-input">
                <option value="">All statuses</option>
                <option value="active">Active</option>
                <option value="pending_subscription">Pending subscription</option>
                <option value="suspended">Suspended</option>
                <option value="deactivated">Deactivated</option>
            </select>
            <select wire:model.live="subscriptionFilter" class="pk-input">
                <option value="">All subscriptions</option>
                <option value="active">Active</option>
                <option value="expired">Expired</option>
                <option value="pending_subscription">Pending</option>
            </select>
            <select wire:model.live="joinedYearFilter" class="pk-input">
                <option value="">All joined years</option>
                @foreach($joinedYears as $year => $total)
                    <option value="{{ $year }}">{{ $year }} ({{ $total }})</option>
                @endforeach
            </select>
            <select wire:model.live="insuranceClaimFilter" class="pk-input">
                <option value="">All insurance claims</option>
                @foreach(App\Models\Member::INSURANCE_STATUSES as $status)
                    <option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                @endforeach
            </select>
        </div>
    </section>

    <section class="pk-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-slate-500"><button type="button" wire:click="sortBy('full_name')">Member {{ $this->sortIcon('full_name') }}</button></th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Membership ID</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500"><button type="button" wire:click="sortBy('status')">Status {{ $this->sortIcon('status') }}</button></th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Subscription</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Insurance Claim</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($members as $member)
                        <tr class="hover:bg-slate-50/60">
                            <td class="px-5 py-4"><p class="font-semibold text-slate-800">{{ $member->full_name }}</p><p class="text-xs text-slate-500">{{ $member->email }}</p></td>
                            <td class="px-4 py-4 font-mono text-xs font-semibold text-[#E31E24]">{{ $member->membership_id ?: '-' }}</td>
                            <td class="px-4 py-4 text-sm"><x-admin.status-badge :status="$member->status" /></td>
                            <td class="px-4 py-4 text-xs text-slate-600">
                                <x-admin.status-badge :status="$member->subscription_state" />
                                @if($member->subscription_ends_at)<div class="mt-1">Ends {{ $member->subscription_ends_at->format('d M Y') }}</div>@endif
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-600">{{ ucwords(str_replace('_', ' ', $member->insurance_claim_status)) }}</td>
                            <td class="px-4 py-4 text-right">
                                @can('members.edit')
                                <button type="button" wire:click="editMember({{ $member->id }})" class="pk-btn-secondary">Edit</button>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No members found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $members->links() }}</div>
    </section>

    <div x-data="{ open: false, tab: 'profile' }"
         x-on:member-modal-open.window="open = true; tab = $event.detail?.tab || 'profile'"
         x-on:member-modal-close.window="open = false"
         x-show="open" x-cloak class="fixed inset-0 z-[140] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-slate-900/50" @click="open = false"></div>
        <div class="relative max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-800">{{ $memberId ? 'Edit Member' : 'New Member' }}</h2>
                <button type="button" @click="open = false" class="text-slate-400">&times;</button>
            </div>

            <div class="flex gap-2 border-b px-6">
                <button type="button" @click="tab = 'profile'" class="border-b-2 px-3 py-3 text-sm" :class="tab === 'profile' ? 'border-[#E31E24] text-[#E31E24]' : 'border-transparent text-slate-500'">Profile</button>
                @if($memberId)
                <button type="button" @click="tab = 'subscription'" class="border-b-2 px-3 py-3 text-sm" :class="tab === 'subscription' ? 'border-[#E31E24] text-[#E31E24]' : 'border-transparent text-slate-500'">Subscription</button>
                @endif
            </div>

            <div x-show="tab === 'profile'" class="space-y-4 p-6">
                <div class="grid gap-4 md:grid-cols-2">
                    <div><label class="pk-label">Full Name *</label><input type="text" wire:model="full_name" class="pk-input">@error('full_name')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="pk-label">Display Name</label><input type="text" wire:model="display_name" class="pk-input"></div>
                    <div><label class="pk-label">Email *</label><input type="email" wire:model="email" class="pk-input">@error('email')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror</div>
                    <div><label class="pk-label">Phone</label><input type="text" wire:model="phoneLocal" class="pk-input"></div>
                    <div><label class="pk-label">IC Number</label><input type="text" wire:model="ic_number" class="pk-input"></div>
                    <div><label class="pk-label">Insurance Claim</label><select wire:model="insurance_claim_status" class="pk-input">@foreach(App\Models\Member::INSURANCE_STATUSES as $status)<option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
                </div>
                <button type="button" wire:click="saveMember" class="pk-btn-primary w-full">Save Member</button>
                @if($lastCreatedPassword)<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Temporary password: <strong>{{ $lastCreatedPassword }}</strong></div>@endif
                @if($lastResetTempPassword)<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Reset password: <strong>{{ $lastResetTempPassword }}</strong></div>@endif
            </div>

            @if($memberId)
            <div x-show="tab === 'subscription'" class="space-y-4 p-6">
                <div><label class="pk-label">Subscription Plan</label><select wire:model="selectedPlanId" class="pk-input"><option value="">Select a plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }}</option>@endforeach</select></div>
                <div><label class="pk-label">Notes</label><input type="text" wire:model="subscriptionNotes" class="pk-input"></div>
                <button type="button" wire:click="assignSubscription({{ $memberId }})" class="pk-btn-primary w-full">Assign Plan</button>

                <div class="grid gap-3 md:grid-cols-2"><input type="number" wire:model="extensionDays" min="1" class="pk-input"><button type="button" wire:click="extendSubscription({{ $memberId }})" class="pk-btn-secondary">Extend Subscription</button></div>
                <button type="button" wire:click="cancelSubscription({{ $memberId }})" class="pk-btn-secondary w-full">Cancel Active Subscription</button>

                <div class="space-y-2">
                    @forelse($memberSubscriptionHistory as $subscription)
                        <div class="rounded-lg border p-3 text-sm"><strong>{{ $subscription->plan?->name ?: 'Plan' }}</strong><div class="text-xs text-slate-500">{{ $subscription->status }} · {{ optional($subscription->ends_at)->format('d M Y') }}</div></div>
                    @empty
                        <p class="text-sm text-slate-500">No subscription history.</p>
                    @endforelse
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
