<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'name_ms',
        'name_zh',
        'audience',
        'price',
        'discount_type',
        'discount_value',
        'final_price',
        'duration_value',
        'duration_unit',
        'is_active',
        'display_order',
    ];

    protected $appends = [
        'name_i18n',
    ];

    protected function nameI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->name ?? '');
                $ms = trim((string) ($this->name_ms ?? ''));
                $zh = trim((string) ($this->name_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'final_price' => 'decimal:2',
            'duration_value' => 'integer',
            'duration_unit' => 'string',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /**
     * Subscription end instant from a plan's configured duration (day / month / year).
     */
    public function calculateEndDate(Carbon|DateTimeInterface|string $startsAt): Carbon
    {
        $start = Carbon::parse($startsAt);
        $value = max(1, (int) ($this->duration_value ?? 1));
        $unit = (string) ($this->duration_unit ?? 'month');

        return match ($unit) {
            'day' => $start->copy()->addDays($value),
            'year' => $start->copy()->addYears($value),
            default => $start->copy()->addMonths($value),
        };
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function descriptions(): HasMany
    {
        return $this->hasMany(SubscriptionPlanDescription::class)->orderBy('display_order');
    }
}
