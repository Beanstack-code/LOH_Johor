<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class RedemptionItem extends Model
{
    protected $fillable = [
        'merchant_id',
        'reviewed_by',
        'reviewed_at',
        'name',
        'name_ms',
        'name_zh',
        'description',
        'description_ms',
        'description_zh',
        'terms_and_conditions',
        'terms_and_conditions_ms',
        'terms_and_conditions_zh',
        'required_points',
        'quantity_available',
        'expiry_date',
        'status',
        'image_path',
    ];

    protected $hidden = ['image_path'];

    protected $appends = ['image_url', 'name_i18n', 'description_i18n', 'terms_i18n'];

    protected function casts(): array
    {
        return [
            'required_points' => 'integer',
            'quantity_available' => 'integer',
            'expiry_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->image_path ? url(Storage::url($this->image_path)) : null,
        );
    }

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

    protected function termsI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->terms_and_conditions ?? '');
                $ms = trim((string) ($this->terms_and_conditions_ms ?? ''));
                $zh = trim((string) ($this->terms_and_conditions_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
