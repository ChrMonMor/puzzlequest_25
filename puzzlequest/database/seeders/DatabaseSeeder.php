<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            RunTypeSeeder::class,
            RunSeeder::class,
            FlagSeeder::class,
            QuestionTypeSeeder::class,
            QuestionSeeder::class,
            // QuestionOptionSeeder::class, // Commented out to avoid duplicate seeding
            HistorySeeder::class,
        ]);
    }
}
