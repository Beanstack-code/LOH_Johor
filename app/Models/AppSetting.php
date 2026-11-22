<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    public const SUBSCRIPTION_ADMIN_FEE = 'subscription_admin_fee';

    public const SUBSCRIPTION_ADMIN_FEE_GRACE_DAYS = 'subscription_admin_fee_grace_days';

    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Read a setting value. Falls back to config('pkpms.*') when the row is missing.
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        $cached = Cache::remember("app_setting.{$key}", 60, function () use ($key) {
            return static::query()->where('key', $key)->value('value');
        });

        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        return $default;
    }

    public static function setValue(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget("app_setting.{$key}");
    }

    public static function subscriptionAdminFee(): float
    {
        $raw = static::getValue(self::SUBSCRIPTION_ADMIN_FEE);

        if ($raw !== null && $raw !== '') {
            return (float) $raw;
        }

        return (float) config('pkpms.subscription_admin_fee', 30);
    }

    public static function subscriptionAdminFeeGraceDays(): int
    {
        $raw = static::getValue(self::SUBSCRIPTION_ADMIN_FEE_GRACE_DAYS);

        if ($raw !== null && $raw !== '') {
            return max(0, (int) $raw);
        }

        return (int) config('pkpms.subscription_admin_fee_grace_days', 365);
    }
}
