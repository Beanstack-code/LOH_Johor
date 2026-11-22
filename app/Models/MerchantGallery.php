<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MerchantGallery extends Model
{
    protected $fillable = [
        'merchant_id',
        'image_path',
        'media_type',
        'caption',
        'status',
    ];

    protected $hidden = ['image_path'];

    protected $appends = ['image_url', 'media_url'];

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->media_type === 'image' && $this->image_path
                ? url(Storage::url($this->image_path))
                : null,
        );
    }

    protected function mediaUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? url(Storage::url($this->image_path)) : null,
        );
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
