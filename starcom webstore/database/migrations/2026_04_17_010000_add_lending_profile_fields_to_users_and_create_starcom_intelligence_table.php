<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('address')->nullable()->after('country_code');
            $table->string('city')->nullable()->after('address');
            $table->string('area')->nullable()->after('city');
            $table->string('latitude')->nullable()->after('area');
            $table->string('longitude')->nullable()->after('latitude');
            $table->string('distribution_route')->nullable()->after('longitude');

            $table->index(['country_code', 'phone'], 'users_country_code_phone_index');
            $table->index('distribution_route', 'users_distribution_route_index');
        });

        Schema::create('starcom_intelligence', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('phone_number')->index();
            $table->string('country_code')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('area')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('distribution_route')->nullable()->index();
            $table->decimal('invoice_amount', 19, 6)->default(0);
            $table->decimal('cartona_credit_amount', 19, 6)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('starcom_intelligence');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_country_code_phone_index');
            $table->dropIndex('users_distribution_route_index');
            $table->dropColumn([
                'address',
                'city',
                'area',
                'latitude',
                'longitude',
                'distribution_route',
            ]);
        });
    }
};
