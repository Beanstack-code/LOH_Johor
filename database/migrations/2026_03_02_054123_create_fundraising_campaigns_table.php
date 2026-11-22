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
        Schema::create('fundraising_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_name');
            $table->string('campaign_name_ms')->nullable();
            $table->string('campaign_name_zh')->nullable();
            $table->string('organization_name')->nullable();
            $table->string('organization_name_ms')->nullable();
            $table->string('organization_name_zh')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ms')->nullable();
            $table->text('description_zh')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('sub_description')->nullable();
            $table->string('sub_description_ms')->nullable();
            $table->string('sub_description_zh')->nullable();
            $table->string('impact')->nullable();
            $table->string('impact_ms')->nullable();
            $table->string('impact_zh')->nullable();
            $table->json('how_it_helps')->nullable();
            $table->json('how_it_helps_ms')->nullable();
            $table->json('how_it_helps_zh')->nullable();
            $table->decimal('target_amount', 12, 2)->default(0);
            $table->decimal('current_collected_amount', 12, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fundraising_campaigns');
    }
};
