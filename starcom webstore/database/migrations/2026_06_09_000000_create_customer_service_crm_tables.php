<?php

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_service_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('assigned_to_user_id')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->unsignedInteger('priority_order')->default(999)->index();
            $table->unsignedInteger('assignment_cycle')->default(0);
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->string('prospect_full_name')->nullable();
            $table->string('prospect_national_id_number')->nullable();
            $table->string('documents_status')->nullable();
            $table->text('latest_note')->nullable();
            $table->string('source_sheet')->nullable();
            $table->string('source_status')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_service_lead_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_service_lead_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->text('note')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->updateOrInsert(
            ['id' => RoleEnum::CUSTOMER_SERVICE],
            [
                'name'       => 'Customer Service',
                'guard_name' => 'sanctum',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionRows = [
            ['title' => 'Customer Service Leads', 'name' => 'customer-service-leads', 'url' => 'customer-service-leads', 'parent' => 0],
            ['title' => 'Customer Service Leads Update', 'name' => 'customer-service-leads_update', 'url' => 'customer-service-leads/update', 'parent' => 0],
            ['title' => 'Customer Service Leads Submit Application', 'name' => 'customer-service-leads_submit', 'url' => 'customer-service-leads/submit', 'parent' => 0],
            ['title' => 'Customer Service Reports', 'name' => 'customer-service-reports', 'url' => 'customer-service-reports', 'parent' => 0],
            ['title' => 'Customer Service Redistribute', 'name' => 'customer-service-redistribute', 'url' => 'customer-service-redistribute', 'parent' => 0],
        ];

        foreach ($permissionRows as $permissionRow) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permissionRow['name'], 'guard_name' => 'sanctum'],
                $permissionRow + ['guard_name' => 'sanctum', 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $financeMenuId = DB::table('menus')->where('url', '#')->where('language', 'finance_operations')->value('id');
        if (!$financeMenuId) {
            $financeMenuId = DB::table('menus')->insertGetId([
                'name'       => 'Finance Operations',
                'language'   => 'finance_operations',
                'url'        => '#',
                'icon'       => 'lab lab-line-wallet',
                'status'     => 1,
                'parent'     => 0,
                'type'       => 1,
                'priority'   => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $menuRows = [
            ['name' => 'Customer Service Leads', 'language' => 'customer_service_leads', 'url' => 'customer-service-leads', 'icon' => 'lab lab-line-users', 'parent' => $financeMenuId],
            ['name' => 'Customer Service Reports', 'language' => 'customer_service_reports', 'url' => 'customer-service-reports', 'icon' => 'lab lab-line-chart', 'parent' => $financeMenuId],
        ];

        foreach ($menuRows as $menuRow) {
            DB::table('menus')->updateOrInsert(
                ['url' => $menuRow['url']],
                $menuRow + [
                    'status'     => 1,
                    'type'       => 1,
                    'priority'   => 101,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $dashboardPermissionId = DB::table('permissions')
            ->where('name', 'dashboard')
            ->where('guard_name', 'sanctum')
            ->value('id');

        if ($dashboardPermissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $dashboardPermissionId,
                'role_id' => RoleEnum::CUSTOMER_SERVICE,
            ]);
        }

        $customerServicePermissionIds = DB::table('permissions')
            ->whereIn('name', [
                'customer-service-leads',
                'customer-service-leads_update',
                'customer-service-leads_submit',
            ])
            ->pluck('id');

        foreach ($customerServicePermissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => RoleEnum::CUSTOMER_SERVICE,
            ]);
        }

        $managerPermissionIds = DB::table('permissions')
            ->whereIn('name', [
                'customer-service-leads',
                'customer-service-leads_update',
                'customer-service-leads_submit',
                'customer-service-reports',
                'customer-service-redistribute',
            ])
            ->pluck('id');

        foreach ($managerPermissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => RoleEnum::ADMIN,
            ]);

            DB::table('role_has_permissions')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => RoleEnum::MANAGER,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_service_lead_activities');
        Schema::dropIfExists('customer_service_leads');
    }
};
