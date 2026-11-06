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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->dateTime('movement_date');
            $table->enum('type', ['IN', 'OUT']);
            $table->enum('item_type', ['Product', 'Material']);
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('material_id')->nullable()->constrained('materials')->nullOnDelete();
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('stock_before', 12, 2)->default(0);
            $table->decimal('stock_after', 12, 2)->default(0);
            $table->string('reference_type'); // e.g. Good Receipt, Manufacturing Order, Delivery Order, Penyesuaian
            $table->string('reference_no');   // e.g. GR-0001, MO-0001, DO-0001, ADJ-0001
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['item_type', 'product_id']);
            $table->index(['item_type', 'material_id']);
            $table->index('batch_number');
            $table->index('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
