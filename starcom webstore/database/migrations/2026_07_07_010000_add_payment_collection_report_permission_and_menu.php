<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'payment-collection-report', 'guard_name' => 'sanctum'],
            [
                'title' => 'Payment Collection Report',
                'name' => 'payment-collection-report',
                'url' => 'payment-collection-report',
                'parent' => 0,
                'guard_name' => 'sanctum',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $financeMenuId = DB::table('menus')->where('url', '#')->where('language', 'finance_operations')->value('id');

        if ($financeMenuId) {
            DB::table('menus')->updateOrInsert(
                ['url' => 'payment-collection-report'],
                [
                    'name' => 'Payment Collection Report',
                    'language' => 'payment_collection_report',
                    'url' => 'payment-collection-report',
                    'icon' => 'lab lab-line-receipt',
                    'status' => 1,
                    'parent' => $financeMenuId,
                    'type' => 1,
                    'priority' => 103,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionId = DB::table('permissions')
            ->where('name', 'payment-collection-report')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($permissionId) {
            foreach ([RoleEnum::ADMIN, RoleEnum::MANAGER, RoleEnum::FINANCIAL_INSTITUTION] as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'payment-collection-report')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('menus')->where('url', 'payment-collection-report')->delete();
    }
};
