<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type')->default('subscription')->index();

            // The subscribable (member or merchant)
            $table->string('subscribable_type');
            $table->unsignedBigInteger('subscribable_id');
            $table->index(['subscribable_type', 'subscribable_id']);

            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('fundraising_campaign_id')->nullable()->constrained('fundraising_campaigns');

            // Gateway identifiers — unique index enforces idempotency.
            $table->string('provider')->default('razorpay');
            $table->string('razorpay_order_id')->unique();
            $table->string('razorpay_payment_id')->nullable()->unique();

            // Server-computed amounts at the time the order was created.
            $table->decimal('amount_plan', 12, 2);
            $table->decimal('amount_admin_fee', 12, 2)->default(0);
            $table->decimal('amount_total', 12, 2);
            $table->string('currency', 3)->default('MYR');

            // Why was admin fee charged (or not) — for support and audit.
            $table->string('fee_reason')->default('renewal');

            // pending | captured | failed | refunded
            $table->string('status')->default('pending')->index();
            $table->string('donor_name')->nullable();
            $table->string('donor_email')->nullable();

            // Raw gateway payload stored for dispute / support.
            $table->json('provider_payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
