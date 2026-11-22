<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    protected $fillable = [
        'title',
        'title_ms',
        'title_zh',
        'category',
        'content',
        'content_ms',
        'content_zh',
        'terms_and_conditions',
        'terms_and_conditions_ms',
        'terms_and_conditions_zh',
        'cover_image',
        'starts_at',
        'ends_at',
        'is_highlighted',
        'status',
    ];

    protected $hidden = ['cover_image'];

    protected $appends = [
        'cover_image_url',
        'title_i18n',
        'content_i18n',
        'terms_and_conditions_i18n',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_highlighted' => 'boolean',
        ];
    }

    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->cover_image ? url(Storage::url($this->cover_image)) : null,
        );
    }

    protected function titleI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->title ?? '');
                $ms = trim((string) ($this->title_ms ?? ''));
                $zh = trim((string) ($this->title_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function contentI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->content ?? '');
                $ms = trim((string) ($this->content_ms ?? ''));
                $zh = trim((string) ($this->content_zh ?? ''));

                return [
                    'en' => $en,
                    'ms' => $ms !== '' ? $ms : $en,
                    'zh' => $zh !== '' ? $zh : $en,
                ];
            },
        );
    }

    protected function termsAndConditionsI18n(): Attribute
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
}
