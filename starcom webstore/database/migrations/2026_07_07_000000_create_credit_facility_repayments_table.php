<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('credit_facility_repayments')) {
            Schema::create('credit_facility_repayments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('credit_facility_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('financial_institution_user_id')->nullable();
                $table->decimal('amount', 19, 6)->default(0);
                $table->string('payment_method')->nullable();
                $table->string('reference_number')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamps();

                $table->index(['credit_facility_id', 'paid_at'], 'facility_repayments_facility_paid_idx');
                $table->index(['financial_institution_user_id'], 'facility_repayments_institution_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_facility_repayments');
    }
};
