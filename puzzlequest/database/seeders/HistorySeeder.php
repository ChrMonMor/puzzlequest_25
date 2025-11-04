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

        foreach ($users as $user) {
            foreach ($runs as $run) {
                $startDate = now()->subDays(rand(1, 10));
                $history = History::create([
                    'user_id' => $user->user_id,
                    'run_id' => $run->run_id,
                    'history_start' => $startDate,
                    'history_end' => $startDate->copy()->addDays(rand(0, 5)),
                    'history_run_update' => now(),
                    'history_run_type' => $run->run_type,
                    'history_run_position' => random_int(1,10),
                ]);

                // Add HistoryFlags for this history based on run flags
                foreach ($run->flags as $flag) {
                    HistoryFlag::create([
                        'history_id' => $history->history_id,
                        'history_flag_reached' => $startDate->addHours(rand(1, 2)),
                        'history_flag_long' => $flag->flag_long,
                        'history_flag_lat' => $flag->flag_lat,
                        'history_flag_distance' => rand(10, 100),
                        'history_flag_type' => null,
                        'history_flag_point' => rand(0, 10),
                    ]);
                }
            }
        }
        // Generate a guest history
        $guestUuid = (string) Str::uuid();
        $startDate = now()->subDays(rand(1,10));
        $history = History::create([
            'user_id' => $guestUuid, // store guest UUID
            'run_id' => $run->run_id,
            'history_start' => $startDate,
            'history_end' => null, // ongoing
            'history_run_update' => now(),
            'history_run_type' => $run->run_type,
            'history_run_position' =>  rand(0, 10),
        ]);

        // Create flags for guest history
        foreach ($run->flags as $flag) {
            HistoryFlag::create([
                'history_id' => $history->history_id,
                'history_flag_reached' => $startDate->addHours(rand(1, 2)),
                'history_flag_long' => $flag->flag_long,
                'history_flag_lat' => $flag->flag_lat,
                'history_flag_distance' => null,
                'history_flag_type' => null,
                'history_flag_point' => 0,
            ]);
        }
    }
}
