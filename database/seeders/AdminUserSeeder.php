<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@pasarkoin.test',
            ],
            [
                'name' => 'Admin PasarKoin',
                'password' => 'password123',
                'role' => 'admin',
            ]
        );
    }
}