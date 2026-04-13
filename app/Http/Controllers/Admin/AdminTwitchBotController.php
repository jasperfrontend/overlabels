<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BotToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class AdminTwitchBotController extends Controller
{
    private const ACCOUNT = 'overlabels';

    private const SCOPES = ['user:read:chat', 'user:write:chat', 'user:bot'];

    public function index(): Response
    {
        $token = BotToken::where('account', self::ACCOUNT)->first();

        return Inertia::render('admin/TwitchBot', [
            'connected' => $token !== null,
            'expires_at' => $token?->expires_at,
            'obtained_at' => $token?->obtained_at,
            'scopes' => $token?->scopes ?? [],
            'client_id_configured' => ! empty(config('services.twitchbot.client_id')),
            'listener_secret_configured' => ! empty(config('services.twitchbot.listener_secret')),
        ]);
    }

    public function redirect(): RedirectResponse
    {
        $params = http_build_query([
            'client_id' => config('services.twitchbot.client_id'),
            'redirect_uri' => config('services.twitchbot.redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'force_verify' => 'true',
        ]);

        return redirect("https://id.twitch.tv/oauth2/authorize?{$params}");
    }

    /**
     * @throws ConnectionException
     */
    public function callback(Request $request): RedirectResponse
    {
        if ($error = $request->query('error')) {
            return redirect()->route('admin.twitchbot.index')
                ->with(['message' => "Twitch rejected authorization: {$error}", 'type' => 'error']);
        }

        $code = (string) $request->query('code', '');

        if ($code === '') {
            return redirect()->route('admin.twitchbot.index')
                ->with(['message' => 'No authorization code received.', 'type' => 'error']);
        }

        $tokenResponse = Http::asForm()->post('https://id.twitch.tv/oauth2/token', [
            'client_id' => config('services.twitchbot.client_id'),
            'client_secret' => config('services.twitchbot.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => config('services.twitchbot.redirect'),
        ]);

        if (! $tokenResponse->ok()) {
            Log::error('TwitchBot token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
            ]);

            return redirect()->route('admin.twitchbot.index')
                ->with(['message' => 'Failed to exchange authorization code.', 'type' => 'error']);
        }

        $data = $tokenResponse->json();
        $obtainedAt = time();
        $expiresAt = $obtainedAt + (int) ($data['expires_in'] ?? 0);

        BotToken::updateOrCreate(
            ['account' => self::ACCOUNT],
            [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'],
                'expires_at' => $expiresAt,
                'obtained_at' => $obtainedAt,
                'scopes' => $data['scope'] ?? self::SCOPES,
            ],
        );

        return redirect()->route('admin.twitchbot.index')
            ->with(['message' => 'Bot authenticated. Tokens stored. The bot service can now pull them from the internal API.', 'type' => 'success']);
    }
}
