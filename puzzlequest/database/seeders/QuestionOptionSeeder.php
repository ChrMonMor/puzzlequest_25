<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;
use App\Models\QuestionOption;

class QuestionOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = Question::all();

        if ($questions->isEmpty()) {
            $this->command->info('No questions found. Please seed questions first.');
            return;
        }

        foreach ($questions as $question) {
            // Generate 4 sample options per question
            for ($i = 1; $i <= 4; $i++) {
                QuestionOption::create([
                    'question_id' => $question->question_id,
                    'question_option_text' => "Option {$i} for question: {$question->question_text}",
                ]);
            }
        }
    }
}
