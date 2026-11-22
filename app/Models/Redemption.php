<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Redemption extends Model
{
    protected $fillable = [
        'member_id',
        'redemption_item_id',
        'points_used',
        'status',
        'reference_number',
        'redeemed_at',
        'fulfilled_by_merchant_id',
        'fulfilled_at',
        'evidence_image_path',
        'fulfillment_notes',
    ];

    protected $hidden = ['evidence_image_path'];

    protected $appends = ['evidence_image_url'];

    protected function casts(): array
    {
        return [
            'points_used' => 'integer',
            'redeemed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
        ];
    }

    protected function evidenceImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->evidence_image_path
                ? url(Storage::url($this->evidence_image_path))
                : null,
        );
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RedemptionItem::class, 'redemption_item_id');
    }

    public function fulfilledByMerchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'fulfilled_by_merchant_id');
    }
}
