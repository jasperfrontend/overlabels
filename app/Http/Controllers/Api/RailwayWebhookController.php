<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\BroadcastVersionUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Random\RandomException;

class RailwayWebhookController extends Controller
{
    /**
     * Debounce window in seconds. Railway fires one webhook per service,
     * so with 4 services we get 4 near-simultaneous hits. We wait this
     * long after the last hit before actually broadcasting.
     */
    private const int DEBOUNCE_SECONDS = 300;

    /**
     * @throws RandomException
     */
    public function handle(Request $request, string $token): JsonResponse
    {
        $expectedToken = config('services.railway.webhook_secret');

        if (empty($expectedToken) || ! hash_equals($expectedToken, $token)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $type = $request->input('type');
        $status = $request->input('details.status');
        $commitHash = $request->input('details.commitHash');

        Log::info('Railway webhook received', [
            'type' => $type,
            'status' => $status,
            'commit' => $commitHash ? substr($commitHash, 0, 7) : null,
        ]);

        if (! in_array($type, ['Deployment.deployed', 'Deployment.redeployed']) || $status !== 'SUCCESS') {
            return response()->json(['status' => 'ignored']);
        }

        // Each webhook resets the debounce timer. Only the last job to
        // run will see its nonce still in cache and actually broadcast.
        $nonce = bin2hex(random_bytes(8));
        Cache::put('railway:deploy:nonce', $nonce, self::DEBOUNCE_SECONDS + 10);

        BroadcastVersionUpdate::dispatch($commitHash, $nonce)
            ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS));

        Log::info('Railway deploy debounced', [
            'sha' => $commitHash ? substr($commitHash, 0, 7) : null,
            'nonce' => $nonce,
        ]);

        return response()->json(['status' => 'debounced']);
    }
}
