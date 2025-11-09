<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;
use App\Models\Run;
use App\Models\Flag;
use App\Models\QuestionType;
use App\Models\QuestionOption;

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

        $scale = max(1, (int) env('SEED_SCALE', 1));

        // Create a variable number of questions per flag with several options (scaled)
        foreach ($flags as $flag) {
            $numQuestions = rand(1, max(1, 3 * $scale));
            for ($q = 1; $q <= $numQuestions; $q++) {
                $questionType = $questionTypes->random();
                $question = Question::create([
                    'run_id' => $flag->run_id,
                    'flag_id' => $flag->flag_id,
                    'question_type' => $questionType->question_type_id,
                    'question_text' => "What is notable about flag {$flag->flag_number}?",
                ]);

                // create 3- up to (5*scale) options, but cap at 8
                $opts = rand(3, min(8, 5 * $scale));
                $createdOptions = [];
                for ($o = 1; $o <= $opts; $o++) {
                    $opt = QuestionOption::create([
                        'question_id' => $question->question_id,
                        'question_option_text' => "Option {$o} for flag {$flag->flag_number}",
                    ]);
                    $createdOptions[] = $opt;
                }

                // choose an answer at random
                if (!empty($createdOptions)) {
                    $answer = $createdOptions[array_rand($createdOptions)];
                    $question->update(['question_answer' => $answer->question_option_id]);
                }
            }
        }
    }
}
