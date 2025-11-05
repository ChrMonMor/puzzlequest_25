<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuestionType;

class QuestionTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example question types
        $types = [
            ['question_type_name' => 'Multiple Choice'],
            ['question_type_name' => 'Physical Challenge'],
            ['question_type_name' => 'True/False'],
            ['question_type_name' => 'Link'],
            ['question_type_name' => 'Video'],
            ['question_type_name' => 'Photo'],
            ['question_type_name' => 'Text'],
        ];

        foreach ($types as $type) {
            QuestionType::create($type);
        }
    }
}
