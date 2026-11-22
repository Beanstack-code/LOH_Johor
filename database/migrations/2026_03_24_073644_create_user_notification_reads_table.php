<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->string('role'); // 'member' or 'merchant'
            $table->unsignedBigInteger('user_id'); // ID in members or merchants table
            $table->foreignId('admin_notification_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->unique(['role', 'user_id', 'admin_notification_id'], 'unr_role_user_notif_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_reads');
    }
};
