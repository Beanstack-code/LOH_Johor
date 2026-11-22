<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('insurance_claim_status')->default('unclaimed')->after('insurance_claimed');
        });

        // Backfill from the old boolean column
        DB::table('members')->where('insurance_claimed', true)->update(['insurance_claim_status' => 'claimed']);
        DB::table('members')->where('insurance_claimed', false)->update(['insurance_claim_status' => 'unclaimed']);

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('insurance_claimed');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('insurance_claimed')->default(false)->after('insurance_claim_status');
        });

        DB::table('members')->where('insurance_claim_status', 'claimed')->update(['insurance_claimed' => true]);
        DB::table('members')->whereIn('insurance_claim_status', ['unclaimed', 'unavailable'])->update(['insurance_claimed' => false]);

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('insurance_claim_status');
        });
    }
};
