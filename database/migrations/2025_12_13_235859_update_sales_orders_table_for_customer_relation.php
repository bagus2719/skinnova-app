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
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->dropColumn(['customer_name', 'customer_phone', 'customer_email']);
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete()->after('reference_no');
            $table->text('shipping_address')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_orders', function (Blueprint $table) {
            $table->string('customer_name')->after('reference_no');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
