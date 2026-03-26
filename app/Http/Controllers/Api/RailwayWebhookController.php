<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Pusher\Pusher;

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

        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true,
            ]
        );

        $pusher->trigger('app-updates', 'version.updated', [
            'sha' => $commitHash,
        ]);

        Log::info('Version update broadcast sent', ['sha' => substr($commitHash, 0, 7)]);

        return response()->json(['status' => 'broadcast_sent']);
    }
}
