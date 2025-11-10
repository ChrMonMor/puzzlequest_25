<?php

namespace App\Http\Controllers;

use App\Models\QuestionOption;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Exception;

/**
 * @group Question Options
 * @authenticated
 *
 * Manage individual question options and bulk option operations.
 */
class QuestionOptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth.api')->except(['index','show']);
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    
    public function index()
    {
    /**
     * List question options
    * @unauthenticated
     *
     * @response 200 [{"question_option_id":"uuid","question_option_text":"..."}]
     */
        try {
            $options = QuestionOption::with('question')->get();
            return response()->json($options, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to fetch options', 'details' => $e->getMessage()], 500);
        }
    }

    
    /**
     * Show a single question option
     *
    * @unauthenticated
     * @urlParam id string required Option UUID.
     * @response 200 {"question_option_id":"uuid","question_option_text":"..."}
     */
    public function show($id)
    {
        try {
            $option = QuestionOption::with('question')->findOrFail($id);
            return response()->json($option, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Option not found', 'details' => $e->getMessage()], 404);
        }
    }

    /**
     * Create a question option
     *
     * @bodyParam question_id string required The question UUID this option belongs to.
     * @bodyParam question_option_text string required The option text.
     * @response 201 {"message":"Option created","option":{"question_option_id":"uuid","question_option_text":"Example"}}
     */
    public function store(Request $request)
    {
        try {
            $question = Question::findOrFail($request->input('question_id'));
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'question_id' => 'required|exists:questions,question_id',
                'question_option_text' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $option = QuestionOption::create($request->only(['question_id', 'question_option_text']));
            $option->load('question');

            return response()->json(['message' => 'Option created', 'option' => $option], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create option', 'details' => $e->getMessage()], 500);
        }
    }

    
    /**
     * Update a question option
     *
     * @urlParam id string required Option UUID.
     * @bodyParam question_option_text string required New option text.
     * @response 200 {"message":"Option updated","option":{"question_option_id":"uuid"}}
     */
    public function update(Request $request, $id)
    {
        try {
            $option = QuestionOption::findOrFail($id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($option->question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'question_option_text' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $option->update($request->only('question_option_text'));
            $option->load('question');

            return response()->json(['message' => 'Option updated', 'option' => $option], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update option', 'details' => $e->getMessage()], 500);
        }
    }

    
    /**
     * Delete a question option
     *
     * @urlParam id string required Option UUID.
     * @response 200 {"message":"Option deleted"}
     */
    public function destroy($id)
    {
        try {
            $option = QuestionOption::findOrFail($id);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($option->question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $option->delete();
            return response()->json(['message' => 'Option deleted'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete option', 'details' => $e->getMessage()], 500);
        }
    }

    // ---------------- Bulk Operations ----------------

    /**
     * Bulk create options for a question
     *
     * @bodyParam options array required Array of option objects with `question_option_text`.
     * @bodyParam options.*.question_option_text string required Option text.
     * @response 201 {"message":"Options created","options":[{"question_option_id":"uuid","question_option_text":"..."}]}
     */
    public function bulkCreate(Request $request, $questionId)
    {
        try {
            $question = Question::findOrFail($questionId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $optionsData = $request->input('options', []);
            $createdOptions = [];

            foreach ($optionsData as $optData) {
                $validator = Validator::make($optData, [
                    'question_option_text' => 'required|string',
                ]);
                if ($validator->fails()) continue;

                $createdOptions[] = QuestionOption::create(array_merge($optData, ['question_id' => $questionId]));
            }

            return response()->json(['message' => 'Options created', 'options' => $createdOptions], 201);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk create options', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk update options for a question
     *
     * @bodyParam options array required Array of option objects including `question_option_id`.
     * @response 200 {"message":"Options updated"}
     */
    public function bulkUpdate(Request $request, $questionId)
    {
        try {
            $question = Question::findOrFail($questionId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $optionsData = $request->input('options', []);
            $updatedOptions = [];

            foreach ($optionsData as $optData) {
                $option = QuestionOption::find($optData['option_id'] ?? null);
                if (!$option || $option->question_id !== $questionId) continue;

                $option->update($optData);
                $updatedOptions[] = $option;
            }

            return response()->json(['message' => 'Options updated', 'options' => $updatedOptions], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk update options', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Bulk delete options for a question
     *
     * @bodyParam option_ids array required Array of option UUIDs to delete.
     * @response 200 {"message":"Options deleted"}
     */
    public function bulkDelete(Request $request, $questionId)
    {
        try {
            $question = Question::findOrFail($questionId);
            $user = auth('api')->user();
            if (!$user) return response()->json(['error' => 'Unauthorized. Please log in.'], 401);
            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $optionIds = $request->input('option_ids', []);
            $deletedCount = QuestionOption::where('question_id', $questionId)
                ->whereIn('question_option_id', $optionIds)
                ->delete();

            return response()->json(['message' => "Deleted $deletedCount options"], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to bulk delete options', 'details' => $e->getMessage()], 500);
        }
    }
}
