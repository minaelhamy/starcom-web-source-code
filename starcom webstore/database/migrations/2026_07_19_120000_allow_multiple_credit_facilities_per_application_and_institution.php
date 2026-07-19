<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_facilities')) {
            return;
        }

        $indexExists = collect(DB::select("SHOW INDEX FROM credit_facilities WHERE Key_name = 'credit_application_institution_unique'"))->isNotEmpty();

        if ($indexExists) {
            Schema::table('credit_facilities', function (Blueprint $table) {
                $table->dropUnique('credit_application_institution_unique');
            });
        }

        $nonUniqueIndexExists = collect(DB::select("SHOW INDEX FROM credit_facilities WHERE Key_name = 'credit_application_institution_idx'"))->isNotEmpty();

        if (!$nonUniqueIndexExists) {
            Schema::table('credit_facilities', function (Blueprint $table) {
                $table->index(
                    ['credit_application_id', 'financial_institution_user_id'],
                    'credit_application_institution_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('credit_facilities')) {
            return;
        }

        $nonUniqueIndexExists = collect(DB::select("SHOW INDEX FROM credit_facilities WHERE Key_name = 'credit_application_institution_idx'"))->isNotEmpty();

        if ($nonUniqueIndexExists) {
            Schema::table('credit_facilities', function (Blueprint $table) {
                $table->dropIndex('credit_application_institution_idx');
            });
        }

        $uniqueExists = collect(DB::select("SHOW INDEX FROM credit_facilities WHERE Key_name = 'credit_application_institution_unique'"))->isNotEmpty();

        if (!$uniqueExists) {
            Schema::table('credit_facilities', function (Blueprint $table) {
                $table->unique(
                    ['credit_application_id', 'financial_institution_user_id'],
                    'credit_application_institution_unique'
                );
            });
        }
    }
};
