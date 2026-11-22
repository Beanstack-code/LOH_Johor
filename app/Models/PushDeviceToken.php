<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PushDeviceToken extends Model
{
    protected $fillable = [
        'authenticatable_type',
        'authenticatable_id',
        'fcm_token',
        'fcm_token_hash',
        'platform',
        'app_version',
        'is_active',
        'last_seen_at',
    ];

    protected static function booted(): void
    {
        // Auto-compute the hash whenever fcm_token is set so callers never
        // need to remember to populate fcm_token_hash manually.
        static::saving(function (self $model): void {
            if ($model->isDirty('fcm_token') && $model->fcm_token) {
                $model->fcm_token_hash = hash('sha256', $model->fcm_token);
            }
        });
    }

    /** Find a token record by the raw FCM token string (uses the hash index). */
    public static function findByToken(string $token): ?self
    {
        return static::where('fcm_token_hash', hash('sha256', $token))->first();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

