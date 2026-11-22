<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->text('about_description')->nullable()->after('short_description_zh');
            $table->text('about_description_ms')->nullable()->after('about_description');
            $table->text('about_description_zh')->nullable()->after('about_description_ms');
        });
    }

    public function down(): void
    {
        Schema::table('merchants', function (Blueprint $table) {
            $table->dropColumn([
                'about_description',
                'about_description_ms',
                'about_description_zh',
            ]);
        });
    }
};
