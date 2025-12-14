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
        // // Menambahkan kolom profile_image ke tabel 'vendors'
        // Schema::table('vendors', function (Blueprint $table) {
        //     $table->string('profile_image')->nullable()->after('address');
        // });

        // // Menambahkan kolom profile_image ke tabel 'customers'
        // Schema::table('customers', function (Blueprint $table) {
        //     $table->string('profile_image')->nullable()->after('address');
        // });

        // Menambahkan kolom profile_image ke tabel 'employees'
        Schema::table('employees', function (Blueprint $table) {
            $table->string('profile_image')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // // Menghapus kolom profile_image dari tabel 'vendors'
        // Schema::table('vendors', function (Blueprint $table) {
        //     $table->dropColumn('profile_image');
        // });

        // // Menghapus kolom profile_image dari tabel 'customers'
        // Schema::table('customers', function (Blueprint $table) {
        //     $table->dropColumn('profile_image');
        // });

        // Menghapus kolom profile_image dari tabel 'employees'
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('profile_image');
        });
    }
};