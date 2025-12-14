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
        Schema::create('purchase_bills', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('purchase_order_id')->nullable()->constrained('purchase_orders')->nullOnDelete(); 
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('bill_date');
            $table->date('due_date');
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->decimal('paid_amount', 15, 2)->default(0.00);
            $table->enum('status', ['Draft', 'Open', 'Paid', 'Cancelled'])->default('Draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_bill_id')->constrained('purchase_bills')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials')->restrictOnDelete(); 
            $table->string('description')->nullable();
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('sub_total', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_bills');
        Schema::dropIfExists('purchase_bill_items');
    }
};
