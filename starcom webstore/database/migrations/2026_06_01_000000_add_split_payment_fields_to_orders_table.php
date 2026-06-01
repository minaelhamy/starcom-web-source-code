<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'wallet_paid_amount')) {
                $table->decimal('wallet_paid_amount', 19, 6)->default(0)->after('total');
            }

            if (!Schema::hasColumn('orders', 'cash_on_delivery_amount')) {
                $table->decimal('cash_on_delivery_amount', 19, 6)->default(0)->after('wallet_paid_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'cash_on_delivery_amount')) {
                $table->dropColumn('cash_on_delivery_amount');
            }

            if (Schema::hasColumn('orders', 'wallet_paid_amount')) {
                $table->dropColumn('wallet_paid_amount');
            }
        });
    }
};
