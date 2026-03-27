<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Events\VersionUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RailwayWebhookController extends Controller
{
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

        broadcast(new VersionUpdated($commitHash));

        Log::info('Version update broadcast sent', ['sha' => $commitHash ? substr($commitHash, 0, 7) : null]);

        return response()->json(['status' => 'broadcast_sent']);
    }
}
