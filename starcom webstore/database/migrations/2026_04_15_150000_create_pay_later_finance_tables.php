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
        Schema::create('financial_institution_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('credit_facilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_application_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('financial_institution_user_id');
            $table->string('status')->default('approved');
            $table->decimal('approved_amount', 19, 6)->default(0);
            $table->decimal('available_amount', 19, 6)->default(0);
            $table->decimal('utilized_amount', 19, 6)->default(0);
            $table->unsignedInteger('duration_days')->default(30);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['credit_application_id', 'financial_institution_user_id'], 'credit_application_institution_unique');
        });

        Schema::create('credit_facility_order_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('credit_facility_id');
            $table->unsignedBigInteger('order_id');
            $table->decimal('amount', 19, 6)->default(0);
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('financial_institution_user_id')->nullable();
            $table->unsignedBigInteger('credit_application_id')->nullable();
            $table->unsignedBigInteger('credit_facility_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('type');
            $table->string('direction');
            $table->decimal('amount', 19, 6)->default(0);
            $table->decimal('balance_before', 19, 6)->default(0);
            $table->decimal('balance_after', 19, 6)->default(0);
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        DB::table('roles')->updateOrInsert(
            ['id' => RoleEnum::FINANCIAL_INSTITUTION],
            [
                'name'       => 'Financial Institution',
                'guard_name' => 'sanctum',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionRows = [
            ['title' => 'Financial Institutions', 'name' => 'financial-institutions', 'url' => 'financial-institutions', 'parent' => 0],
            ['title' => 'Financial Institutions Create', 'name' => 'financial-institutions_create', 'url' => 'financial-institutions/create', 'parent' => 0],
            ['title' => 'Financial Institutions Edit', 'name' => 'financial-institutions_edit', 'url' => 'financial-institutions/edit', 'parent' => 0],
            ['title' => 'Financial Institutions Show', 'name' => 'financial-institutions_show', 'url' => 'financial-institutions/show', 'parent' => 0],
            ['title' => 'Credit Requests', 'name' => 'credit-requests', 'url' => 'credit-requests', 'parent' => 0],
            ['title' => 'Credit Requests Show', 'name' => 'credit-requests_show', 'url' => 'credit-requests/show', 'parent' => 0],
            ['title' => 'Credit Requests Review', 'name' => 'credit-requests_review', 'url' => 'credit-requests/review', 'parent' => 0],
            ['title' => 'Lending Portfolio', 'name' => 'lending-portfolio', 'url' => 'lending-portfolio', 'parent' => 0],
            ['title' => 'Lending Portfolio Show', 'name' => 'lending-portfolio_show', 'url' => 'lending-portfolio/show', 'parent' => 0],
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
            ['name' => 'Financial Institutions', 'language' => 'financial_institutions', 'url' => 'financial-institutions', 'icon' => 'lab lab-line-users', 'parent' => $financeMenuId],
            ['name' => 'Credit Requests', 'language' => 'credit_requests', 'url' => 'credit-requests', 'icon' => 'lab lab-line-paper', 'parent' => $financeMenuId],
            ['name' => 'Lending Portfolio', 'language' => 'lending_portfolio', 'url' => 'lending-portfolio', 'icon' => 'lab lab-line-chart', 'parent' => $financeMenuId],
        ];

        foreach ($menuRows as $menuRow) {
            DB::table('menus')->updateOrInsert(
                ['url' => $menuRow['url']],
                $menuRow + [
                    'status'     => 1,
                    'type'       => 1,
                    'priority'   => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $adminPermissionNames = [
            'financial-institutions',
            'financial-institutions_create',
            'financial-institutions_edit',
            'financial-institutions_show',
            'credit-requests',
            'credit-requests_show',
            'credit-requests_review',
            'lending-portfolio',
            'lending-portfolio_show',
        ];

        $institutionPermissionNames = [
            'credit-requests',
            'credit-requests_show',
            'credit-requests_review',
            'lending-portfolio',
            'lending-portfolio_show',
        ];

        $adminPermissionIds = DB::table('permissions')->whereIn('name', $adminPermissionNames)->pluck('id');
        foreach ($adminPermissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore(
                ['permission_id' => $permissionId, 'role_id' => RoleEnum::ADMIN]
            );
        }

        $institutionPermissionIds = DB::table('permissions')->whereIn('name', $institutionPermissionNames)->pluck('id');
        foreach ($institutionPermissionIds as $permissionId) {
            DB::table('role_has_permissions')->insertOrIgnore(
                ['permission_id' => $permissionId, 'role_id' => RoleEnum::FINANCIAL_INSTITUTION]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('credit_facility_order_allocations');
        Schema::dropIfExists('credit_facilities');
        Schema::dropIfExists('credit_applications');
        Schema::dropIfExists('financial_institution_profiles');
    }
};
