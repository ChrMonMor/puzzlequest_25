<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\Flag;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Exception;
use Illuminate\Database\QueryException;

class RunController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    // List runs with optional server-side search & pagination
    public function index(Request $request)
    {
        try {
            $q = $request->query('q');
            $perPage = max(1, (int) $request->query('per_page', 12));

            $query = Run::with(['runType', 'user']);

            if (!empty($q)) {
                $query->where(function($w) use ($q) {
                    $w->where('run_title', 'like', "%{$q}%")
                      ->orWhere('run_pin', 'like', "%{$q}%")
                      ->orWhereHas('user', function($u) use ($q) {
                          $u->where('user_name', 'like', "%{$q}%");
                      });
                });
            }

            $runs = $query->orderByDesc('run_added')->paginate($perPage);

            return response()->json($runs, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch runs', 'details' => $e->getMessage()], 500);
        }
    }

    // Find a run by its pin code
    public function findByPin($pin)
    {
        try {
            if (empty($pin)) return response()->json(['error' => 'Pin required'], 400);

            $run = Run::with(['runType','user'])->where('run_pin', $pin)->first();
            if (!$run) return response()->json(['error' => 'Run not found'], 404);

            return response()->json($run, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to lookup pin', 'details' => $e->getMessage()], 500);
        }
    }

    // Show single run
    public function show($id)
    {
        try {
            $run = Run::with([
                'runType',
                'flags.questions.options',
                'questions.options',
                'questions.questionType'
            ])->findOrFail($id);

            return response()->json($run, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Run not found', 'details' => $e->getMessage()], 404);
        }
    }

    // Create new run
    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            $userId = $user->user_id;

            $validator = Validator::make($request->all(), [
                'run_type' => 'required|integer',
                'run_title' => 'required|string|max:255',
                'run_description' => 'nullable|string',
                'run_img_icon' => 'nullable|string',
                'run_img_front' => 'nullable|string',
                'run_pin' => 'nullable|string',
                'run_location' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $run = Run::create(array_merge($request->only([
                'run_type', 'run_title', 'run_description', 
                'run_img_icon', 'run_img_front', 'run_pin', 'run_location'
            ]), [
                'user_id' => $userId,
                'run_added' => now(),
                'run_last_update' => now(),
            ]));

            // If no run_pin was provided, generate one and try to save it atomically.
            // Use a retry loop that catches DB unique-constraint violations so concurrent
            // requests won't create duplicate pins (race condition between exists() and save()).
            if (empty($run->run_pin)) {
                $attempts = 0;
                $saved = false;
                while ($attempts < 10 && !$saved) {
                    $attempts++;
                    $candidate = strtoupper(Str::random(6));
                    $run->run_pin = $candidate;
                    $run->run_last_update = now();
                    try {
                        $run->save();
                        $saved = true;
                        break;
                    } catch (QueryException $qe) {
                        // Postgres unique violation SQLSTATE is 23505. If another driver,
                        // fall back to checking SQLSTATE or message conservatively.
                        $sqlState = $qe->errorInfo[0] ?? null;
                        if ($sqlState === '23505' || stripos($qe->getMessage() ?? '', 'unique') !== false) {
                            // Collision: try another candidate
                            continue;
                        }
                        // Unknown DB error: rethrow to be handled by outer catch
                        throw $qe;
                    }
                }

                if (!$saved) {
                    return response()->json(['error' => 'Failed to generate unique run pin after several attempts'], 500);
                }
            }

            $run->load([
                'runType',
                'flags.questions.options',
                'questions.options',
                'questions.questionType'
            ]);

            return response()->json(['message' => 'Run created', 'run' => $run], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create run', 'details' => $e->getMessage()], 500);
        }
    }

    // Update run (only owner)
    public function update(Request $request, $id)
    {
        try {
            $run = Run::findOrFail($id);

            $user = auth('api')->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            }

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                // Accept numeric run_type in tests (may not have seeded run_types)
                'run_type' => 'sometimes|integer',
                'run_title' => 'sometimes|string|max:255',
                'run_description' => 'nullable|string',
                'run_img_icon' => 'nullable|string',
                'run_img_front' => 'nullable|string',
                'run_pin' => 'nullable|string',
                'run_location' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $run->update(array_merge($request->only([
                'run_type', 'run_title', 'run_description', 
                'run_img_icon', 'run_img_front', 'run_pin', 'run_location'
            ]), [
                'run_last_update' => now(),
            ]));

            $run->load([
                'runType',
                'flags.questions.options',
                'questions.options',
                'questions.questionType'
            ]);

            return response()->json(['message' => 'Run updated', 'run' => $run], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update run', 'details' => $e->getMessage()], 500);
        }
    }

    // Delete run (only owner)
    public function destroy($id)
    {
        try {
            $run = Run::findOrFail($id);

            $user = auth('api')->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            }

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $run->delete();

            return response()->json(['message' => 'Run deleted'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete run', 'details' => $e->getMessage()], 500);
        }
    }

    // Generate a unique 6-character alphanumeric pin for a run and save it
    public function generatePin($id)
    {
        try {
            $run = Run::findOrFail($id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($run->user_id !== $user->user_id) return response()->json(['error' => 'Unauthorized'], 403);

            $attempts = 0;
            $saved = false;
            $pin = null;
            while ($attempts < 10 && !$saved) {
                $attempts++;
                $candidate = strtoupper(Str::random(6));
                $run->run_pin = $candidate;
                $run->run_last_update = now();
                try {
                    $run->save();
                    $saved = true;
                    $pin = $candidate;
                    break;
                } catch (QueryException $qe) {
                    $sqlState = $qe->errorInfo[0] ?? null;
                    if ($sqlState === '23505' || stripos($qe->getMessage() ?? '', 'unique') !== false) {
                        // Collision: try again
                        continue;
                    }
                    throw $qe;
                }
            }

            if (!$saved) return response()->json(['error' => 'Failed to generate unique pin'], 500);

            return response()->json(['message' => 'Pin generated', 'pin' => $pin, 'run' => $run], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to generate pin', 'details' => $e->getMessage()], 500);
        }
    }

    // Bulk operations for flags and questions are implemented in their respective controllers
    // (FlagController and QuestionController) and the routes point to those implementations.
}
