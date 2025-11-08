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
        // Block session-only guests from mutating actions if needed
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['destroy']);
    }

    /**
     * Get current actor: either authenticated user or guest token
     */
    protected function getActor(Request $request)
    {
        $user = auth('api')->user();
        if ($user) {
            return ['type' => 'user', 'id' => $user->user_id];
        }

        $guestToken = $request->bearerToken() ?? $request->query('guest_token');
        if ($guestToken) {
            return ['type' => 'guest', 'id' => $guestToken];
        }

        return null;
    }

    /**
     * Start a new run history for the actor (user or guest)
     */
    public function startRun(Request $request, $runId)
    {
        try {
            $actor = $this->getActor($request);
            if (!$actor) {
                return response()->json(['error' => 'Unauthorized. Please log in or provide guest token.'], 401);
            }

            $run = Run::with('flags')->findOrFail($runId);

            $validator = Validator::make($request->all(), [
                'history_run_position' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Use transaction + locking to prevent duplicate active histories
            $history = DB::transaction(function () use ($actor, $run, $request) {
                $existing = History::where('user_id', $actor['id'])
                    ->where('run_id', $run->run_id)
                    ->whereNull('history_end')
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    throw new Exception('You already have an active history for this run.');
                }

                $h = History::create([
                    'user_id' => $actor['id'],
                    'run_id' => $run->run_id,
                    'history_start' => now(),
                    'history_run_type' => $run->run_type,
                    'history_run_position' => $request->input('history_run_position'),
                    'history_run_update' => $run->run_last_update,
                ]);

                foreach ($run->flags as $flag) {
                    HistoryFlag::create([
                        'history_id' => $h->history_id,
                        'history_flag_reached' => null,
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
            $status = $e->getMessage() === 'You already have an active history for this run.' ? 409 : 500;
            return response()->json(['error' => $e->getMessage()], $status);
        }
    }

    /**
     * Mark the run as finished.
     */
    public function endRun(Request $request, $historyId)
    {
        try {
            $actor = $this->getActor($request);
            if (!$actor) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $history = History::where('history_id', $historyId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($history->user_id !== $actor['id']) {
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
            $actor = $this->getActor($request);
            if (!$actor) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $history = History::where('history_id', $historyId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($history->user_id !== $actor['id']) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $flag = HistoryFlag::where('history_id', $historyId)
                ->where('history_flag_id', $flagId)
                ->firstOrFail();

            $flag->update([
                'history_flag_reached' => now(),
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
     * Get all histories for the actor (user or guest)
     */
    public function index(Request $request)
    {
        $actor = $this->getActor($request);
        if (!$actor) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $histories = History::with(['run', 'flags'])
            ->where('user_id', $actor['id'])
            ->orderByDesc('history_start')
            ->get();

        return response()->json($histories);
    }

    /**
     * Show one history record with all flags
     */
    public function show(Request $request, $historyId)
    {
        $actor = $this->getActor($request);
        if (!$actor) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $history = History::with(['run', 'flags'])->findOrFail($historyId);

        if ($history->user_id !== $actor['id']) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($history);
    }
}
