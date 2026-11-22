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
        Schema::create('redemption_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_ms')->nullable();
            $table->string('name_zh')->nullable();
            $table->text('description')->nullable();
            $table->text('description_ms')->nullable();
            $table->text('description_zh')->nullable();
            $table->integer('required_points');
            $table->integer('quantity_available')->default(0);
            $table->date('expiry_date')->nullable();
            $table->string('status')->default('active');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('redemption_items');
    }
};
