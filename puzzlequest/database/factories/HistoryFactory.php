<?php

namespace Database\Factories;

use App\Models\History;
use App\Models\Run;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HistoryFactory extends Factory
{
    protected $model = History::class;

    public function definition(): array
    {
        $run = Run::factory()->create();
        return [
            'user_id' => User::factory(),
            'run_id' => $run->id,
            'history_start' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'history_end' => $this->faker->optional(0.5)->dateTimeBetween('now', '+1 day'),
            'history_run_update' => $run->run_last_update,
            'history_run_type' => $this->faker->randomElement(['casual', 'competitive', 'training']),
            'history_run_position' => $this->faker->optional()->word(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'history_end' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $run = Run::find($attributes['run_id']) ?? Run::factory()->create();
            return [
                'history_end' => $this->faker->dateTimeBetween('now', '+1 day'),
                'history_run_update' => $run->run_last_update,
            ];
        });
    }
}
