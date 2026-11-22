<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_benefits', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ms')->nullable();
            $table->string('title_zh')->nullable();
            $table->longText('content');
            $table->longText('content_ms')->nullable();
            $table->longText('content_zh')->nullable();
            $table->string('audience', 20)->default('both');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['audience', 'is_active'], 'platform_benefits_audience_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_benefits');
    }
};

