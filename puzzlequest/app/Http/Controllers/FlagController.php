<?php

namespace App\Http\Controllers;

use App\Models\Flag;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Exception;

class FlagController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        try {
            // Eager-load related run, questions and their options so consumers (edit UI) get full data
            $query = Flag::with(['run','questions.options']);
            if ($request->has('run_id')) $query->where('run_id',$request->run_id);
            $flags = $query->get();
            return response()->json($flags, 200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to fetch flags','details'=>$e->getMessage()], 500);
        }
    }

    public function show($flag_id)
    {
        try {
            $flag = Flag::with('run','questions')->findOrFail($flag_id);
            return response()->json($flag, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error'=>'Flag not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to fetch flag','details'=>$e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'run_id'=>'required|exists:runs,run_id',
                'flag_lat'=>'required|numeric',
                'flag_long'=>'required|numeric',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);
            $run = Run::findOrFail($request->run_id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error'=>'Unauthorized. Please log in.'], 401);
            if ($user->user_id !== $run->user_id) return response()->json(['error'=>'Unauthorized'], 403);

            // Assign flag_number server-side atomically to avoid races
            $flag = DB::transaction(function () use ($request, $run) {
                // To support Postgres (which disallows FOR UPDATE with aggregates),
                // lock the last row ordered by flag_number and compute the next value.
                $last = Flag::where('run_id', $run->run_id)->orderBy('flag_number', 'desc')->lockForUpdate()->first();
                $next = $last ? ($last->flag_number + 1) : 1;

                $data = [
                    'run_id' => $run->run_id,
                    'flag_number' => $next,
                    'flag_lat' => $request->input('flag_lat'),
                    'flag_long' => $request->input('flag_long'),
                ];

                $f = Flag::create($data);
                $f->load('run','questions.options');
                return $f;
            });

            return response()->json(['message'=>'Flag created','flag'=>$flag], 201);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to create flag','details'=>$e->getMessage()],500);
        }
    }

    public function update(Request $request, $flag_id)
    {
        try {
            $flag = Flag::with('run')->findOrFail($flag_id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error'=>'Unauthorized. Please log in.'], 401);
            if ($user->user_id !== $flag->run->user_id) return response()->json(['error'=>'Unauthorized'], 403);

            $validator = Validator::make($request->all(), [
                'flag_number'=>'nullable|integer',
                'flag_lat'=>'nullable|numeric',
                'flag_long'=>'nullable|numeric',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);

            $flag->update($request->only(['flag_number','flag_lat','flag_long']));
            $flag->load('run','questions');

            return response()->json(['message'=>'Flag updated','flag'=>$flag], 200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to update flag','details'=>$e->getMessage()],500);
        }
    }

    public function destroy(Request $request, $flag_id)
    {
        try {
            $flag = Flag::with('run')->findOrFail($flag_id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error'=>'Unauthorized. Please log in.'], 401);
            if ($user->user_id !== $flag->run->user_id) return response()->json(['error'=>'Unauthorized'], 403);
            $flag->delete();
            return response()->json(['message'=>'Flag deleted'],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to delete flag','details'=>$e->getMessage()],500);
        }
    }

    // Bulk create for flags under a run (matches routes: POST /runs/{runId}/flags/bulk)
    public function bulkCreate(Request $request, $runId)
    {
        try {
            $run = Run::findOrFail($runId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error'=>'Unauthorized. Please log in.'], 401);
            if ($user->user_id !== $run->user_id) return response()->json(['error'=>'Unauthorized'], 403);
            // Accept either a top-level array payload or a 'flags' key
            $flagsData = $request->input('flags', $request->all());

            // Normalize single-object payload into an array
            if (!is_array($flagsData)) return response()->json(['error'=>'flags must be an array'],422);
            if (array_keys($flagsData) !== range(0, count($flagsData) - 1)) {
                // associative -> single object
                $flagsData = [$flagsData];
            }

            $created = [];

            // Create all flags in a transaction and assign sequential flag_numbers atomically
            $created = DB::transaction(function () use ($flagsData, $runId) {
                // Lock the last row for this run and compute the starting flag_number.
                // Avoid using aggregate with FOR UPDATE on Postgres by selecting the last
                // row ordered by flag_number and locking it.
                $last = Flag::where('run_id', $runId)->orderBy('flag_number', 'desc')->lockForUpdate()->first();
                $next = $last ? ($last->flag_number + 1) : 1;

                $out = [];
                foreach ($flagsData as $data) {
                    // Validate minimal required fields
                    $validator = Validator::make($data, [
                        'flag_lat' => 'required|numeric',
                        'flag_long' => 'required|numeric',
                    ]);
                    if ($validator->fails()) continue;

                    // enforce run id from the URL and assign server flag_number
                    $payload = [
                        'run_id' => $runId,
                        'flag_number' => $next++,
                        'flag_lat' => $data['flag_lat'],
                        'flag_long' => $data['flag_long'],
                    ];

                    $flag = Flag::create($payload);
                    $flag->load('run', 'questions.options');
                    $out[] = $flag;
                }

                return $out;
            });

            // Return the created flags as a top-level JSON array (tests expect an array)
            return response()->json($created, 201);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed bulk create','details'=>$e->getMessage()],500);
        }
    }

    // Bulk update for flags under a run (matches routes: PUT /runs/{runId}/flags/bulk)
    public function bulkUpdate(Request $request, $runId)
    {
        try {
            $run = Run::findOrFail($runId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error'=>'Unauthorized. Please log in.'], 401);
            if ($user->user_id !== $run->user_id) return response()->json(['error'=>'Unauthorized'], 403);
            // Accept either a top-level array payload or a 'flags' key
            $flagsData = $request->input('flags', $request->all());
            if (!is_array($flagsData)) return response()->json(['error'=>'flags must be an array'],422);
            if (array_keys($flagsData) !== range(0, count($flagsData) - 1)) {
                $flagsData = [$flagsData];
            }

            $updated = [];
            foreach ($flagsData as $data) {
                $flag = Flag::find($data['flag_id'] ?? null);
                if (!$flag || $flag->run_id != $runId) continue;

                // Only allow updatable fields
                $flag->update(array_intersect_key($data, array_flip(['flag_number','flag_lat','flag_long'])));
                $flag->load('run', 'questions.options');
                $updated[] = $flag;
            }

            return response()->json(['message' => 'Flags updated', 'flags' => $updated], 200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed bulk update','details'=>$e->getMessage()],500);
        }
    }

    // Bulk delete for flags under a run (matches routes: DELETE /runs/{runId}/flags/bulk)
    public function bulkDelete(Request $request, $runId)
    {
        try {
            $run = Run::findOrFail($runId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error'=>'Unauthorized. Please log in.'], 401);
            if ($user->user_id !== $run->user_id) return response()->json(['error'=>'Unauthorized'], 403);
            // Accept 'flag_ids' or 'ids' or a raw array body
            $flagIds = $request->input('flag_ids', $request->input('ids', $request->all()));
            if (!is_array($flagIds)) return response()->json(['error'=>'flag_ids must be an array'],422);

            $deleted = Flag::where('run_id', $runId)->whereIn('flag_id', $flagIds)->delete();

            // Return consistent message expected by tests
            return response()->json(['message' => 'Flags deleted'], 200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed bulk delete','details'=>$e->getMessage()],500);
        }
    }
}
