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
        if (! Schema::hasColumn('subscriptions', 'ends_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->timestamp('ends_at')->nullable()->after('starts_at');
                $table->index('ends_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'ends_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropIndex(['ends_at']);
                $table->dropColumn('ends_at');
            });
        }
    }
};
