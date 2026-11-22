<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Donation extends Model
{
    protected $fillable = [
        'fundraising_campaign_id',
        'member_name',
        'member_email',
        'amount',
        'donated_at',
        'payment_transaction_id',
        'payment_provider',
        'gateway_order_id',
        'gateway_payment_id',
        'payment_status',
        'receipt_emailed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'donated_at' => 'datetime',
            'receipt_emailed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FundraisingCampaign::class, 'fundraising_campaign_id');
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_transaction_id');
    }
}
