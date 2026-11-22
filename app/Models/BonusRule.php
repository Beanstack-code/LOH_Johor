<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BonusRule extends Model
{
    protected $fillable = [
        'name',
        'description',
        'multiplier',
        'starts_at',
        'ends_at',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_enabled' => 'boolean',
        ];
    }
}
