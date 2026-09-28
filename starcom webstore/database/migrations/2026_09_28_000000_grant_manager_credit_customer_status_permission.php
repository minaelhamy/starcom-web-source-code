<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'credit-customer-status')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => RoleEnum::MANAGER,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('name', 'credit-customer-status')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($permissionId) {
            DB::table('role_has_permissions')
                ->where('permission_id', $permissionId)
                ->where('role_id', RoleEnum::MANAGER)
                ->delete();
        }
    }
};
