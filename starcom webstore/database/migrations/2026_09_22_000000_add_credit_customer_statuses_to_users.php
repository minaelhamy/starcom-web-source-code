<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('credit_blacklisted_at')->nullable()->index();
            $table->unsignedBigInteger('credit_blacklisted_by_user_id')->nullable();
            $table->text('credit_blacklist_reason')->nullable();
            $table->boolean('is_top_credit_customer')->default(false)->index();
            $table->timestamp('top_credit_customer_at')->nullable();
            $table->unsignedBigInteger('top_credit_customer_by_user_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['credit_blacklisted_at']);
            $table->dropIndex(['is_top_credit_customer']);
            $table->dropColumn([
                'credit_blacklisted_at',
                'credit_blacklisted_by_user_id',
                'credit_blacklist_reason',
                'is_top_credit_customer',
                'top_credit_customer_at',
                'top_credit_customer_by_user_id',
            ]);
        });
    }
};
