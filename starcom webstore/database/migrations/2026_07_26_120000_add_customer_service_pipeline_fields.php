<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'estimated_average_monthly_purchase')) {
                    $table->decimal('estimated_average_monthly_purchase', 19, 6)
                        ->nullable()
                        ->after('distribution_route');
                }
            });
        }

        if (Schema::hasTable('customer_service_leads')) {
            Schema::table('customer_service_leads', function (Blueprint $table) {
                if (!Schema::hasColumn('customer_service_leads', 'last_pipeline_stage')) {
                    $table->string('last_pipeline_stage')->nullable()->after('documents_status')->index();
                }

                if (!Schema::hasColumn('customer_service_leads', 'last_pipeline_stage_at')) {
                    $table->timestamp('last_pipeline_stage_at')->nullable()->after('last_pipeline_stage');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('customer_service_leads')) {
            Schema::table('customer_service_leads', function (Blueprint $table) {
                $dropColumns = [];

                if (Schema::hasColumn('customer_service_leads', 'last_pipeline_stage_at')) {
                    $dropColumns[] = 'last_pipeline_stage_at';
                }

                if (Schema::hasColumn('customer_service_leads', 'last_pipeline_stage')) {
                    $dropColumns[] = 'last_pipeline_stage';
                }

                if ($dropColumns !== []) {
                    $table->dropColumn($dropColumns);
                }
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'estimated_average_monthly_purchase')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('estimated_average_monthly_purchase');
            });
        }
    }
};
