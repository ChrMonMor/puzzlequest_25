<?php

namespace App\Http\Controllers;

use App\Models\Flag;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Exception;

class FlagController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
        $this->middleware('auth:api')->except(['index', 'show']);
    }

    /**
     * List all flags (public)
     */
    public function index()
    {
        $flags = Flag::with(['run', 'run.runType'])->get();
        return response()->json($flags, 200);
    }

    /**
     * Show a single flag (public)
     */
    public function show($flag_id)
    {
        try {
            $flag = Flag::with(['run', 'run.runType'])->findOrFail($flag_id);
            return response()->json($flag, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Flag not found'], 404);
        }
    }

    /**
     * Create a single flag (authenticated, owner of run)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'run_id' => 'required|exists:runs,run_id',
                'flag_number' => 'required|integer|min:1',
                'flag_long' => 'required|numeric',
                'flag_lat' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $run = Run::findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $flag = Flag::create($request->only(['run_id', 'flag_number', 'flag_long', 'flag_lat']));
            $flag->load(['run', 'run.runType']);

            return response()->json([
                'message' => 'Flag created successfully',
                'flag' => $flag
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Run not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create flag', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a single flag (authenticated, owner of run)
     */
    public function update(Request $request, $flag_id)
    {
        try {
            $user = Auth::user();
            $flag = Flag::findOrFail($flag_id);

            $run = Run::findOrFail($flag->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'flag_number' => 'sometimes|integer|min:1',
                'flag_long' => 'sometimes|numeric',
                'flag_lat' => 'sometimes|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $flag->update($request->only(['flag_number', 'flag_long', 'flag_lat']));
            $flag->load(['run', 'run.runType']);

            return response()->json([
                'message' => 'Flag updated successfully',
                'flag' => $flag
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Flag not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update flag', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a single flag (authenticated, owner of run)
     */
    public function destroy($flag_id)
    {
        try {
            $user = Auth::user();
            $flag = Flag::findOrFail($flag_id);
            $run = Run::findOrFail($flag->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $flag->delete();

            return response()->json(['message' => 'Flag deleted successfully'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Flag not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete flag', 'details' => $e->getMessage()], 500);
        }
    }
}
