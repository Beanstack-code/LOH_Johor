<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('api_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('token_hash', 64)->unique();
            $table->boolean('remember_me')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['authenticatable_type', 'authenticatable_id'], 'api_refresh_tokens_authenticatable_idx');
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_refresh_tokens');
    }
};
