<?php

namespace App\Http\Controllers\Api\Internal;

use App\Http\Controllers\Controller;
use App\Services\BotPresence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /internal/bot/presence - the bot reports the channel logins whose chat
 * it is subscribed to right now. Sent after every channel sync (every 60 s
 * and on each push). Feeds the `bot.present` wire on /wiring and nothing
 * else. An empty list is a valid report: the bot is running and in no chats.
 */
class BotPresenceController extends Controller
{
    public function __construct(private BotPresence $presence) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'logins' => 'present|array|max:5000',
            'logins.*' => 'string|max:64',
        ]);

        $logins = array_values(array_unique(array_map('strtolower', $data['logins'])));

        $this->presence->record($logins);

        return response()->json(['ok' => true, 'count' => count($logins)]);
    }
}
