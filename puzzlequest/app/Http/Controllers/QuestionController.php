<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    // List all questions (with options, type, run, flag)
    public function index()
    {
        try {
            $questions = Question::with(['options', 'questionType', 'flag', 'run'])->get();
            return response()->json($questions, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch questions', 'details' => $e->getMessage()], 500);
        }
    }

    // Show single question
    public function show($id)
    {
        try {
            $question = Question::with(['options', 'questionType', 'flag', 'run'])->findOrFail($id);
            return response()->json($question, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Question not found', 'details' => $e->getMessage()], 404);
        }
    }

    // Create a question (linked to a run)
    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            $userId = $user->user_id;
            $runId = $request->input('run_id');

            $run = Run::findOrFail($runId);
            if ($run->user_id !== $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'flag_id' => 'nullable|exists:flags,flag_id',
                'question_type' => 'required|exists:question_types,question_type_id',
                'question_text' => 'required|string',
                'question_answer' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $question = Question::create($request->only(['run_id', 'flag_id', 'question_type', 'question_text', 'question_answer']));
            $question->load(['options', 'questionType', 'flag', 'run']);

            return response()->json(['message' => 'Question created', 'question' => $question], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create question', 'details' => $e->getMessage()], 500);
        }
    }

    // Update question (only owner of run)
    public function update(Request $request, $id)
    {
        try {
            $question = Question::findOrFail($id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($question->run->user_id !== $user->user_id) {
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

            $question->update($request->only(['flag_id', 'question_type', 'question_text', 'question_answer']));
            $question->load(['options', 'questionType', 'flag', 'run']);

            return response()->json(['message' => 'Question updated', 'question' => $question], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update question', 'details' => $e->getMessage()], 500);
        }
    }

    // Delete question (only owner)
    public function destroy($id)
    {
        try {
            $question = Question::findOrFail($id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $question->delete();
            return response()->json(['message' => 'Question deleted'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete question', 'details' => $e->getMessage()], 500);
        }
    }

    // ---------------- Bulk Operations ----------------

    public function bulkCreate(Request $request, $runId)
    {
        try {
            $run = Run::findOrFail($runId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $questionsData = $request->input('questions', []);
            $createdQuestions = [];

            foreach ($questionsData as $qData) {
                $validator = Validator::make($qData, [
                    'flag_id' => 'nullable|exists:flags,flag_id',
                    'question_type' => 'required|exists:question_types,question_type_id',
                    'question_text' => 'required|string',
                    'question_answer' => 'nullable|string',
                ]);
                if ($validator->fails()) continue;

                $createdQuestions[] = Question::create(array_merge($qData, ['run_id' => $runId]));
            }

            return response()->json(['message' => 'Questions created', 'questions' => $createdQuestions], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk create questions', 'details' => $e->getMessage()], 500);
        }
    }

    public function bulkUpdate(Request $request, $runId)
    {
        try {
            $run = Run::findOrFail($runId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $questionsData = $request->input('questions', []);
            $updatedQuestions = [];

            foreach ($questionsData as $qData) {
                $question = Question::find($qData['question_id'] ?? null);
                if (!$question || $question->run_id !== $runId) continue;

                $question->update($qData);
                $updatedQuestions[] = $question;
            }

            return response()->json(['message' => 'Questions updated', 'questions' => $updatedQuestions], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk update questions', 'details' => $e->getMessage()], 500);
        }
    }

    public function bulkDelete(Request $request, $runId)
    {
        try {
            $run = Run::findOrFail($runId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $questionIds = $request->input('question_ids', []);
            $deletedCount = Question::where('run_id', $runId)->whereIn('question_id', $questionIds)->delete();

            return response()->json(['message' => "Deleted $deletedCount questions"], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk delete questions', 'details' => $e->getMessage()], 500);
        }
    }
}
