<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Question;
use App\Models\Run;
use App\Models\Flag;
use App\Models\QuestionType;
use Illuminate\Support\Str;

class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition()
    {
        return [
            'question_id' => (string) Str::uuid(),
            'run_id' => Run::factory(),
            'flag_id' => Flag::factory(),
            'question_type' => QuestionType::factory(),
            'question_text' => $this->faker->sentence,
            'question_answer' => $this->faker->word,
        ];
    }
}
