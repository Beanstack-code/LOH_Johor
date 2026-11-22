<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AdminNotification extends Model
{
    protected $fillable = [
        'title',
        'title_ms',
        'title_zh',
        'message',
        'message_ms',
        'message_zh',
        'notification_type',
        'target_audience',
        'scheduled_at',
        'sent_at',
        'status',
        'push_total_targets',
        'push_sent_count',
        'push_failed_count',
        'last_push_attempt_at',
        'last_push_error',
        'image_path',
        'deep_link',
    ];

    protected $appends = [
        'title_i18n',
        'message_i18n',
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

    protected function messageI18n(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                $en = (string) ($this->message ?? '');
                $ms = trim((string) ($this->message_ms ?? ''));
                $zh = trim((string) ($this->message_zh ?? ''));

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
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'last_push_attempt_at' => 'datetime',
        ];
    }
}
