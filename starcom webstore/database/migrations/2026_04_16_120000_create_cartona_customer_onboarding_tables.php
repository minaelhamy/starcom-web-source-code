<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('google_map_url')->nullable()->after('longitude');
            $table->string('route_code')->nullable()->after('google_map_url');
            $table->string('route_name')->nullable()->after('route_code');
            $table->text('address_notes')->nullable()->after('route_name');
            $table->string('source')->nullable()->after('address_notes');
        });

        Schema::create('cartona_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('retailer_code')->nullable()->index();
            $table->string('retailer_name');
            $table->string('retailer_number')->nullable()->index();
            $table->string('retailer_number2')->nullable();
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('secondary_country_code')->nullable();
            $table->string('secondary_phone')->nullable();
            $table->text('retailer_address')->nullable();
            $table->text('address_notes')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('google_map_url')->nullable();
            $table->string('distribution_route_code')->nullable()->index();
            $table->string('supplier_code')->nullable()->index();
            $table->json('latest_payload')->nullable();
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['retailer_code', 'retailer_number'], 'cartona_customers_retailer_code_number_unique');
        });

        Schema::create('cartona_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cartona_customer_id')->constrained('cartona_customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hashed_id')->unique();
            $table->string('supplier_code')->nullable()->index();
            $table->string('distribution_route_code')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->date('delivery_day')->nullable();
            $table->date('estimated_delivery_day')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('return_reason')->nullable();
            $table->decimal('total_price', 19, 6)->default(0);
            $table->decimal('cartona_credit', 19, 6)->default(0);
            $table->decimal('installment_cost', 19, 6)->default(0);
            $table->decimal('wallet_top_up', 19, 6)->default(0);
            $table->text('note_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cartona_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cartona_order_id')->constrained('cartona_orders')->cascadeOnDelete();
            $table->unsignedBigInteger('cartona_order_detail_id')->nullable()->index();
            $table->string('internal_product_id')->nullable()->index();
            $table->string('product_name')->nullable();
            $table->decimal('amount', 19, 6)->default(0);
            $table->decimal('selling_price', 19, 6)->default(0);
            $table->decimal('applied_supplier_discount', 19, 6)->default(0);
            $table->decimal('applied_cartona_discount', 19, 6)->default(0);
            $table->text('comment')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['cartona_order_id', 'cartona_order_detail_id'], 'cartona_order_items_order_detail_unique');
        });

        Schema::create('starcom_intelligence_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cartona_customer_id')->nullable()->constrained('cartona_customers')->nullOnDelete();
            $table->string('source')->default('cartona')->index();
            $table->string('external_customer_code')->nullable()->index();
            $table->string('full_name');
            $table->string('country_code')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('secondary_phone')->nullable();
            $table->text('address')->nullable();
            $table->text('address_notes')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->string('google_map_url')->nullable();
            $table->string('route_code')->nullable()->index();
            $table->string('route_name')->nullable();
            $table->string('supplier_code')->nullable()->index();
            $table->unsignedInteger('orders_count')->default(0);
            $table->decimal('total_purchase_value', 19, 6)->default(0);
            $table->decimal('average_order_value', 19, 6)->default(0);
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->json('source_payload')->nullable();
            $table->json('metrics_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_customer_code', 'phone'], 'starcom_intelligence_customers_source_code_phone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('starcom_intelligence_customers');
        Schema::dropIfExists('cartona_order_items');
        Schema::dropIfExists('cartona_orders');
        Schema::dropIfExists('cartona_customers');

        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn(['google_map_url', 'route_code', 'route_name', 'address_notes', 'source']);
        });
    }
};
