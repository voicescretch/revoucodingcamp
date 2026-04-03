<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Head Manager
        User::factory()->create([
            'name'      => 'Head Manager',
            'email'     => 'manager@pos.com',
            'password'  => bcrypt('password'),
            'role'      => 'head_manager',
            'is_active' => true,
        ]);

        // Kasir
        User::factory()->create([
            'name'      => 'Kasir',
            'email'     => 'kasir@pos.com',
            'password'  => bcrypt('password'),
            'role'      => 'kasir',
            'is_active' => true,
        ]);

        // Finance
        User::factory()->create([
            'name'      => 'Finance',
            'email'     => 'finance@pos.com',
            'password'  => bcrypt('password'),
            'role'      => 'finance',
            'is_active' => true,
        ]);
    }
}
