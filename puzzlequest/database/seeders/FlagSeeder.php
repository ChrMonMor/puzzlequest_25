<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Flag;
use App\Models\Run;

class FlagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $runs = Run::all();

        if ($runs->isEmpty()) {
            $this->command->info('No runs found. Please seed runs first.');
            return;
        }

        // For each run, create 3 flags as an example
        foreach ($runs as $run) {
            for ($i = 1; $i <= 3; $i++) {
                Flag::create([
                    'run_id' => $run->run_id,
                    'flag_number' => $i,
                    'flag_long' => mt_rand(-18000, 18000) / 100, // Random longitude (-180 to 180)
                    'flag_lat' => mt_rand(-9000, 9000) / 100,    // Random latitude (-90 to 90)
                ]);
            }
        }
    }
}
