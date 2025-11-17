<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Run;
use App\Models\History;
use App\Models\HistoryFlag;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebRunController extends Controller
{
    /**
     * Show a list of runs (all runs)
     */
    public function index(Request $request)
    {
        $runs = Run::with('user')->orderBy('run_added', 'desc')->get();
        return view('runs.index', ['runs' => $runs, 'title' => 'All Runs']);
    }

    /**
     * Show runs belonging to the authenticated user
     */
    public function myRuns(Request $request)
    {
        // Resolve the current user: prefer the session/web guard, but fall back to a JWT stored in session
        $user = auth('api')->user();
        if (!$user && $request->session()->has('jwt_token')) {
            try {
                $token = $request->session()->get('jwt_token');
                $jwtUser = JWTAuth::setToken($token)->toUser();
                if ($jwtUser) {
                    Auth::login($jwtUser);
                    $user = $jwtUser;
                }
            } catch (\Throwable $e) {
                // ignore token problems
            }
        }

        $runs = collect();
        if ($user) {
            $runs = Run::where('user_id', $user->user_id)->orderBy('run_added', 'desc')->get();
        }

        return view('runs.index', ['runs' => $runs, 'title' => 'My Runs', 'onlyMine' => true]);
    }

    /**
     * Show a single run
     */
    public function show($id)
    {
        $run = Run::with(['user', 'flags', 'questions'])->where('run_id', $id)->firstOrFail();

        // Load a small list of recent histories for this run (users who ran it)
        // include history flags so we can plot where players reached flags
        $histories = \App\Models\History::with(['user','flags'])
            ->where('run_id', $run->run_id)
            ->orderByDesc('history_start')
            ->limit(10)
            ->get();

        return view('runs.show', ['run' => $run, 'histories' => $histories]);
    }

    /**
     * Edit form (placeholder) - only accessible by owner
     */
    public function edit(Request $request, $id)
    {
        $run = Run::where('run_id', $id)->firstOrFail();
        $user = auth('api')->user();
        if (!$user && request()->session()->has('jwt_token')) {
            try {
                $token = request()->session()->get('jwt_token');
                $jwtUser = JWTAuth::setToken($token)->toUser();
                if ($jwtUser) {
                    Auth::login($jwtUser);
                    $user = $jwtUser;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (!$user || $user->user_id !== $run->user_id) {
            return redirect()->route('runs.show', $run->run_id)->with('error', 'You are not authorized to edit this run.');
        }

        return view('runs.edit', ['run' => $run]);
    }

    /**
     * Owner-only live view showing active runners on a map
     */
    public function live(Request $request, $id)
    {
        $run = Run::where('run_id', $id)->firstOrFail();

        // resolve the current user similarly to other pages
        $user = auth('api')->user();
        if (!$user && $request->session()->has('jwt_token')) {
            try {
                $token = $request->session()->get('jwt_token');
                $jwtUser = JWTAuth::setToken($token)->toUser();
                if ($jwtUser) {
                    Auth::login($jwtUser);
                    $user = $jwtUser;
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (!$user || $user->user_id !== $run->user_id) {
            abort(403, 'You are not authorized to view this live map');
        }

        return view('runs.live', ['run' => $run]);
    }

    /**
     * JSON: Active runners in a run with last point and reached path
     */
    public function liveData(Request $request, $id)
    {
        $run = Run::where('run_id', $id)->firstOrFail();

        // authorize owner
        $user = auth('api')->user();
        if (!$user && $request->session()->has('jwt_token')) {
            try {
                $token = $request->session()->get('jwt_token');
                $jwtUser = JWTAuth::setToken($token)->toUser();
                if ($jwtUser) $user = $jwtUser;
            } catch (\Throwable $e) {
                // ignore
            }
        }
        if (!$user || $user->user_id !== $run->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Active histories are those without an end time
        $histories = History::with(['user', 'flags'])
            ->where('run_id', $run->run_id)
            ->whereNull('history_end')
            ->orderByDesc('history_start')
            ->get();

        $payload = $histories->map(function ($h) {
            // Only include flags actually reached; order by provided point if available, else by timestamp
            $reached = $h->flags
                ->filter(function ($f) { return !is_null($f->history_flag_reached); })
                ->values()
                ->sort(function ($a, $b) {
                    $ap = $a->history_flag_point ?? PHP_INT_MAX;
                    $bp = $b->history_flag_point ?? PHP_INT_MAX;
                    if ($ap !== $bp) return $ap <=> $bp;
                    $at = $a->history_flag_reached ? strtotime($a->history_flag_reached) : 0;
                    $bt = $b->history_flag_reached ? strtotime($b->history_flag_reached) : 0;
                    return $at <=> $bt;
                });

            $path = $reached->map(function ($f) {
                return [
                    'lat' => (float) $f->history_flag_lat,
                    'lng' => (float) $f->history_flag_long,
                    'point' => $f->history_flag_point,
                    'reached_at' => $f->history_flag_reached,
                ];
            })->values();

            $last = $path->count() ? $path->last() : null;

            return [
                'history_id' => $h->history_id,
                'user' => [
                    'user_id' => $h->user->user_id ?? null,
                    'name' => $h->user->user_name ?? $h->user->name ?? 'Guest',
                    'img' => $h->user->user_img ?? null,
                ],
                'started_at' => $h->history_start,
                'last' => $last,
                'path' => $path,
            ];
        })->values();

        return response()->json([
            'run_id' => $run->run_id,
            'updated' => now()->toIso8601String(),
            'runners' => $payload,
        ]);
    }
}
