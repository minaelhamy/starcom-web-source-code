<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'credit-customer-status', 'guard_name' => 'sanctum'],
            [
                'title' => 'Credit Customer Status',
                'url' => 'credit-customer-status',
                'parent' => 0,
                'guard_name' => 'sanctum',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $financeMenuId = DB::table('menus')->where('url', '#')->where('language', 'finance_operations')->value('id');
        if ($financeMenuId) {
            DB::table('menus')->updateOrInsert(
                ['url' => 'credit-customer-status'],
                [
                    'name' => 'Credit Customer Status',
                    'language' => 'credit_customer_status',
                    'url' => 'credit-customer-status',
                    'icon' => 'lab lab-line-user',
                    'status' => 1,
                    'parent' => $financeMenuId,
                    'type' => 1,
                    'priority' => 110,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionId = DB::table('permissions')->where('name', 'credit-customer-status')->where('guard_name', 'sanctum')->value('id');
        if ($permissionId) {
            foreach ([RoleEnum::ADMIN, RoleEnum::MANAGER] as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'credit-customer-status')->where('guard_name', 'sanctum')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('menus')->where('url', 'credit-customer-status')->delete();
    }
};
