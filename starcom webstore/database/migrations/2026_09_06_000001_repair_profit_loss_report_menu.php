<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'profit-loss-report', 'guard_name' => 'sanctum'],
            [
                'title' => 'Profit and Loss Report',
                'url' => 'profit-loss-report',
                'parent' => 0,
                'guard_name' => 'sanctum',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $reportsMenuId = DB::table('menus')
            ->where('url', '#')
            ->where('language', 'reports')
            ->value('id');

        if (!$reportsMenuId) {
            $reportsMenuId = DB::table('menus')->insertGetId([
                'name' => 'Reports',
                'language' => 'reports',
                'url' => '#',
                'icon' => 'lab',
                'status' => 1,
                'parent' => 0,
                'type' => 1,
                'priority' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menus')->updateOrInsert(
            ['url' => 'profit-loss-report'],
            [
                'name' => 'Profit and Loss Report',
                'language' => 'profit_loss_report',
                'icon' => 'lab lab-line-chart',
                'status' => 1,
                'parent' => $reportsMenuId,
                'type' => 1,
                'priority' => 104,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('name', 'profit-loss-report')
            ->where('guard_name', 'sanctum')
            ->value('id');

        foreach ([RoleEnum::ADMIN, RoleEnum::MANAGER] as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        // This migration repairs data created by the original migration.
    }
};
