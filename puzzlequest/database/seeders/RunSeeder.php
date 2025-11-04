<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Run;
use App\Models\User;
use App\Models\RunType;
use Illuminate\Support\Str;

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

        // Example runs
        $exampleRuns = [
            [
                'run_title' => 'Morning Park Run',
                'run_description' => 'A quick morning run in the park.',
                'run_img_icon' => 'park_icon.png',
                'run_img_front' => 'park_front.png',
                'run_pin' => '123456',
                'run_location' => 'NOR',
            ],
            [
                'run_title' => 'City Night Run',
                'run_description' => 'Enjoy the city lights while running.',
                'run_img_icon' => 'city_icon.png',
                'run_img_front' => 'city_front.png',
                'run_pin' => '567890',
                'run_location' => 'DKK',
            ],
        ];

        foreach ($exampleRuns as $runData) {
            Run::create([
                'user_id' => $users->random()->user_id,
                'run_type' => $runTypes->random()->run_type_id,
                'run_title' => $runData['run_title'],
                'run_description' => $runData['run_description'],
                'run_img_icon' => $runData['run_img_icon'],
                'run_img_front' => $runData['run_img_front'],
                'run_pin' => $runData['run_pin'],
                'run_location' => $runData['run_location'],
                'run_last_update' => now(),
            ]);
        }
    }
}
