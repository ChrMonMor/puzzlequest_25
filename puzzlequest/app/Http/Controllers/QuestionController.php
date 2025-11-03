<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class QuestionController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    /**
     * List all questions (public)
     */
    public function index()
    {
        $questions = Question::with(['run', 'flag', 'questionType', 'options'])->get();
        return response()->json($questions, 200);
    }

    /**
     * Show a single question (public)
     */
    public function show($question_id)
    {
        try {
            $question = Question::with(['run', 'flag', 'questionType', 'options'])->findOrFail($question_id);
            return response()->json($question, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Question not found'], 404);
        }
    }

    /**
     * Create a new question (authenticated, run owner)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validator = Validator::make($request->all(), [
                'run_id' => 'required|exists:runs,run_id',
                'flag_id' => 'nullable|exists:flags,flag_id',
                'question_type' => 'required|exists:question_types,question_type_id',
                'question_text' => 'required|string',
                'question_answer' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $run = Run::findOrFail($request->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $question = Question::create($request->only([
                'run_id', 'flag_id', 'question_type', 'question_text', 'question_answer'
            ]));

            $question->load(['run', 'flag', 'questionType', 'options']);

            return response()->json([
                'message' => 'Question created successfully',
                'question' => $question
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Run not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create question', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a question (authenticated, run owner)
     */
    public function update(Request $request, $question_id)
    {
        try {
            $user = Auth::user();
            $question = Question::findOrFail($question_id);

            $run = Run::findOrFail($question->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'flag_id' => 'nullable|exists:flags,flag_id',
                'question_type' => 'sometimes|exists:question_types,question_type_id',
                'question_text' => 'sometimes|string',
                'question_answer' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $question->update($request->only([
                'flag_id', 'question_type', 'question_text', 'question_answer'
            ]));

            $question->load(['run', 'flag', 'questionType', 'options']);

            return response()->json([
                'message' => 'Question updated successfully',
                'question' => $question
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Question not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update question', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a question (authenticated, run owner)
     */
    public function destroy($question_id)
    {
        try {
            $user = Auth::user();
            $question = Question::findOrFail($question_id);

            $run = Run::findOrFail($question->run_id);

            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $question->delete();

            return response()->json(['message' => 'Question deleted successfully'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Question not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete question', 'details' => $e->getMessage()], 500);
        }
    }
}
