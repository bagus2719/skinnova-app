<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds for default department users.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Super Administrator',
                'email' => 'admin@skinnova.com',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567890',
            ],
            [
                'name' => 'Staff Sales & Marketing',
                'email' => 'sales@skinnova.com',
                'role' => 'sales',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567891',
            ],
            [
                'name' => 'Staff Purchasing',
                'email' => 'purchasing@skinnova.com',
                'role' => 'purchasing',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567892',
            ],
            [
                'name' => 'Staff Gudang / Inventory',
                'email' => 'gudang@skinnova.com',
                'role' => 'inventory',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567893',
            ],
            [
                'name' => 'Supervisor Produksi',
                'email' => 'produksi@skinnova.com',
                'role' => 'production',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567894',
            ],
            [
                'name' => 'Staff Finance & Akuntansi',
                'email' => 'finance@skinnova.com',
                'role' => 'finance',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567895',
            ],
            [
                'name' => 'Staff Human Resources',
                'email' => 'hr@skinnova.com',
                'role' => 'hr',
                'password' => Hash::make('password'),
                'is_active' => true,
                'phone' => '081234567896',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
