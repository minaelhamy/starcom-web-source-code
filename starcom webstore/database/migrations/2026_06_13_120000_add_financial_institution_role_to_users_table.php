<?php

use App\Enums\FinancialInstitutionUserRole;
use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'financial_institution_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('financial_institution_role', 20)->nullable()->after('financial_institution_owner_user_id');
            });
        }

        $financialInstitutionUserIds = DB::table('model_has_roles')
            ->where('role_id', RoleEnum::FINANCIAL_INSTITUTION)
            ->pluck('model_id');

        if ($financialInstitutionUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $financialInstitutionUserIds)
                ->whereNotNull('financial_institution_owner_user_id')
                ->update(['financial_institution_role' => FinancialInstitutionUserRole::EMPLOYEE]);

            DB::table('users')
                ->whereIn('id', $financialInstitutionUserIds)
                ->whereNull('financial_institution_owner_user_id')
                ->update(['financial_institution_role' => FinancialInstitutionUserRole::MANAGER]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'financial_institution_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('financial_institution_role');
            });
        }
    }
};
