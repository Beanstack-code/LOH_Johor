<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fundraising_campaign_id');
            $table->unsignedBigInteger('payment_transaction_id')->nullable()->index();
            $table->string('member_name');
            $table->string('member_email')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamp('donated_at')->nullable();
            $table->string('payment_provider')->nullable();
            $table->string('gateway_order_id')->nullable();
            $table->string('gateway_payment_id')->nullable();
            $table->string('payment_status')->nullable()->index();
            $table->timestamps();
            $table->index('fundraising_campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
