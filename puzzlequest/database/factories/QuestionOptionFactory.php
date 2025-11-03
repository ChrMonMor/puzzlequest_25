<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\QuestionOption;
use App\Models\Question;
use Illuminate\Support\Str;

class QuestionOptionFactory extends Factory
{
    protected $model = QuestionOption::class;

    public function definition()
    {
        return [
            'question_option_id' => (string) Str::uuid(),
            'question_id' => Question::factory(),
            'question_option_text' => $this->faker->word,
        ];
    }
}
