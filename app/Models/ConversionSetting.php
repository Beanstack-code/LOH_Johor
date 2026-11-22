<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversionSetting extends Model
{
    protected $fillable = [
        'currency_unit',
        'points_per_currency',
        'effective_date',
        'is_active',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'points_per_currency' => 'decimal:2',
            'effective_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
