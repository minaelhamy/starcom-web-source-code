<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('starcom_intelligence')) {
            return;
        }

        Schema::table('starcom_intelligence', function (Blueprint $table) {
            if (!Schema::hasColumn('starcom_intelligence', 'invoice_date')) {
                $table->date('invoice_date')->nullable()->after('distribution_route');
            }

            if (!Schema::hasColumn('starcom_intelligence', 'generated_from_backfill')) {
                $table->boolean('generated_from_backfill')->default(false)->after('invoice_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('starcom_intelligence')) {
            return;
        }

        Schema::table('starcom_intelligence', function (Blueprint $table) {
            $dropColumns = [];

            if (Schema::hasColumn('starcom_intelligence', 'generated_from_backfill')) {
                $dropColumns[] = 'generated_from_backfill';
            }

            if (Schema::hasColumn('starcom_intelligence', 'invoice_date')) {
                $dropColumns[] = 'invoice_date';
            }

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
