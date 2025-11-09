<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Run;
use App\Models\User;
use App\Models\RunType;
use Illuminate\Support\Str;
use Faker\Factory as Faker;

class RunSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some existing users and run types
        $users = User::all();
        $runTypes = RunType::all();

        if ($users->isEmpty() || $runTypes->isEmpty()) {
            $this->command->info('No users or run types found. Please seed users and run types first.');
            return;
        }

    $faker = Faker::create();

    $scale = max(1, (int) env('SEED_SCALE', 1));

    // A few curated runs
        $templates = [
            ['title' => 'Morning Park Run', 'desc' => 'A quick morning run in the park.', 'img_icon' => 'park_icon.png', 'img_front' => 'park_front.png', 'loc' => 'NOR'],
            ['title' => 'City Night Run', 'desc' => 'Enjoy the city lights while running.', 'img_icon' => 'city_icon.png', 'img_front' => 'city_front.png', 'loc' => 'DKK'],
            ['title' => 'Riverside Trail', 'desc' => 'Follow the river and solve puzzles along the way.', 'img_icon' => 'river_icon.png', 'img_front' => 'river_front.png', 'loc' => 'SWE'],
            ['title' => 'Old Town Walk', 'desc' => 'Historic route through the old town, family friendly.', 'img_icon' => 'town_icon.png', 'img_front' => 'town_front.png', 'loc' => 'GBR'],
        ];

        // Create curated runs
        foreach ($templates as $t) {
            Run::create([
                'user_id' => $users->random()->user_id,
                'run_type' => $runTypes->random()->run_type_id,
                'run_title' => $t['title'],
                'run_description' => $t['desc'],
                'run_img_icon' => $t['img_icon'],
                'run_img_front' => $t['img_front'],
                'run_pin' => Str::upper(Str::random(6)),
                'run_location' => $t['loc'],
                'run_last_update' => now(),
            ]);
        }

        // Create several random runs (scaled)
        for ($i = 0; $i < (6 * $scale); $i++) {
            Run::create([
                'user_id' => $users->random()->user_id,
                'run_type' => $runTypes->random()->run_type_id,
                'run_title' => ucfirst($faker->words(rand(2,4), true)),
                'run_description' => $faker->sentence(rand(6,14)),
                'run_img_icon' => 'default_icon.png',
                'run_img_front' => 'default_front.png',
                'run_pin' => Str::upper(Str::random(6)),
                'run_location' => $faker->countryCode(),
                'run_last_update' => now()->subDays(rand(0,30)),
            ]);
        }
    }
}
