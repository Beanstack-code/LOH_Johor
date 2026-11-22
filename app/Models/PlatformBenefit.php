<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class PlatformBenefit extends Model
{
    protected $fillable = [
        'title',
        'title_ms',
        'title_zh',
        'content',
        'content_ms',
        'content_zh',
        'audience',
        'is_active',
    ];

    protected $appends = [
        'title_i18n',
        'content_i18n',
    ];

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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}

