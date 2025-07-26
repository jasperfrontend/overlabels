<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TwitchDataController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            return Log::error('error: No authenticated user or access token');
        }
        $twitchService = new \App\Services\TwitchApiService();
        $freshData = $twitchService->getExtendedUserData($user->access_token, $user->twitch_id);
        
        return Inertia::render('TwitchData', [
            'twitchData' => $freshData,
        ]);
    }
}