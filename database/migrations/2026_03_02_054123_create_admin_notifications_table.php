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
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ms')->nullable();
            $table->string('title_zh')->nullable();
            $table->text('message');
            $table->text('message_ms')->nullable();
            $table->text('message_zh')->nullable();
            $table->string('notification_type')->default('system');
            $table->string('target_audience')->default('all_users');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('push_total_targets')->default(0);
            $table->unsignedInteger('push_sent_count')->default(0);
            $table->unsignedInteger('push_failed_count')->default(0);
            $table->timestamp('last_push_attempt_at')->nullable();
            $table->text('last_push_error')->nullable();
            $table->string('image_path')->nullable();
            $table->string('deep_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
    }
};
