<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FundraisingCampaign extends Model
{
    protected $fillable = [
        'campaign_name',
        'campaign_name_ms',
        'campaign_name_zh',
        'organization_name',
        'organization_name_ms',
        'organization_name_zh',
        'description',
        'description_ms',
        'description_zh',
        'sub_description',
        'sub_description_ms',
        'sub_description_zh',
        'impact',
        'impact_ms',
        'impact_zh',
        'how_it_helps',
        'how_it_helps_ms',
        'how_it_helps_zh',
        'cover_image',
        'target_amount',
        'current_collected_amount',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected $hidden = ['cover_image'];

    protected $appends = [
        'cover_image_url',
        'campaign_name_i18n',
        'organization_name_i18n',
        'description_i18n',
        'sub_description_i18n',
        'impact_i18n',
        'how_it_helps_i18n',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_collected_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'how_it_helps' => 'array',
            'how_it_helps_ms' => 'array',
            'how_it_helps_zh' => 'array',
        ];
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cover_image ? url(Storage::url($this->cover_image)) : null,
        );
    }

    protected function campaignNameI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->campaign_name ?? '');
                $ms = trim((string) ($this->campaign_name_ms ?? ''));
                $zh = trim((string) ($this->campaign_name_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function organizationNameI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->organization_name ?? '');
                $ms = trim((string) ($this->organization_name_ms ?? ''));
                $zh = trim((string) ($this->organization_name_zh ?? ''));

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

    protected function subDescriptionI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->sub_description ?? '');
                $ms = trim((string) ($this->sub_description_ms ?? ''));
                $zh = trim((string) ($this->sub_description_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function impactI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->impact ?? '');
                $ms = trim((string) ($this->impact_ms ?? ''));
                $zh = trim((string) ($this->impact_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function howItHelpsI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = is_array($this->how_it_helps) ? $this->how_it_helps : [];
                $ms = is_array($this->how_it_helps_ms) ? $this->how_it_helps_ms : [];
                $zh = is_array($this->how_it_helps_zh) ? $this->how_it_helps_zh : [];

                $useMs = ! empty($ms);
                $useZh = ! empty($zh);

                return [
                    'en' => $en,
                    'ms' => $useMs ? $ms : $en,
                    'zh' => $useZh ? $zh : $en,
                ];
            },
        );
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }
}
