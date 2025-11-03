<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\QuestionType;

class QuestionTypeController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    public function index()
    {
        return response()->json(QuestionType::all());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'question_type_name' => 'required|string|max:255',
        ]);

        $type = QuestionType::create($validated);
        return response()->json($type, 201);
    }

    public function show(Request $request)
    {
        $type = QuestionType::find($request->id);
        return $type
            ? response()->json($type)
            : response()->json(['error' => 'Question type not found'], 404);
    }

    public function update(Request $request)
    {
        $type = QuestionType::find($request->id);
        if (!$type) return response()->json(['error' => 'Question type not found'], 404);

        $type->update($request->only('question_type_name'));
        return response()->json(['message' => 'Updated successfully', 'data' => $type]);
    }

    public function destroy(Request $request)
    {
        $type = QuestionType::find($request->id);
        if (!$type) return response()->json(['error' => 'Question type not found'], 404);

        $type->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
