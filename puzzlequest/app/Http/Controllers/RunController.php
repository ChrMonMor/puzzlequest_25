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

/**
 * @group Runs
 * @authenticated
 *
 * APIs for creating, listing and managing puzzle runs.
 */
class RunController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    
    /**
     * List runs with optional server-side search & pagination
     *
     * @unauthenticated
     * @queryParam q string optional Search term to filter runs by title, pin, or owner. Example: "downtown"
     * @queryParam page integer optional Page number. Example: 1
     * @queryParam per_page integer optional Results per page. Example: 12
     * @response 200 {"data":[{"run_id":"uuid","run_title":"Example"}],"current_page":1}
     */
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

            $runs = $query->orderByDesc('run_added');
            if ($request->has('page')) {
                $runs = $runs->paginate($perPage);
            } else {
                $runs = $runs->get();
            }

            return response()->json($runs, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch runs', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Find a run by its pin code
     *
    * @unauthenticated
    * @urlParam pin string required The 4-8 character run pin. Example: "ABC123"
     * @response 200 {"run_id":"uuid","run_title":"Downtown Puzzle Run","run_pin":"ABC123"}
     */
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
    /**
     * Show single run with nested relationships
     *
    * @unauthenticated
    * @urlParam id string required Run UUID.
    * @response 200 {"run_id":"uuid","run_title":"Example","flags":[],"questions":[]}
     */
    public function show($id)
    {
        try {
            $run = Run::with([
                'runType',
                'flags' => function ($query) {
                    $query->orderBy('flag_number', 'asc');
                },
                'flags.questions.options'
            ])->findOrFail($id);

            return response()->json($run, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Run not found', 'details' => $e->getMessage()], 404);
        }
    }

    // Create new run
    /**
     * Create a new run
     *
     * @bodyParam run_type integer required The id of the run type. Example: 1
     * @bodyParam run_title string required The title of the run. Example: "Downtown Puzzle Run"
     * @bodyParam run_description string nullable Optional description. Example: "A short urban puzzle"
     * @bodyParam run_pin string nullable Optional 6-char pin to assign to the run. Example: "AB12CD"
     * @response 201 {
     *  "message": "Run created",
     *  "run": {"run_id":"uuid","run_pin":"ABC123","run_title":"Downtown Puzzle Run"}
     * }
     */
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
    /**
     * Update a run (owner only)
     *
     * @urlParam id string required Run UUID.
     * @bodyParam run_title string nullable The new title. Example: "New Title"
     * @bodyParam run_description string nullable Optional description.
     * @response 200 {"message":"Run updated","run":{"run_id":"uuid","run_title":"New Title"}}
     */
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
    /**
     * Delete a run (owner only)
     *
     * @urlParam id string required Run UUID.
     * @response 200 {"message":"Run deleted"}
     */
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

    /**
     *
     * @response 200 {
     *  "message": "Pin generated",
     *  "pin": "ABC123",
     *  "run": {"run_id":"uuid","run_pin":"ABC123"}
     * }
     */
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
    
    /**
     * get all runs (owner only)
     *
     * @response 200 {"message":"Runs retrieved","run":{"run_id":"uuid","run_title":"New Title"}}
     */
    public function myRuns()
    {
        // Resolve the current user: prefer the session/web guard, but fall back to a JWT stored in session
        $user = auth('api')->user();
        if (!$user) { return response()->json(['error' => 'Unauthorized. Please log in.'], 401);}

        $runs = collect();
        if ($user) {
            $runs = Run::with('runType')->where('user_id', $user->user_id)->orderBy('run_added', 'desc')->get();
        }

        return response()->json(['message' => 'Runs retrieved', 'runs' => $runs, ]);
    }
}
