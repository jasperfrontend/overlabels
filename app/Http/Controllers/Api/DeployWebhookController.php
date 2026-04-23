<?php

namespace App\Http\Controllers\Api;

use App\Events\VersionUpdated;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployWebhookController extends Controller
{
    public function handle(Request $request, string $token): JsonResponse
    {
        $expectedToken = config('services.deploy.webhook_secret');

        if (empty($expectedToken) || ! hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $sha = $request->input('sha');

        broadcast(new VersionUpdated($sha));

        Log::info('Deploy webhook broadcast sent', [
            'sha' => $sha ? substr($sha, 0, 7) : null,
        ]);

        return response()->json(['status' => 'broadcast']);
    }
}
