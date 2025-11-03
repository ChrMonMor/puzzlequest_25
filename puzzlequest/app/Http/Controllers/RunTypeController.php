<?php

namespace App\Http\Controllers;

use App\Models\RunType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class RunTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    public function index()
    {
        try {
            $runTypes = RunType::with('runs')->get();
            return response()->json($runTypes,200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to fetch run types','details'=>$e->getMessage()],500);
        }
    }

    public function show($id)
    {
        try {
            $runType = RunType::with('runs')->findOrFail($id);
            return response()->json($runType,200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Run type not found','details'=>$e->getMessage()],404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(),[
                'run_type_name'=>'required|string|unique:run_types,run_type_name',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);

            $runType = RunType::create($request->only(['run_type_name']));
            $runType->load('runs');
            return response()->json(['message'=>'Run type created','run_type'=>$runType],201);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to create run type','details'=>$e->getMessage()],500);
        }
    }

    public function update(Request $request,$id)
    {
        try {
            $runType = RunType::findOrFail($id);
            $validator = Validator::make($request->all(),[
                'run_type_name'=>'required|string|unique:run_types,run_type_name,'.$id.',run_type_id',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);

            $runType->update($request->only(['run_type_name']));
            $runType->load('runs');
            return response()->json(['message'=>'Run type updated','run_type'=>$runType],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to update run type','details'=>$e->getMessage()],500);
        }
    }

    public function destroy($id)
    {
        try {
            $runType = RunType::findOrFail($id);
            $runType->delete();
            return response()->json(['message'=>'Run type deleted'],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to delete run type','details'=>$e->getMessage()],500);
        }
    }
}
