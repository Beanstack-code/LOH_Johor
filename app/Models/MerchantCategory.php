<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class MerchantCategory extends Model
{
    protected $fillable = [
        'name',
        'name_ms',
        'name_zh',
        'is_active',
    ];

    protected $appends = [
        'name_i18n',
    ];

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

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
