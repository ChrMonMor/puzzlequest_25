<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Run;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * @group Questions
 * @authenticated
 *
 * Manage questions and their options for runs/flags. Supports bulk operations and answer selection.
 */
class QuestionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'storeWithAnswer', 'update', 'destroy']);
    }
    
    /**
     * List all questions
     * @unauthenticated
     *
     * @response 200 [{"question_id":"uuid","question_text":"..."}]
     */
    public function index()
    {
        try {
            $questions = Question::with(['options', 'questionType', 'flag', 'run'])->get();
            return response()->json($questions, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch questions', 'details' => $e->getMessage()], 500);
        }
    }

    
    /**
     * Show a single question
     *
    * @unauthenticated
     * @urlParam id string required Question UUID.
     * @response 200 {"question_id":"uuid","question_text":"...","options":[]}
     */
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
    /**
     *
     * @bodyParam run_id string required Run UUID. Example: "..."
     * @bodyParam flag_id string nullable Flag UUID if tied to a specific flag.
     * @bodyParam question_type integer required Question type id. Example: 1
     * @bodyParam question_text string required The question text.
     * @bodyParam options array nullable Array of options: objects with `question_option_text` and optional `is_answer`.
     * @response 201 {"message":"Question created successfully","question":{"question_id":"uuid","question_text":"What is...","options":[{"question_option_id":"uuid"}]}}
     */
    public function store(Request $request)
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            }

            $userId = $user->user_id;
            $runId = $request->input('run_id');

            $run = Run::findOrFail($runId);
            if ($run->user_id !== $userId) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'run_id' => 'required|exists:runs,run_id',
                'flag_id' => 'nullable|exists:flags,flag_id',
                'question_type' => 'required|exists:question_types,question_type_id',
                'question_text' => 'required|string',
                'options' => 'nullable|array|min:1',
                'options.*.question_option_text' => 'required_with:options|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // Transaction for safety
            $question = DB::transaction(function () use ($request) {
                $options = $request->input('options', []);

                // Step 1: Create the question
                $q = Question::create([
                    'run_id' => $request->input('run_id'),
                    'flag_id' => $request->input('flag_id'),
                    'question_type' => $request->input('question_type'),
                    'question_text' => $request->input('question_text'),
                ]);

                // Step 2: Create options
                $firstOptionId = null;
                if (!empty($options)) {
                    foreach ($options as $index => $opt) {
                        $option = QuestionOption::create([
                            'question_id' => $q->question_id,
                            'question_option_text' => $opt['question_option_text'],
                        ]);

                        // Capture first option’s ID
                        if ($index === 0) {
                            $firstOptionId = $option->question_option_id;
                        }
                    }
                }

                // Step 3: Update question with the first option ID as the answer
                if ($firstOptionId) {
                    $q->update(['question_answer' => $firstOptionId]);
                }

                return $q;
            });

            $question->load(['options', 'questionType', 'flag', 'run']);

            return response()->json([
                'message' => 'Question created successfully',
                'question' => $question,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'Failed to create question',
                'details' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Update a question
     *
     * @urlParam id string required Question UUID.
     * @bodyParam question_text string nullable New question text.
     * @bodyParam question_answer string nullable New answer reference.
     * @response 200 {"message":"Question updated","question":{"question_id":"uuid"}}
     */
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

    
    /**
     * Delete a question
     *
     * @urlParam id string required Question UUID.
     * @response 200 {"message":"Question deleted"}
     */
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

    /**
     * Bulk create questions for a run
     *
     * @bodyParam questions array required Array of question objects.
     * @bodyParam questions.*.flag_id string nullable Flag UUID if tied to a flag.
     * @bodyParam questions.*.question_type integer required Question type id.
     * @bodyParam questions.*.question_text string required The question text.
     * @bodyParam questions.*.options array nullable Array of option objects with `question_option_text` and optional `is_answer`.
     * @response 201 {"message":"Questions created","questions":[{"question_id":"uuid","question_text":"..."}]}
     */
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

            // Normalize input: accept a top-level array or a 'questions' key
            if (!is_array($questionsData)) return response()->json(['error' => 'questions must be an array'], 422);
            if (array_keys($questionsData) !== range(0, count($questionsData) - 1)) {
                $questionsData = [$questionsData];
            }

            // Create questions and options inside a single transaction for atomicity
            $createdQuestions = DB::transaction(function () use ($questionsData, $runId) {
                $out = [];

                foreach ($questionsData as $qData) {
                    $validator = Validator::make($qData, [
                        'flag_id' => 'nullable|exists:flags,flag_id',
                        'question_type' => 'required|exists:question_types,question_type_id',
                        'question_text' => 'required|string',
                        'options' => 'nullable|array',
                        'options.*.question_option_text' => 'required_with:options|string',
                        'options.*.is_answer' => 'sometimes|boolean',
                        'answer_index' => 'sometimes|integer|min:0',
                    ]);
                    if ($validator->fails()) continue;

                    // Create the question
                    $q = Question::create(array_merge($qData, ['run_id' => $runId]));

                    // Create options if provided and capture the chosen answer
                    $options = $qData['options'] ?? [];
                    $createdOptionIds = [];
                    $chosenOptionId = null;
                    if (!empty($options) && is_array($options)) {
                        foreach ($options as $idx => $opt) {
                            $created = \App\Models\QuestionOption::create([
                                'question_id' => $q->question_id,
                                'question_option_text' => $opt['question_option_text'] ?? null,
                            ]);
                            $createdOptionIds[] = $created->question_option_id;

                            // If option explicitly marked as answer, prefer that
                            if (!is_null($opt['is_answer'] ?? null) && $opt['is_answer']) {
                                $chosenOptionId = $created->question_option_id;
                            }
                        }

                        // If answer_index was provided, use it (overrides implicit first option)
                        if (isset($qData['answer_index']) && is_int($qData['answer_index']) && isset($createdOptionIds[$qData['answer_index']])) {
                            $chosenOptionId = $createdOptionIds[$qData['answer_index']];
                        }

                        // Fallback: use first option as answer if none specified
                        if (!$chosenOptionId && count($createdOptionIds)) {
                            $chosenOptionId = $createdOptionIds[0];
                        }
                    }

                    if ($chosenOptionId) {
                        $q->question_answer = $chosenOptionId;
                        $q->save();
                    }

                    $q->load(['options', 'questionType', 'flag', 'run']);
                    $out[] = $q;
                }

                return $out;
            });

            return response()->json(['message' => 'Questions created', 'questions' => $createdQuestions], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk create questions', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Create a question with options and an explicit answer
     *
     * @bodyParam run_id string required Run UUID.
     * @bodyParam options array required Array of option objects with `question_option_text`.
     * @response 201 {"message":"Question with options created","question":{"question_id":"uuid"}}
     */
    public function storeWithAnswer(Request $request)
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
                'options' => 'required|array|min:1',
                'options.*.question_option_text' => 'required|string',
                'options.*.is_answer' => 'sometimes|boolean',
                'answer_index' => 'sometimes|integer|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $question = DB::transaction(function () use ($request) {
                $q = Question::create($request->only(['run_id', 'flag_id', 'question_type', 'question_text']));

                $options = $request->input('options', []);
                $answerIndex = null;
                foreach ($options as $idx => $opt) {
                    $created = QuestionOption::create([
                        'question_id' => $q->question_id,
                        'question_option_text' => $opt['question_option_text'] ?? null,
                    ]);

                    if (!is_null($opt['is_answer'] ?? null) && $opt['is_answer']) {
                        $answerIndex = $idx;
                    }
                }

                // If answer_index explicitly provided, prefer it
                if ($request->filled('answer_index')) {
                    $answerIndex = (int) $request->input('answer_index');
                }

                // If we found an answer index, store it (as integer). If not, leave null/0 per schema.
                if (!is_null($answerIndex)) {
                    $q->question_answer = $answerIndex;
                    $q->save();
                }

                return $q;
            });

            $question->load(['options', 'questionType', 'flag', 'run']);
            return response()->json(['message' => 'Question with options created', 'question' => $question], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create question with options', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk update questions for a run
     *
     * @bodyParam questions array required Array of question objects with `question_id` and fields to update.
     * @response 200 {"message":"Questions updated"}
     */
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

    /**
     * Bulk delete questions for a run
     *
     * @bodyParam question_ids array required Array of question UUIDs.
     * @response 200 {"message":"Deleted X questions"}
     */
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
