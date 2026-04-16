<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'address')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('address')->nullable()->after('country_code');
            });
        }

        if (!Schema::hasColumn('users', 'city')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('city')->nullable()->after('address');
            });
        }

        if (!Schema::hasColumn('users', 'area')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('area')->nullable()->after('city');
            });
        }

        if (!Schema::hasColumn('users', 'latitude')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('latitude')->nullable()->after('area');
            });
        }

        if (!Schema::hasColumn('users', 'longitude')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('longitude')->nullable()->after('latitude');
            });
        }

        if (!Schema::hasColumn('users', 'distribution_route')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('distribution_route')->nullable()->after('longitude');
            });
        }

        if (!$this->indexExists('users', 'users_country_code_phone_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['country_code', 'phone'], 'users_country_code_phone_index');
            });
        }

        if (!$this->indexExists('users', 'users_distribution_route_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('distribution_route', 'users_distribution_route_index');
            });
        }

        if (!Schema::hasTable('starcom_intelligence')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('starcom_intelligence');

        if (Schema::hasTable('users')) {
            if ($this->indexExists('users', 'users_country_code_phone_index')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex('users_country_code_phone_index');
                });
            }

            if ($this->indexExists('users', 'users_distribution_route_index')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex('users_distribution_route_index');
                });
            }

            $dropColumns = array_filter([
                Schema::hasColumn('users', 'address') ? 'address' : null,
                Schema::hasColumn('users', 'city') ? 'city' : null,
                Schema::hasColumn('users', 'area') ? 'area' : null,
                Schema::hasColumn('users', 'latitude') ? 'latitude' : null,
                Schema::hasColumn('users', 'longitude') ? 'longitude' : null,
                Schema::hasColumn('users', 'distribution_route') ? 'distribution_route' : null,
            ]);

            if ($dropColumns !== []) {
                Schema::table('users', function (Blueprint $table) use ($dropColumns) {
                    $table->dropColumn($dropColumns);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
