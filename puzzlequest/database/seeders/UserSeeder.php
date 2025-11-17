<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Create a handful of realistic users with Faker
        $faker = Faker::create();

        // Seed a couple of known users for testing
        $known = [
            [
                'user_name' => 'Aan',
                'user_email' => 'aan@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => true,
                'user_img'=> 'androgynousDefault.png',
            ],
            [
                'user_name' => 'San',
                'user_email' => 'san@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => false,
                'user_img'=> 'androgynousDefault.png',
            ],
            [
                'user_name' => 'Dan',
                'user_email' => 'dan@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => true,
                'user_img'=> 'androgynousDefault.png',
            ],
            [
                'user_name' => 'player',
                'user_email' => 'player@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => true,
                'user_img'=> 'androgynousDefault.png',
            ],
        ];

        foreach ($known as $u) { User::create($u); }

        // scale factor (set SEED_SCALE in your .env to ramp up)
        $scale = max(1, (int) env('SEED_SCALE', 1));

        // Generate additional random users
        for ($i = 0; $i < 10 * $scale; $i++) {
            $name = $faker->unique()->userName();
            User::create([
                'user_name' => $name,
                'user_email' => $name . '@example.com',
                'user_password' => Hash::make('password123'),
                'user_verified' => $faker->boolean(70),
                'user_img'=> 'androgynousDefault.png',
            ]);
        }
    }
}
