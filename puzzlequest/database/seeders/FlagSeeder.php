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

        $scale = max(1, (int) env('SEED_SCALE', 1));

        // For each run, create a small cluster of flags (scaled) around a random center to make realistic routes
        foreach ($runs as $run) {
            // choose a random center point for this run
            $centerLat = mt_rand(-5000, 5000) / 100;
            $centerLng = mt_rand(-5000, 5000) / 100;
            $min = max(3, 5 * $scale);
            $max = max($min, 8 * $scale);
            $count = rand($min, $max);
            for ($i = 1; $i <= $count; $i++) {
                // small offsets so flags cluster
                $lat = $centerLat + (mt_rand(-500, 500) / 10000);
                $lng = $centerLng + (mt_rand(-500, 500) / 10000);
                Flag::create([
                    'run_id' => $run->run_id,
                    'flag_number' => $i,
                    'flag_long' => $lng,
                    'flag_lat' => $lat,
                ]);
            }
        }
    }
}
