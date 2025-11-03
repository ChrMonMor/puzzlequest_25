<?php

namespace App\Http\Controllers;

use App\Models\Flag;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class FlagController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }

    public function index(Request $request)
    {
        try {
            $query = Flag::with('run','questions');
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
                'flag_number'=>'required|integer',
                'flag_lat'=>'required|numeric',
                'flag_long'=>'required|numeric',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);

            $run = Run::findOrFail($request->run_id);
            if ($request->user()->user_id !== $run->user_id) return response()->json(['error'=>'Unauthorized'], 403);

            $flag = Flag::create($request->only(['run_id','flag_number','flag_lat','flag_long']));
            $flag->load('run','questions');

            return response()->json(['message'=>'Flag created','flag'=>$flag], 201);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to create flag','details'=>$e->getMessage()],500);
        }
    }

    public function update(Request $request, $flag_id)
    {
        try {
            $flag = Flag::with('run')->findOrFail($flag_id);
            if ($request->user()->user_id !== $flag->run->user_id) return response()->json(['error'=>'Unauthorized'], 403);

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
            if ($request->user()->user_id !== $flag->run->user_id) return response()->json(['error'=>'Unauthorized'], 403);
            $flag->delete();
            return response()->json(['message'=>'Flag deleted'],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to delete flag','details'=>$e->getMessage()],500);
        }
    }

    // Bulk create
    public function bulkStore(Request $request)
    {
        try {
            $flagsData = $request->input('flags');
            if (!is_array($flagsData)) return response()->json(['error'=>'flags must be an array'],422);

            $created = [];
            foreach($flagsData as $data){
                $validator = Validator::make($data, [
                    'run_id'=>'required|exists:runs,run_id',
                    'flag_number'=>'required|integer',
                    'flag_lat'=>'required|numeric',
                    'flag_long'=>'required|numeric',
                ]);
                if ($validator->fails()) continue;

                $run = Run::findOrFail($data['run_id']);
                if ($request->user()->user_id !== $run->user_id) continue;

                $flag = Flag::create($data);
                $flag->load('run','questions');
                $created[] = $flag;
            }

            return response()->json(['message'=>'Bulk flags created','flags'=>$created],201);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed bulk create','details'=>$e->getMessage()],500);
        }
    }

    // Bulk delete
    public function bulkDelete(Request $request)
    {
        try {
            $flagIds = $request->input('flag_ids');
            if (!is_array($flagIds)) return response()->json(['error'=>'flag_ids must be an array'],422);

            $deleted = 0;
            foreach($flagIds as $id){
                $flag = Flag::with('run')->find($id);
                if (!$flag) continue;
                if ($request->user()->user_id !== $flag->run->user_id) continue;
                $flag->delete();
                $deleted++;
            }

            return response()->json(['message'=>"$deleted flags deleted"],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed bulk delete','details'=>$e->getMessage()],500);
        }
    }
}
