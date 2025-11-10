<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * @group History Flags
 * @authenticated
 *
 * Lightweight resource for per-history flag records created when starting a run.
 */
class HistoryFlagController extends Controller
{
    public function __construct()
    {
        // Block session-only guests from mutating actions
        $this->middleware(\App\Http\Middleware\BlockGuestMiddleware::class)->only(['store', 'update', 'destroy']);
    }
    
    public function index()
    {
        /**
         * List history flags
         *
         * @response 200 [{"history_flag_id":"uuid","history_id":"uuid","flag_id":"uuid","history_flag_reached":null}]
         */
        // Return placeholder for now — implementation may be added later
        return response()->json([], 200);
    }

    public function store(Request $request)
    {
        /**
         * Create a history flag record
         *
         * @bodyParam history_id string required History UUID the flag belongs to. Example: "..."
         * @bodyParam flag_id string required Flag UUID being recorded. Example: "..."
         * @bodyParam history_flag_point numeric nullable Optional score/points earned. Example: 10.5
         * @response 201 {"message":"HistoryFlag created","history_flag":{"history_flag_id":"uuid","history_id":"...","flag_id":"..."}}
         */
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(Request $request)
    {
        /**
         * Show a history flag
         *
         * @urlParam id string required HistoryFlag UUID.
         * @response 200 {"history_flag_id":"uuid","history_id":"...","flag_id":"...","history_flag_reached":null}
         */
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(Request $request)
    {
        /**
         * Update a history flag
         *
         * @bodyParam history_flag_id string required HistoryFlag UUID.
         * @bodyParam history_flag_reached string nullable ISO8601 timestamp when the flag was reached.
         * @response 200 {"message":"HistoryFlag updated","history_flag":{"history_flag_id":"uuid"}}
         */
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(Request $request)
    {
        /**
         * Delete a history flag
         *
         * @bodyParam history_flag_id string required HistoryFlag UUID.
         * @response 200 {"message":"HistoryFlag deleted"}
         */
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
