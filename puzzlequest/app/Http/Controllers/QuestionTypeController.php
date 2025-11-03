<?php

namespace App\Http\Controllers;

use App\Models\QuestionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Exception;

class QuestionTypeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    public function index()
    {
        try {
            $questionTypes = QuestionType::with('questions')->get();
            return response()->json($questionTypes,200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to fetch question types','details'=>$e->getMessage()],500);
        }
    }

    public function show($id)
    {
        try {
            $questionType = QuestionType::with('questions')->findOrFail($id);
            return response()->json($questionType,200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Question type not found','details'=>$e->getMessage()],404);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(),[
                'question_type_name'=>'required|string|unique:question_types,question_type_name',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);

            $questionType = QuestionType::create($request->only(['question_type_name']));
            $questionType->load('questions');
            return response()->json(['message'=>'Question type created','question_type'=>$questionType],201);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to create question type','details'=>$e->getMessage()],500);
        }
    }

    public function update(Request $request,$id)
    {
        try {
            $questionType = QuestionType::findOrFail($id);
            $validator = Validator::make($request->all(),[
                'question_type_name'=>'required|string|unique:question_types,question_type_name,'.$id.',question_type_id',
            ]);
            if ($validator->fails()) return response()->json(['errors'=>$validator->errors()],422);

            $questionType->update($request->only(['question_type_name']));
            $questionType->load('questions');
            return response()->json(['message'=>'Question type updated','question_type'=>$questionType],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to update question type','details'=>$e->getMessage()],500);
        }
    }

    public function destroy($id)
    {
        try {
            $questionType = QuestionType::findOrFail($id);
            $questionType->delete();
            return response()->json(['message'=>'Question type deleted'],200);
        } catch (Exception $e) {
            return response()->json(['error'=>'Failed to delete question type','details'=>$e->getMessage()],500);
        }
    }
}
