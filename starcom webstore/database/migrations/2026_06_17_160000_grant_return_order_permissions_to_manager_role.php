<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'return-orders',
                'return_order_create',
                'return_order_edit',
                'return_order_delete',
                'return_order_show',
            ])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id'       => RoleEnum::MANAGER,
            ]);
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', [
                'return-orders',
                'return_order_create',
                'return_order_edit',
                'return_order_delete',
                'return_order_show',
            ])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')
                ->where('role_id', RoleEnum::MANAGER)
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }
    }
};
