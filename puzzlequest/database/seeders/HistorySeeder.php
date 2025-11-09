<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Run;
use App\Models\History;
use App\Models\HistoryFlag;
use Illuminate\Support\Str;

class HistorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();
        $runs = Run::with('flags')->get();

        if ($users->isEmpty() || $runs->isEmpty()) {
            $this->command->info('No users or runs found. Seed them first.');
            return;
        }

        $scale = max(1, (int) env('SEED_SCALE', 1));

        // Create realistic histories: not every user plays every run. For each run pick some users and create plays (scaled).
        foreach ($runs as $run) {
            $sampleFraction = 0.3 * $scale;
            $sampleCount = max(2, min($users->count(), (int) round($users->count() * $sampleFraction)));
            $playerSample = $users->random($sampleCount);
            if ($playerSample instanceof \App\Models\User) {
                $playerSample = collect([$playerSample]);
            }
            foreach ($playerSample as $user) {
                $plays = rand(1, max(1, 3 * $scale));
                for ($p = 0; $p < $plays; $p++) {
                    $startDate = now()->subDays(rand(1, 60))->subHours(rand(0,23))->subMinutes(rand(0,59));
                    $ended = rand(0,100) > 20 ? $startDate->copy()->addMinutes(rand(10, 120)) : null; // some plays unfinished
                    $history = History::create([
                        'user_id' => $user->user_id,
                        'run_id' => $run->run_id,
                        'history_start' => $startDate,
                        'history_end' => $ended,
                        'history_run_update' => now(),
                        'history_run_type' => $run->run_type,
                        'history_run_position' => random_int(1,10),
                    ]);

                    // For each flag, randomly decide if reached
                    foreach ($run->flags as $flag) {
                        $reached = rand(0,100) < 70; // 70% chance the flag was reached by this play
                        HistoryFlag::create([
                            'history_id' => $history->history_id,
                            'history_flag_reached' => $reached ? $startDate->copy()->addMinutes(rand(1, 90)) : null,
                            'history_flag_long' => $flag->flag_long,
                            'history_flag_lat' => $flag->flag_lat,
                            'history_flag_distance' => $reached ? rand(5, 200) : null,
                            'history_flag_type' => null,
                            'history_flag_point' => $reached ? rand(0, 1) : 0,
                        ]);
                    }
                }
            }
        }

        // Generate a few guest histories (anonymous players) scaled
        for ($g = 0; $g < max(1, 3 * $scale); $g++) {
            $guestUuid = (string) Str::uuid();
            $run = $runs->random();
            $startDate = now()->subDays(rand(1,30))->subHours(rand(0,23));
            $history = History::create([
                'user_id' => $guestUuid,
                'run_id' => $run->run_id,
                'history_start' => $startDate,
                'history_end' => null,
                'history_run_update' => now(),
                'history_run_type' => $run->run_type,
                'history_run_position' => rand(0,10),
            ]);

            foreach ($run->flags as $flag) {
                HistoryFlag::create([
                    'history_id' => $history->history_id,
                    'history_flag_reached' => rand(0,100) < 50 ? $startDate->copy()->addMinutes(rand(1,60)) : null,
                    'history_flag_long' => $flag->flag_long,
                    'history_flag_lat' => $flag->flag_lat,
                    'history_flag_distance' => null,
                    'history_flag_type' => null,
                    'history_flag_point' => 0,
                ]);
            }
        }
    }
}
