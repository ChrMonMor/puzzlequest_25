<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;
use App\Models\Run;
use App\Models\Flag;
use App\Models\QuestionType;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $runs = Run::all();
        $flags = Flag::all();
        $questionTypes = QuestionType::all();

        if ($runs->isEmpty() || $flags->isEmpty() || $questionTypes->isEmpty()) {
            $this->command->info('Please seed runs, flags, and question types first.');
            return;
        }

        // For example, create 2 questions per flag
        foreach ($flags as $flag) {
            for ($i = 1; $i <= 2; $i++) {
                $questionType = $questionTypes->random();

                Question::create([
                    'run_id' => $flag->run_id,
                    'flag_id' => $flag->flag_id,
                    'question_type' => $questionType->question_type_id,
                    'question_text' => "Sample question {$i} for flag {$flag->flag_number}",
                    'question_answer' => "{$i}",
                ]);
            }
        }
    }
}
