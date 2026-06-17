<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('return_orders', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
            }

            if (!Schema::hasColumn('return_orders', 'order_serial_no')) {
                $table->string('order_serial_no')->nullable()->after('reference_no');
            }

            if (!Schema::hasColumn('return_orders', 'refund_amount')) {
                $table->decimal('refund_amount', 19, 6)->default(0)->after('total');
            }

            if (!Schema::hasColumn('return_orders', 'refund_meta')) {
                $table->json('refund_meta')->nullable()->after('refund_amount');
            }
        });

        DB::statement('ALTER TABLE `return_and_refund_products` MODIFY `quantity` DECIMAL(19,2) NOT NULL DEFAULT 1');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `return_and_refund_products` MODIFY `quantity` BIGINT NOT NULL DEFAULT 1');

        Schema::table('return_orders', function (Blueprint $table) {
            if (Schema::hasColumn('return_orders', 'refund_meta')) {
                $table->dropColumn('refund_meta');
            }

            if (Schema::hasColumn('return_orders', 'refund_amount')) {
                $table->dropColumn('refund_amount');
            }

            if (Schema::hasColumn('return_orders', 'order_serial_no')) {
                $table->dropColumn('order_serial_no');
            }

            if (Schema::hasColumn('return_orders', 'order_id')) {
                $table->dropConstrainedForeignId('order_id');
            }
        });
    }
};
