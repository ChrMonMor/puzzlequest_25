<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RunType;

class RunTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example run types
        $runTypes = [
            ['run_type_name' => 'Private', 'run_type_icon'=> '🔒'],
            ['run_type_name' => 'Public', 'run_type_icon'=> '🌐'],
            ['run_type_name' => 'Sprint', 'run_type_icon'=> '🏃‍♂️'],
            ['run_type_name' => 'Free Run', 'run_type_icon'=> '🕊️'],
            ['run_type_name' => 'Trail Run', 'run_type_icon'=> '🌲'],
            ['run_type_name' => 'Obstacle Course', 'run_type_icon'=> '🧗‍♂️'],
            ['run_type_name' => 'Orienteering Run', 'run_type_icon'=> '🗺️'],
            ['run_type_name' => 'Hike', 'run_type_icon'=> '🥾'],
        ];

        foreach ($runTypes as $type) {
            RunType::create($type);
        }
    }
}
