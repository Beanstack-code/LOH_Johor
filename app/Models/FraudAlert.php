<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FraudAlert extends Model
{
    protected $fillable = [
        'fraud_rule_id',
        'entity_type',
        'entity_id',
        'risk_score',
        'severity',
        'status',
        'fingerprint',
        'triggered_at',
        'payload_json',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'risk_score' => 'integer',
            'triggered_at' => 'datetime',
            'payload_json' => 'array',
            'resolved_by' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(FraudRule::class, 'fraud_rule_id');
    }

    public function entity(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'entity_type', 'entity_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
