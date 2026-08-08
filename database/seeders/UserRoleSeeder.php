<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run()
    {
        // Admin Account
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@medfind.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
        
        // Pharmacy Operator Account
        User::create([
            'name' => 'Mercury Drug Operator',
            'email' => 'pharmacy@medfind.com',
            'password' => Hash::make('password'),
            'role' => 'pharmacy',
        ]);
        
        // Regular Consumer Account
        User::create([
            'name' => 'John Consumer',
            'email' => 'consumer@medfind.com',
            'password' => Hash::make('password'),
            'role' => 'consumer',
        ]);
    }
}