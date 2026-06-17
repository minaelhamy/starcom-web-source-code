<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stocks') && Schema::hasColumn('stocks', 'quantity')) {
            DB::statement('ALTER TABLE `stocks` MODIFY `quantity` DECIMAL(14,2) NOT NULL DEFAULT 1.00');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stocks') && Schema::hasColumn('stocks', 'quantity')) {
            DB::statement('ALTER TABLE `stocks` MODIFY `quantity` BIGINT NOT NULL DEFAULT 1');
        }
    }
};
