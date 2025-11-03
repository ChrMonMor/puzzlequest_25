<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Run;
use App\Models\User;
use App\Models\RunType;

class RunFactory extends Factory
{
    protected $model = Run::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'run_title' => $this->faker->sentence,
            'run_description' => $this->faker->paragraph,
            'run_type' => RunType::factory(),
        ];
    }
}
