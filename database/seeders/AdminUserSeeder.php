<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'username' => 'admin',
            'email' => 'admin@helpdesk.com',
            'password' => Hash::make('password'),
            'role_id' => 1, // admin role
            'area_id' => null,
            'is_active' => true,
        ]);
    }
}
