<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            // Merchant who fulfilled/completed this redemption claim
            $table->unsignedBigInteger('fulfilled_by_merchant_id')->nullable()->after('redeemed_at');
            $table->foreign('fulfilled_by_merchant_id')->references('id')->on('merchants')->nullOnDelete();

            $table->timestamp('fulfilled_at')->nullable()->after('fulfilled_by_merchant_id');
            $table->string('evidence_image_path')->nullable()->after('fulfilled_at');
            $table->text('fulfillment_notes')->nullable()->after('evidence_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropForeign(['fulfilled_by_merchant_id']);
            $table->dropColumn([
                'fulfilled_by_merchant_id',
                'fulfilled_at',
                'evidence_image_path',
                'fulfillment_notes',
            ]);
        });
    }
};
