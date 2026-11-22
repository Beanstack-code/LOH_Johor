<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('redemption_items', function (Blueprint $table) {
            $table->text('terms_and_conditions')->nullable()->after('description_zh');
            $table->text('terms_and_conditions_ms')->nullable()->after('terms_and_conditions');
            $table->text('terms_and_conditions_zh')->nullable()->after('terms_and_conditions_ms');
        });
    }

    public function down(): void
    {
        Schema::table('redemption_items', function (Blueprint $table) {
            $table->dropColumn([
                'terms_and_conditions',
                'terms_and_conditions_ms',
                'terms_and_conditions_zh',
            ]);
        });
    }
};
