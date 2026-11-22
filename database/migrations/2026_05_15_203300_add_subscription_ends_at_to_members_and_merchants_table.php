<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restores subscription_ends_at for production DBs that still run older app code
 * which writes this column on login (Merchant/Member::refreshSubscriptionState).
 *
 * Newer app code no longer uses this column; it is safe to keep nullable for compatibility.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('merchants', 'subscription_ends_at')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_state');
            });
        }

        if (! Schema::hasColumn('members', 'subscription_ends_at')) {
            Schema::table('members', function (Blueprint $table) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_state');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('merchants', 'subscription_ends_at')) {
            Schema::table('merchants', function (Blueprint $table) {
                $table->dropColumn('subscription_ends_at');
            });
        }

        if (Schema::hasColumn('members', 'subscription_ends_at')) {
            Schema::table('members', function (Blueprint $table) {
                $table->dropColumn('subscription_ends_at');
            });
        }
    }
};
