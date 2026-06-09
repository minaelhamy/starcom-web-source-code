<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('credit_applications', 'submitted_by_customer_service_user_id')) {
                $table->unsignedBigInteger('submitted_by_customer_service_user_id')->nullable()->after('user_id')->index();
            }

            if (!Schema::hasColumn('credit_applications', 'submitted_by_customer_service_at')) {
                $table->timestamp('submitted_by_customer_service_at')->nullable()->after('submitted_by_customer_service_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('credit_applications', function (Blueprint $table) {
            if (Schema::hasColumn('credit_applications', 'submitted_by_customer_service_at')) {
                $table->dropColumn('submitted_by_customer_service_at');
            }

            if (Schema::hasColumn('credit_applications', 'submitted_by_customer_service_user_id')) {
                $table->dropColumn('submitted_by_customer_service_user_id');
            }
        });
    }
};
