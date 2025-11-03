<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Flag;
use App\Models\Run;

class FlagFactory extends Factory
{
    protected $model = Flag::class;

    public function definition()
    {
        return [
            'run_id' => Run::factory(),
            'flag_number' => $this->faker->numberBetween(1, 20),
            'flag_lat' => $this->faker->latitude,
            'flag_long' => $this->faker->longitude,
        ];
    }
}
