<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IntegrationSuggestionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'service_url' => 'required|string|max:500',
            'example' => 'required|string|max:1000',
            'context' => 'nullable|string|max:1000',
        ]);

        $user = $request->user();
        $webhookUrl = config('services.integration_suggestions.webhook_url');

        if (! $webhookUrl) {
            Log::warning('Integration suggestion received but no webhook URL configured', [
                'user' => $user->display_name,
                'service_url' => $validated['service_url'],
            ]);

            return response()->json(['message' => 'Suggestion received.']);
        }

        $embed = [
            'title' => 'Integration Suggestion',
            'color' => 0x7C3AED, // violet-600
            'fields' => [
                ['name' => 'From', 'value' => $user->display_name.' ('.$user->twitch_id.')', 'inline' => true],
                ['name' => 'Service URL', 'value' => $validated['service_url'], 'inline' => false],
                ['name' => 'What it does', 'value' => $validated['example'], 'inline' => false],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        if (! empty($validated['context'])) {
            $embed['fields'][] = ['name' => 'Additional context', 'value' => $validated['context'], 'inline' => false];
        }

        try {
            Http::post($webhookUrl, [
                'embeds' => [$embed],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send integration suggestion webhook', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'Suggestion received.']);
    }
}
