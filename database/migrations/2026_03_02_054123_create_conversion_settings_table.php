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
        Schema::create('conversion_settings', function (Blueprint $table) {
            $table->id();
            $table->string('currency_unit')->default('MYR');
            $table->decimal('points_per_currency', 10, 2);
            $table->date('effective_date');
            $table->boolean('is_active')->default(true);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversion_settings');
    }
};
