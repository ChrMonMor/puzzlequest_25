<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\History;

class WebHistoryController extends Controller
{
    /**
     * Show detailed view for a history record (must be owned by current user)
     */
    public function show(Request $request, $historyId)
    {
        $user = auth('api')->user();
        if (!$user) {
            // Try session-backed JWT if available
            if ($request->session()->has('jwt_token')) {
                try {
                    $token = $request->session()->get('jwt_token');
                    $jwtUser = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->toUser();
                    if ($jwtUser) $user = $jwtUser;
                } catch (\Throwable $e) {
                    // ignore
                }
            }
        }

        if (!$user) {
            return redirect()->route('login')->with('error', 'Please log in to view your history.');
        }

        $history = History::with(['run', 'flags'])->where('history_id', $historyId)->firstOrFail();

        if ($history->user_id !== $user->user_id) {
            abort(403, 'You are not authorized to view this history.');
        }

        return view('history.show', ['history' => $history]);
    }
}
