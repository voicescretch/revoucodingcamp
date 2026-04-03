<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Head Manager',  'email' => 'manager@pos.com',  'role' => 'head_manager'],
            ['name' => 'Kasir 1',       'email' => 'kasir@pos.com',    'role' => 'kasir'],
            ['name' => 'Finance Staff', 'email' => 'finance@pos.com',  'role' => 'finance'],
        ];

        foreach ($users as $u) {
            User::firstOrCreate(
                ['email' => $u['email']],
                array_merge($u, ['password' => bcrypt('password'), 'is_active' => true])
            );
        }
    }
}
