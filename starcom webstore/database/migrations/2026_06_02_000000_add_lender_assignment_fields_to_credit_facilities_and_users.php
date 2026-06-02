<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'financial_institution_owner_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('financial_institution_owner_user_id')
                    ->nullable()
                    ->after('distribution_route');
                $table->index('financial_institution_owner_user_id', 'users_financial_institution_owner_idx');
            });
        }

        if (!Schema::hasColumn('credit_facilities', 'financial_institution_employee_user_id')) {
            Schema::table('credit_facilities', function (Blueprint $table) {
                $table->unsignedBigInteger('financial_institution_employee_user_id')
                    ->nullable()
                    ->after('financial_institution_user_id');
                $table->index(
                    'financial_institution_employee_user_id',
                    'credit_facilities_institution_employee_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('credit_facilities', 'financial_institution_employee_user_id')) {
            Schema::table('credit_facilities', function (Blueprint $table) {
                $table->dropIndex('credit_facilities_institution_employee_idx');
                $table->dropColumn('financial_institution_employee_user_id');
            });
        }

        if (Schema::hasColumn('users', 'financial_institution_owner_user_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_financial_institution_owner_idx');
                $table->dropColumn('financial_institution_owner_user_id');
            });
        }
    }
};
