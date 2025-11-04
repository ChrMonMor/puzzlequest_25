<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Example: create 3 users
        $users = [
            [
                'user_name' => 'Aan',
                'user_email' => 'aan@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => true,
            ],
            [
                'user_name' => 'San',
                'user_email' => 'san@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => false,
            ],
            [
                'user_name' => 'Dan',
                'user_email' => 'dan@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => true,
            ],
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
