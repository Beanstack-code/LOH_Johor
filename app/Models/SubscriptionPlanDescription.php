<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanDescription extends Model
{
    protected $fillable = [
        'subscription_plan_id',
        'description',
        'description_ms',
        'description_zh',
        'display_order',
    ];

    protected $appends = [
        'description_i18n',
    ];

    protected function descriptionI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->description ?? '');
                $ms = trim((string) ($this->description_ms ?? ''));
                $zh = trim((string) ($this->description_zh ?? ''));

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
            'display_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
