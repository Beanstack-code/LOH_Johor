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

class Merchant extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'membership_id',
        'company_name',
        'company_name_ms',
        'company_name_zh',
        'company_email',
        'phone',
        'whatsapp_number',
        'categories',
        'registration_number',
        'ssm_document',
        'business_address',
        'street_address',
        'city',
        'postcode',
        'country',
        'cover_image',
        'short_description',
        'short_description_ms',
        'short_description_zh',
        'about_description',
        'about_description_ms',
        'about_description_zh',
        'password',
        'temp_password_issued_at',
        'status',
        'subscription_state',
        'approval_notes',
        'is_featured',
        'featured_until',
    ];

    protected $hidden = [
        'password',
        'cover_image',
        'ssm_document',
    ];

    protected $appends = [
        'cover_image_url',
        'ssm_document_url',
        'company_name_i18n',
        'short_description_i18n',
        'about_description_i18n',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'is_featured' => 'boolean',
            'featured_until' => 'date',
            'password' => 'hashed',
            'temp_password_issued_at' => 'datetime',
            // Admin list uses subscription_ends_at with ->format(); cast required when column exists.
            'subscription_ends_at' => 'datetime',
        ];
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cover_image ? url(Storage::url($this->cover_image)) : null,
        );
    }

    protected function ssmDocumentUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->ssm_document ? url(Storage::url($this->ssm_document)) : null,
        );
    }

    protected function companyNameI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->company_name ?? '');
                $ms = trim((string) ($this->company_name_ms ?? ''));
                $zh = trim((string) ($this->company_name_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function shortDescriptionI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->short_description ?? '');
                $ms = trim((string) ($this->short_description_ms ?? ''));
                $zh = trim((string) ($this->short_description_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function aboutDescriptionI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->about_description ?? '');
                $ms = trim((string) ($this->about_description_ms ?? ''));
                $zh = trim((string) ($this->about_description_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    public function galleries(): HasMany
    {
        return $this->hasMany(MerchantGallery::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(MerchantBenefit::class)->orderBy('display_order');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class);
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

        if (! $hasActive && $this->status === 'active') {
            $this->status = 'pending_subscription';
        }

        if ($hasActive && in_array($this->status, ['pending_subscription', 'pending'], true)) {
            $this->status = 'active';
        }

        if (! $this->isDirty(['subscription_state', 'status'])) {
            return false;
        }

        $this->save();

        return true;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return ['role' => 'merchant'];
    }

    public function getEmailForPasswordReset()
    {
        return $this->company_email;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->company_email;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AppUserResetPasswordNotification($token, 'merchant'));
    }

    /**
     * No-op for merchants — admin-fee timestamps only exist on Member.
     * Required so the webhook can call this uniformly on any subscribable.
     */
    public function recordSubscriptionActivated(bool $adminFeeCharged = false): void
    {
        // Merchants have no admin-fee columns; nothing to stamp.
    }

    public static function generateMembershipId(): string
    {
        do {
            $membershipId = 'MCH-'.str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while (self::where('membership_id', $membershipId)->exists());

        return $membershipId;
    }
}
