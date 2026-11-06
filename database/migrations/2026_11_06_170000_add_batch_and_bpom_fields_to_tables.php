<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'bpom_number')) {
                $table->string('bpom_number')->nullable()->after('sku');
            }
            if (!Schema::hasColumn('products', 'shelf_life_months')) {
                $table->integer('shelf_life_months')->default(24)->after('bpom_number');
            }
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('manufacturing_orders', 'batch_number')) {
                $table->string('batch_number')->nullable()->after('reference_no');
            }
            if (!Schema::hasColumn('manufacturing_orders', 'expired_date')) {
                $table->date('expired_date')->nullable()->after('finished_at');
            }
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_order_items', 'batch_number')) {
                $table->string('batch_number')->nullable()->after('product_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'bpom_number')) {
                $table->dropColumn('bpom_number');
            }
            if (Schema::hasColumn('products', 'shelf_life_months')) {
                $table->dropColumn('shelf_life_months');
            }
        });

        Schema::table('manufacturing_orders', function (Blueprint $table) {
            if (Schema::hasColumn('manufacturing_orders', 'batch_number')) {
                $table->dropColumn('batch_number');
            }
            if (Schema::hasColumn('manufacturing_orders', 'expired_date')) {
                $table->dropColumn('expired_date');
            }
        });

        Schema::table('delivery_order_items', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_order_items', 'batch_number')) {
                $table->dropColumn('batch_number');
            }
        });
    }
};
