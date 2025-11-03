<?php

namespace App\Http\Controllers;

use App\Models\QuestionOption;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class QuestionOptionController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }

    // List all options for a question
    public function index($question_id)
    {
        $options = QuestionOption::where('question_id', $question_id)->get();
        return response()->json($options, 200);
    }

    // Show single option
    public function show($option_id)
    {
        try {
            $option = QuestionOption::findOrFail($option_id);
            return response()->json($option, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Option not found'], 404);
        }
    }

    // Create single option
    public function store(Request $request, $question_id)
    {
        try {
            $user = Auth::user();
            $question = Question::findOrFail($question_id);

            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'question_option_text' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $option = $question->options()->create([
                'question_option_text' => $request->question_option_text
            ]);

            return response()->json(['message' => 'Option created', 'option' => $option], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Question not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create option', 'details' => $e->getMessage()], 500);
        }
    }

    // Update single option
    public function update(Request $request, $option_id)
    {
        try {
            $user = Auth::user();
            $option = QuestionOption::findOrFail($option_id);

            if ($option->question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'question_option_text' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $option->update([
                'question_option_text' => $request->question_option_text
            ]);

            return response()->json(['message' => 'Option updated', 'option' => $option], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Option not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to update option', 'details' => $e->getMessage()], 500);
        }
    }

    // Delete single option
    public function destroy($option_id)
    {
        try {
            $user = Auth::user();
            $option = QuestionOption::findOrFail($option_id);

            if ($option->question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $option->delete();
            return response()->json(['message' => 'Option deleted'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Option not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete option', 'details' => $e->getMessage()], 500);
        }
    }

    // Bulk create options
    public function bulkCreate(Request $request, $question_id)
    {
        try {
            $user = Auth::user();
            $question = Question::findOrFail($question_id);

            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'options' => 'required|array|min:1',
                'options.*.question_option_text' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $createdOptions = [];
            foreach ($request->options as $opt) {
                $option = $question->options()->create([
                    'question_option_text' => $opt['question_option_text']
                ]);
                $createdOptions[] = $option;
            }

            return response()->json([
                'message' => 'Options created successfully',
                'options' => $createdOptions
            ], 201);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Question not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to create options', 'details' => $e->getMessage()], 500);
        }
    }

    // Bulk delete options
    public function bulkDelete(Request $request, $question_id)
    {
        try {
            $user = Auth::user();
            $question = Question::findOrFail($question_id);

            if ($question->run->user_id !== $user->user_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validator = Validator::make($request->all(), [
                'option_ids' => 'required|array|min:1',
                'option_ids.*' => 'exists:question_options,question_option_id',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $deleted = $question->options()->whereIn('question_option_id', $request->option_ids)->delete();

            return response()->json(['message' => "$deleted options deleted successfully"], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Question not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'Failed to delete options', 'details' => $e->getMessage()], 500);
        }
    }

}
