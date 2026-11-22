<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'transaction_type',
        'subscribable_type',
        'subscribable_id',
        'subscription_plan_id',
        'fundraising_campaign_id',
        'provider',
        'gateway_order_id',
        'gateway_payment_id',
        'amount_plan',
        'amount_admin_fee',
        'amount_total',
        'currency',
        'fee_reason',
        'status',
        'donor_name',
        'donor_email',
        'provider_payload',
        'receipt_emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_plan' => 'decimal:2',
            'amount_admin_fee' => 'decimal:2',
            'amount_total' => 'decimal:2',
            'provider_payload' => 'array',
            'receipt_emailed_at' => 'datetime',
        ];
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FundraisingCampaign::class, 'fundraising_campaign_id');
    }
}
