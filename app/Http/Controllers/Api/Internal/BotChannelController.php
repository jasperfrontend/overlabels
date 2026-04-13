<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class BotChannelController extends Controller
{
    public function index(): JsonResponse
    {
        $channels = User::where('bot_enabled', true)
            ->whereNotNull('twitch_data')
            ->get()
            ->map(function (User $user) {
                $login = $user->twitch_data['login'] ?? null;

                return $login ? strtolower($login) : null;
            })
            ->filter()
            ->values();

        return response()->json(['channels' => $channels]);
    }
}
