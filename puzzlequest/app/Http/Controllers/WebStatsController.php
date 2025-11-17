<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\History;
use App\Models\Run;
use App\Models\HistoryFlag;

class WebStatsController extends Controller
{
    /**
     * Show a single user's histories (all runs they've done)
     */
    public function show(Request $request, $userId)
    {
        $user = User::where('user_id', $userId)->firstOrFail();

        // resolve current user (web guard or jwt in session)
        $currentUser = auth('api')->user();
        if (!$currentUser && $request->session()->has('jwt_token')) {
            try {
                $token = $request->session()->get('jwt_token');
                $jwtUser = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->toUser();
                if ($jwtUser) $currentUser = $jwtUser;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // Only the owner may view another user's histories
        if (!$currentUser || ($currentUser->user_id !== $userId)) {
            abort(403, 'Not authorized to view this user histories');
        }

        // include history flags and the run's flags so the view can render maps
        $histories = History::with(['run','flags','run.flags'])
            ->where('user_id', $userId)
            ->orderByDesc('history_start')
            ->get();

        return view('stats.show', ['user' => $user, 'histories' => $histories]);
    }

    /**
     * Show aggregated stats for a single run (only owner may view)
     */
    public function run(Request $request, $runId)
    {
        $run = Run::withCount('flags')->where('run_id', $runId)->firstOrFail();

        // resolve current user (web guard or jwt in session)
        $user = auth('api')->user();
        if (!$user && $request->session()->has('jwt_token')) {
            try {
                $token = $request->session()->get('jwt_token');
                $jwtUser = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->toUser();
                if ($jwtUser) $user = $jwtUser;
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (!$user || $user->user_id !== $run->user_id) {
            abort(403, 'Not authorized to view run stats');
        }

        $histories = History::with(['flags','user'])
            ->where('run_id', $run->run_id)
            ->orderByDesc('history_start')
            ->get();
        $totalHistories = $histories->count();
        $completedHistories = $histories->whereNotNull('history_end')->count();
        $uniquePlayers = $histories->pluck('user_id')->filter()->unique()->count();

    $historyIds = $histories->pluck('history_id')->toArray();

        $totalReached = 0;
        $totalPoints = 0;
        if (!empty($historyIds)) {
            $totalReached = HistoryFlag::whereIn('history_id', $historyIds)->whereNotNull('history_flag_reached')->count();
            $totalPoints = HistoryFlag::whereIn('history_id', $historyIds)->sum('history_flag_point');
        }

        $possiblePerHistory = (int) $run->flags_count;
        $totalPossible = $possiblePerHistory * $totalHistories;
        $completionPercent = $totalPossible ? round(($totalReached / $totalPossible) * 100, 1) : 0;
        $averagePoints = $totalHistories ? round($totalPoints / $totalHistories, 1) : 0;

        // average duration for completed histories (in seconds)
        $durations = $histories->filter(function($h){ return $h->history_start && $h->history_end; })->map(function($h){
            return strtotime($h->history_end) - strtotime($h->history_start);
        });
        $avgDurationSeconds = $durations->count() ? (int) round($durations->sum() / $durations->count()) : null;

        // Prepare datasets for charts (per-history ordered by start ascending)
        $chartHistories = $histories->sortBy('history_start')->values();
        $labels = $chartHistories->map(function($h){ return $h->history_start ? date('Y-m-d H:i', strtotime($h->history_start)) : 'unknown'; })->toArray();
        $pointsSeries = $chartHistories->map(function($h){ return $h->flags ? $h->flags->sum('history_flag_point') : 0; })->toArray();
        $reachedSeries = $chartHistories->map(function($h){ return $h->flags ? $h->flags->filter(function($f){ return !is_null($f->history_flag_reached); })->count() : 0; })->toArray();
        $durationSeries = $chartHistories->map(function($h){ return ($h->history_start && $h->history_end) ? (strtotime($h->history_end) - strtotime($h->history_start)) : null; })->toArray();

        return view('stats.run', [
            'run' => $run,
            'totalHistories' => $totalHistories,
            'completedHistories' => $completedHistories,
            'uniquePlayers' => $uniquePlayers,
            'totalReached' => $totalReached,
            'totalPoints' => $totalPoints,
            'completionPercent' => $completionPercent,
            'averagePoints' => $averagePoints,
            'avgDurationSeconds' => $avgDurationSeconds,
            'histories' => $histories,
            // chart datasets and helper values
            'labels' => $labels,
            'pointsSeries' => $pointsSeries,
            'reachedSeries' => $reachedSeries,
            'durationSeries' => $durationSeries,
            'totalPossible' => $totalPossible,
            'possiblePerHistory' => $possiblePerHistory,
        ]);
    }
}
