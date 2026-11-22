<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redemption_items', function (Blueprint $table) {
            // Merchant that created this item (null = admin-created)
            $table->unsignedBigInteger('merchant_id')->nullable()->after('id');
            $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();

            // Admin review tracking
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('merchant_id');
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            // status already exists; merchant-uploaded items start as 'pending_review'
            // (existing values: active, inactive — now adding pending_review)
        });
    }

    public function down(): void
    {
        Schema::table('redemption_items', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['merchant_id', 'reviewed_by', 'reviewed_at']);
        });
    }
};
