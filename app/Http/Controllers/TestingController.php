<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TestingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('testing/index', [
            'twitchId' => $user->twitch_id,
            'webhookUrl' => config('app.url').'/api/twitch/webhook',
            'webhookSecret' => $user->webhook_secret ?? config('app.twitch_webhook_secret'),
            'hasWebhookSecret' => $user->webhook_secret !== null,
        ]);
    }
}
