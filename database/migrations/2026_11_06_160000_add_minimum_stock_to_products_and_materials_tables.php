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
            if (!Schema::hasColumn('products', 'minimum_stock')) {
                $table->decimal('minimum_stock', 12, 2)->default(0)->after('current_stock');
            }
        });

        Schema::table('materials', function (Blueprint $table) {
            if (!Schema::hasColumn('materials', 'minimum_stock')) {
                $table->decimal('minimum_stock', 12, 2)->default(0)->after('current_stock');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'minimum_stock')) {
                $table->dropColumn('minimum_stock');
            }
        });

        Schema::table('materials', function (Blueprint $table) {
            if (Schema::hasColumn('materials', 'minimum_stock')) {
                $table->dropColumn('minimum_stock');
            }
        });
    }
};

