<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'user_id' => (string) Str::uuid(),
            'user_name' => $this->faker->name(),
            'user_email' => $this->faker->unique()->safeEmail(),
            'user_password' => bcrypt('password'),
            'user_verified' => true,
            'user_img' => null,
            'user_email_verified_at' => now(),
        ];
    }
}
