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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_ms')->nullable();
            $table->string('title_zh')->nullable();
            $table->string('category')->nullable();
            $table->text('content');
            $table->text('content_ms')->nullable();
            $table->text('content_zh')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('terms_and_conditions_ms')->nullable();
            $table->text('terms_and_conditions_zh')->nullable();
            $table->string('cover_image')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
