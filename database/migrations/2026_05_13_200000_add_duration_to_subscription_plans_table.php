<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'duration_value')) {
                $table->unsignedInteger('duration_value')->default(1)->after('final_price');
            }
            if (! Schema::hasColumn('subscription_plans', 'duration_unit')) {
                $table->string('duration_unit', 10)->default('month')->after('duration_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'duration_unit')) {
                $table->dropColumn('duration_unit');
            }
            if (Schema::hasColumn('subscription_plans', 'duration_value')) {
                $table->dropColumn('duration_value');
            }
        });
    }
};
