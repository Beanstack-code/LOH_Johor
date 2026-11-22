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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('membership_id', 10)->unique()->nullable();
            $table->string('company_name');
            $table->string('company_name_ms')->nullable();
            $table->string('company_name_zh')->nullable();
            $table->string('company_email')->unique();
            $table->json('categories')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('ssm_document')->nullable();
            $table->string('business_address')->nullable();
            $table->string('password')->nullable();
            $table->timestamp('temp_password_issued_at')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable()->default('Malaysia');
            $table->string('cover_image')->nullable();
            $table->text('short_description')->nullable();
            $table->text('short_description_ms')->nullable();
            $table->text('short_description_zh')->nullable();
            $table->string('status')->default('pending');
            $table->string('subscription_state')->default('pending_subscription');
            $table->text('approval_notes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->date('featured_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
