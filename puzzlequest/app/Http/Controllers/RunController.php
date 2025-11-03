<?php

namespace App\Http\Controllers;



use App\Models\Run;
use App\Models\Flag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Exception;


class RunController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
        $this->middleware('auth:api')->except(['index', 'show']);
    }

    /**
     * List all runs (public)
     */
    public function index()
    {
        $runs = Run::with(['flags', 'questions', 'histories', 'run_types'])->get();
        return response()->json($runs, 200);
    }

    /**
     * Show a single run (public)
     */
    public function show(Request $request)
    {
        try {
            $run = Run::with(['flags', 'questions', 'histories', 'run_types'])->findOrFail($request->run_id);
            return response()->json($run, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Run not found'], 404);
        }
    }

    /**
     * Create a new run (authenticated)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'run_title' => 'required|string|max:255',
                'run_description' => 'nullable|string|max:1000',
                'run_type' => 'required|exists:run_types,run_type_id',
                'run_location' => 'nullable|string|max:255',
                'run_img_icon' => 'nullable|string',
                'run_img_front' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $run = Run::create(array_merge($request->only([
                'run_title', 'run_description', 'run_type', 'run_location', 'run_img_icon', 'run_img_front'
            ]), ['user_id' => $user->user_id, 'run_added' => now()]));

            $run->load(['flags', 'questions', 'histories', 'run_types']);

            return response()->json([
                'message' => 'Run created successfully',
                'run' => $run
            ], 201);

        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create run', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a run (authenticated, owner only)
     */
    public function update(Request $request)
    {
        try {
            $user = Auth::user();
            $run = Run::findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'run_title' => 'sometimes|string|max:255',
                'run_description' => 'sometimes|string|max:1000',
                'run_type' => 'sometimes|exists:run_types,run_type_id',
                'run_location' => 'sometimes|string|max:255',
                'run_img_icon' => 'sometimes|string',
                'run_img_front' => 'sometimes|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $run->update($request->only([
                'run_title', 'run_description', 'run_type', 'run_location', 'run_img_icon', 'run_img_front'
            ]));

            $run->load(['flags', 'questions', 'histories', 'run_types']);

            return response()->json([
                'message' => 'Run updated successfully',
                'run' => $run
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Run not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update run', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a run (authenticated, owner only)
     */
    public function destroy(Request $request)
    {
        try {
            $user = Auth::user();
            $run = Run::findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $run->delete();

            return response()->json(['message' => 'Run deleted successfully'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Run not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete run', 'details' => $e->getMessage()], 500);
        }
    }

    // =====================================================
    // BULK FLAG OPERATIONS
    // =====================================================

    /**
     * Bulk create flags for a run
     */
    public function addFlagsBulk(Request $request)
    {
        try {
            $user = Auth::user();
            $run = Run::with('flags', 'run_types')->findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'flags' => 'required|array|min:1',
                'flags.*.flag_number' => 'required|integer|min:1',
                'flags.*.flag_long' => 'required|numeric',
                'flags.*.flag_lat' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $flagsData = array_map(fn($f) => [
                'run_id' => $request->run_id,
                'flag_number' => $f['flag_number'],
                'flag_long' => $f['flag_long'],
                'flag_lat' => $f['flag_lat']
            ], $request->flags);

            DB::beginTransaction();
            Flag::insert($flagsData);
            DB::commit();

            $run->load(['flags', 'questions', 'histories', 'run_types']);

            return response()->json([
                'message' => count($flagsData) . ' flags created successfully!',
                'run' => $run
            ], 201);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Run not found'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create flags', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk update flags for a run
     */
    public function updateFlagsBulk(Request $request)
    {
        try {
            $user = Auth::user();
            $run = Run::with('flags', 'run_types')->findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'flags' => 'required|array|min:1',
                'flags.*.flag_id' => 'required|exists:flags,flag_id',
                'flags.*.flag_number' => 'sometimes|integer|min:1',
                'flags.*.flag_long' => 'sometimes|numeric',
                'flags.*.flag_lat' => 'sometimes|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();
            foreach ($request->flags as $flagData) {
                $flag = Flag::where('run_id', $request->run_id)->findOrFail($flagData['flag_id']);
                $flag->update(array_filter($flagData, fn($k) => in_array($k, ['flag_number', 'flag_long', 'flag_lat']), ARRAY_FILTER_USE_KEY));
            }
            DB::commit();

            $run->load(['flags', 'questions', 'histories', 'run_types']);

            return response()->json([
                'message' => 'Flags updated successfully!',
                'run' => $run
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Run or flag not found'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update flags', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk delete flags for a run
     */
    public function deleteFlagsBulk(Request $request)
    {
        try {
            $user = Auth::user();
            $run = Run::with('flags', 'run_types')->findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'flag_ids' => 'required|array|min:1',
                'flag_ids.*' => 'required|exists:flags,flag_id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            DB::beginTransaction();
            $deletedCount = Flag::where('run_id', $request->run_id)
                ->whereIn('flag_id', $request->flag_ids)
                ->delete();
            DB::commit();

            $run->load(['flags', 'questions', 'histories', 'run_types']);

            return response()->json([
                'message' => $deletedCount . ' flags deleted successfully!',
                'run' => $run
            ], 200);

        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Run not found'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to delete flags', 'details' => $e->getMessage()], 500);
        }
    }
}
