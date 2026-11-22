<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->text('fcm_token');
            // sha256 hash of the token — used for fast unique lookups without
            // hitting MySQL's 3072-byte index key limit on large text columns.
            $table->char('fcm_token_hash', 64)->unique();
            $table->string('platform', 20);
            $table->string('app_version', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['authenticatable_type', 'authenticatable_id', 'is_active'], 'push_tokens_owner_active_idx');
            $table->index(['platform', 'is_active'], 'push_tokens_platform_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_device_tokens');
    }
};

