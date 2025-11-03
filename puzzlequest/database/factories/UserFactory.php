<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'user_email' => $this->faker->unique()->safeEmail,
            'user_password' => Hash::make('password'), // default password
            'user_name' => $this->faker->name,
            'user_verified' => true,
            'user_img' => null,
        ];
    }
}
