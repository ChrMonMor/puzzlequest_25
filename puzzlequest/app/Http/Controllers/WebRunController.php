<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Run;
use Tymon\JWTAuth\Facades\JWTAuth;

class WebRunController extends Controller
{
    /**
     * Show a list of runs (all runs)
     */
    public function index()
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

        return view('runs.index', ['runs' => $runs, 'title' => 'My Runs']);
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
    public function edit($id)
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
}
