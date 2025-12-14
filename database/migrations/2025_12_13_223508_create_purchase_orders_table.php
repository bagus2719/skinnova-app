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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('order_date');
            $table->date('expected_receipt_date')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0.00);            
            $table->enum('status', ['Draft', 'Sent', 'Received', 'Cancelled'])->default('Draft');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
