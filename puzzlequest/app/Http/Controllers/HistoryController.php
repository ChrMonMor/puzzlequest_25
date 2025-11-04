<?php

namespace App\Http\Controllers;

use App\Models\History;
use App\Models\HistoryFlag;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Exception;

class HistoryController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    /**
     * Start a new run history for the authenticated user.
     */
    public function startRun(Request $request, $runId)
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            }

            $run = Run::with('flags')->findOrFail($runId);

            // Prevent duplicate active histories for the same run
            $existing = History::where('user_id', $user->user_id)
                ->where('run_id', $runId)
                ->whereNull('history_end')
                ->first();

            if ($existing) {
                return response()->json([
                    'error' => 'You already have an active history for this run.'
                ], 409);
            }

            $validator = Validator::make($request->all(), [
                'history_run_position' => 'nullable|string|max:255', // could be lat/long
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Transactionally create the history and its flags
            $history = DB::transaction(function () use ($run, $user, $request) {
                $h = History::create([
                    'user_id' => $user->user_id,
                    'run_id' => $run->run_id,
                    'history_start' => now(),
                    'history_run_type' => $run->run_type,
                    'history_run_position' => $request->input('history_run_position'),
                ]);

                // Copy flags from run to history_flags
                foreach ($run->flags as $flag) {
                    HistoryFlag::create([
                        'history_id' => $h->history_id,
                        'history_flag_reached' => false,
                        'history_flag_long' => $flag->flag_long,
                        'history_flag_lat' => $flag->flag_lat,
                        'history_flag_distance' => null,
                        'history_flag_type' => null,
                        'history_flag_point' => null,
                    ]);
                }

                return $h;
            });

            $history->load(['flags', 'run']);

            return response()->json([
                'message' => 'Run started successfully',
                'history' => $history
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to start run',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark the run as finished.
     */
    public function endRun($historyId)
    {
        try {
            $user = auth('api')->user();
            $history = History::findOrFail($historyId);

            if ($history->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $history->update([
                'history_end' => now(),
                'history_run_update' => now(),
            ]);

            return response()->json(['message' => 'Run ended successfully', 'history' => $history]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to end run', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a flag as reached within a run history.
     */
    public function markFlagReached(Request $request, $historyId, $flagId)
    {
        try {
            $user = auth('api')->user();
            $history = History::findOrFail($historyId);

            if ($history->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $flag = HistoryFlag::where('history_id', $historyId)
                ->where('history_flag_id', $flagId)
                ->firstOrFail();

            $flag->update([
                'history_flag_reached' => true,
                'history_flag_point' => $request->input('history_flag_point', 0),
                'history_flag_distance' => $request->input('history_flag_distance'),
            ]);

            $history->update(['history_run_update' => now()]);

            return response()->json(['message' => 'Flag marked as reached', 'flag' => $flag]);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to mark flag', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get user’s run history list
     */
    public function index()
    {
        $user = auth('api')->user();
        $histories = History::with(['run', 'flags'])
            ->where('user_id', $user->user_id)
            ->orderByDesc('history_start')
            ->get();

        return response()->json($histories);
    }

    /**
     * Show one history record with all flags
     */
    public function show($historyId)
    {
        $user = auth('api')->user();
        $history = History::with(['run', 'flags'])->findOrFail($historyId);

        if ($history->user_id !== $user->user_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($history);
    }
}
