<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\Kit;
use App\Models\OverlayAccessToken;
use App\Models\TemplateTagJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $starterKitId = config('app.starter_kit_id');
        $hasForkedKit = Kit::where('owner_id', $user->id)
            ->where('forked_from_id', $starterKitId)
            ->exists();

        $alertMappings = EventTemplateMapping::where('user_id', $user->id)->get();

        $latestTagJob = TemplateTagJob::where('user_id', $user->id)
            ->where('job_type', 'generate')
            ->latest()
            ->first();

        $tagsStatus = 'not_started';
        if ($latestTagJob) {
            $tagsStatus = $latestTagJob->status;
        }

        $hasToken = $user->overlayAccessTokens()
            ->where('is_active', true)
            ->exists();

        return response()->json([
            'kit_forked' => $hasForkedKit,
            'tags_status' => $tagsStatus,
            'alerts_mapped' => $alertMappings->count() > 0,
            'alert_mappings' => $alertMappings->map(fn ($m) => [
                'event_type' => $m->event_type,
                'display' => EventTemplateMapping::EVENT_TYPES[$m->event_type] ?? $m->event_type,
            ]),
            'token_created' => $hasToken,
            'has_webhook_secret' => $user->webhook_secret !== null,
        ]);
    }

    public function createToken(Request $request): JsonResponse
    {
        $user = $request->user();

        $tokenData = OverlayAccessToken::generateToken();

        $token = OverlayAccessToken::create([
            'user_id' => $user->id,
            'name' => 'Onboarding Token',
            'token_hash' => $tokenData['hash'],
            'token_prefix' => $tokenData['prefix'],
            'is_active' => true,
        ]);

        return response()->json([
            'plain_token' => $tokenData['plain'],
            'token_prefix' => $tokenData['prefix'],
            'token_id' => $token->id,
        ]);
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->update(['onboarded_at' => now()]);

        return response()->json([
            'success' => true,
            'onboarded_at' => $user->onboarded_at,
        ]);
    }
}
