<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\RunType;

class RunTypeFactory extends Factory
{
    protected $model = RunType::class;

    public function definition()
    {
        return [
            'run_type_name' => $this->faker->word,
            'run_type_icon' => $this->faker->imageUrl(100, 100, 'icons', true),
        ];
    }
}
