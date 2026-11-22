<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchantBenefit extends Model
{
    protected $fillable = [
        'merchant_id',
        'description',
        'display_order',
    ];

    protected $hidden = [
        'merchant_id',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
