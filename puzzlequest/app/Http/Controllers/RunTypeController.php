<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RunType;

class RunTypeController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    /**
     * List all run types
     */
    public function index()
    {
        $runTypes = RunType::all();
        return response()->json($runTypes, 200);
    }

    /**
     * Show a single run type
     */
    public function show($id)
    {
        $runType = RunType::find($id);
        if (!$runType) {
            return response()->json(['error' => 'Run type not found'], 404);
        }
        return response()->json($runType, 200);
    }
}
