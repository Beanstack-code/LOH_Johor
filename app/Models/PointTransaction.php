<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class PointTransaction extends Model
{
    protected $fillable = [
        'merchant_id',
        'member_id',
        'type',
        'currency_amount',
        'points',
        'reference_number',
        'reason',
        'status',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'currency_amount' => 'decimal:2',
            'points' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
