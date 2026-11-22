<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FraudRule extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'severity',
        'is_enabled',
        'thresholds_json',
        'window_minutes',
        'sort_order',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'thresholds_json' => 'array',
            'window_minutes' => 'integer',
            'sort_order' => 'integer',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(FraudAlert::class);
    }
}
