<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->dropUnique('payment_transactions_razorpay_order_id_unique');
            $t->dropUnique('payment_transactions_razorpay_payment_id_unique');
        });

        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->renameColumn('razorpay_order_id', 'gateway_order_id');
            $t->renameColumn('razorpay_payment_id', 'gateway_payment_id');
        });

        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->unique('gateway_order_id');
            $t->unique('gateway_payment_id');

            // Switch the default provider to paycools. Existing rows keep
            // whatever provider value they already had (e.g. 'razorpay').
            $t->string('provider')->default('paycools')->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->dropUnique('payment_transactions_gateway_order_id_unique');
            $t->dropUnique('payment_transactions_gateway_payment_id_unique');
        });

        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->renameColumn('gateway_order_id', 'razorpay_order_id');
            $t->renameColumn('gateway_payment_id', 'razorpay_payment_id');
        });

        Schema::table('payment_transactions', function (Blueprint $t) {
            $t->unique('razorpay_order_id');
            $t->unique('razorpay_payment_id');
            $t->string('provider')->default('razorpay')->change();
        });
    }
};
