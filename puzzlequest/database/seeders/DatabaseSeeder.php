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
        $scale = max(1, (int) env('SEED_SCALE', 1));
        if ($scale > 1) {
            $this->command->info("Seeding with SEED_SCALE={$scale} — this may create a large amount of data.");
        }

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
