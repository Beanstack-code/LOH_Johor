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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('membership_id', 10)->unique()->nullable();
            $table->string('full_name');
            $table->string('display_name', 255)->nullable();
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('ic_number')->nullable();
            $table->string('ic_front_image')->nullable();
            $table->string('ic_back_image')->nullable();
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('postcode')->nullable();
            $table->string('country')->nullable()->default('Malaysia');
            $table->string('status')->default('active');
            $table->string('subscription_state')->default('pending_subscription');
            $table->timestamp('subscription_admin_fee_paid_at')->nullable();
            $table->string('membership_status')->default('active');
            $table->boolean('insurance_claimed')->default(false);
            $table->integer('total_points')->default(0);
            $table->string('password')->nullable();
            $table->timestamp('temp_password_issued_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
