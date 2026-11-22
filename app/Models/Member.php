<?php

namespace App\Models;

use App\Notifications\AppUserResetPasswordNotification;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Member extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'membership_id',
        'full_name',
        'display_name',
        'email',
        'phone',
        'ic_number',
        'occupation',
        'ic_front_image',
        'ic_back_image',
        'street_address',
        'city',
        'postcode',
        'country',
        'status',
        'subscription_state',
        'subscription_admin_fee_paid_at',
        'membership_status',
        'insurance_claim_status',
        'total_points',
        'password',
        'temp_password_issued_at',
        'notes',
    ];

    protected $hidden = [
        'password',
        'ic_front_image',
        'ic_back_image',
    ];

    public const INSURANCE_STATUSES = ['unclaimed', 'claimed', 'unavailable'];

    protected $appends = ['ic_front_image_url', 'ic_back_image_url', 'insurance_claimed'];

    protected function casts(): array
    {
        return [
            'total_points' => 'integer',
            'password' => 'hashed',
            'temp_password_issued_at' => 'datetime',
            'subscription_admin_fee_paid_at' => 'datetime',
            // Admin list uses subscription_ends_at with ->format(); cast when column exists.
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected function icFrontImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ic_front_image ? url(Storage::url($this->ic_front_image)) : null,
        );
    }

    protected function icBackImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ic_back_image ? url(Storage::url($this->ic_back_image)) : null,
        );
    }

    /** Backward-compatible boolean for older mobile clients. */
    protected function insuranceClaimed(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->insurance_claim_status === 'claimed',
        );
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'subscribable');
    }

    public function pushDeviceTokens(): MorphMany
    {
        return $this->morphMany(PushDeviceToken::class, 'authenticatable');
    }

    public function activeSubscription(): MorphOne
    {
        return $this->morphOne(Subscription::class, 'subscribable')
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->where('status', 'active')
            ->exists();
    }

    public function requiresActivation(): bool
    {
        return ! $this->hasActiveSubscription();
    }

    public function refreshSubscriptionState(): bool
    {
        $hasActive = $this->subscriptions()->where('status', 'active')->exists();

        $this->subscription_state = $hasActive ? 'active_subscription' : 'pending_subscription';
        $this->status = $hasActive ? 'active' : 'pending_subscription';

        if (! $this->isDirty(['subscription_state', 'status'])) {
            return false;
        }

        $this->save();

        return true;
    }

    public function recordSubscriptionActivated(bool $adminFeeCharged = false): void
    {
        if ($adminFeeCharged && $this->subscription_admin_fee_paid_at === null) {
            $this->subscription_admin_fee_paid_at = now();
            $this->saveQuietly();
        }
    }

    public function shouldChargeAdminFee(): bool
    {
        $feeAmount = AppSetting::subscriptionAdminFee();
        if ($feeAmount <= 0) {
            return false;
        }

        return $this->subscription_admin_fee_paid_at === null;
    }

    public function pendingAdminFee(): float
    {
        if (! $this->shouldChargeAdminFee()) {
            return 0.0;
        }

        return AppSetting::subscriptionAdminFee();
    }

    public function adminFeeReason(): string
    {
        return $this->subscription_admin_fee_paid_at === null ? 'first_time' : 'not_applicable';
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['role' => 'member'];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AppUserResetPasswordNotification($token, 'member'));
    }

    public static function generateMembershipId(): string
    {
        do {
            $membershipId = 'MBR-'.str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('membership_id', $membershipId)->exists());

        return $membershipId;
    }

    /**
     * Assign and persist {@see $membership_id} when missing (matches admin portal display).
     */
    public function ensureMembershipId(): void
    {
        if (is_string($this->membership_id) && $this->membership_id !== '') {
            return;
        }

        $this->membership_id = self::generateMembershipId();
        $this->save();
    }
}
