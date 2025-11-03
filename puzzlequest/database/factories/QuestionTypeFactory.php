<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\QuestionType;

class QuestionTypeFactory extends Factory
{
    protected $model = QuestionType::class;

    public function definition()
    {
        return [
            'question_type_name' => $this->faker->word,
        ];
    }
}
