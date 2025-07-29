<?php

namespace App\Http\Controllers;

use App\Models\OverlayHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class OverlayHashController extends Controller
{
    /**
     * Display the overlay hash management interface
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $hashes = OverlayHash::forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($hash) {
                return [
                    'id' => $hash->id,
                    'overlay_name' => $hash->overlay_name,
                    'description' => $hash->description,
                    'hash_key' => $hash->hash_key,
                    'is_active' => $hash->is_active,
                    'access_count' => $hash->access_count,
                    'last_accessed_at' => $hash->last_accessed_at?->diffForHumans(),
                    'expires_at' => $hash->expires_at?->format('Y-m-d H:i:s'),
                    'overlay_url' => $hash->getOverlayUrl(),
                    'created_at' => $hash->created_at->format('Y-m-d H:i:s'),
                    'is_valid' => $hash->isValid(),
                ];
            });

        return Inertia::render('overlayhashes/index', [
            'hashes' => $hashes,
        ]);
    }

    /**
     * Create a new overlay hash
     */
    public function store(Request $request)
    {
        $request->validate([
            'overlay_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
        ]);

        $user = $request->user();
        
        // Calculate expiration if provided
        $expiresAt = null;
        if ($request->expires_in_days) {
            $expiresAt = Carbon::now()->addDays($request->expires_in_days);
        }

        $hash = OverlayHash::createForUser(
            $user->id,
            $request->overlay_name,
            $request->description,
            $expiresAt,
            $request->allowed_ips
        );

        Log::info('Overlay hash created', [
            'user_id' => $user->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash created successfully',
            'hash' => [
                'id' => $hash->id,
                'hash_key' => $hash->hash_key,
                'overlay_url' => $hash->getOverlayUrl(),
                'overlay_name' => $hash->overlay_name,
            ]
        ]);
    }

    /**
     * Revoke (disable) an overlay hash
     */
    public function revoke(Request $request, OverlayHash $hash)
    {
        // Ensure the hash belongs to the authenticated user
        if ($hash->user_id !== $request->user()->id) {
            abort(403, 'You can only revoke your own overlay hashes');
        }

        $hash->revoke();

        Log::info('Overlay hash revoked', [
            'user_id' => $request->user()->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash revoked successfully'
        ]);
    }

    /**
     * Regenerate an overlay hash (create new hash key)
     */
    public function regenerate(Request $request, OverlayHash $hash)
    {
        // Ensure the hash belongs to the authenticated user
        if ($hash->user_id !== $request->user()->id) {
            abort(403, 'You can only regenerate your own overlay hashes');
        }

        $oldHashKey = $hash->hash_key;
        $newHashKey = $hash->regenerateHash();

        Log::info('Overlay hash regenerated', [
            'user_id' => $request->user()->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
            'old_hash' => substr($oldHashKey, 0, 8) . '...',
            'new_hash' => substr($newHashKey, 0, 8) . '...',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash regenerated successfully',
            'new_hash_key' => $newHashKey,
            'new_overlay_url' => $hash->getOverlayUrl(),
        ]);
    }

    /**
     * Delete an overlay hash permanently
     */
    public function destroy(Request $request, OverlayHash $hash)
    {
        // Ensure the hash belongs to the authenticated user
        if ($hash->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own overlay hashes');
        }

        Log::info('Overlay hash deleted', [
            'user_id' => $request->user()->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
        ]);

        $hash->delete();

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash deleted successfully'
        ]);
    }

    /**
     * Serve overlay content using hash authentication
     * This is the public endpoint that doesn't require Laravel authentication
     */
    public function serveOverlay(Request $request, string $hashKey)
    {
        $clientIp = $request->ip();
        
        // Find and validate the hash
        $hash = OverlayHash::findValidHash($hashKey, $clientIp);
        
        if (!$hash) {
            // Return completely empty response for invalid hashes (as requested)
            return response('', 404);
        }

        // Get the user who owns this hash
        $user = $hash->user;
        
        if (!$user || !$user->access_token) {
            Log::error('Overlay hash has no valid user or access token', [
                'hash_id' => $hash->id,
                'user_id' => $hash->user_id,
            ]);
            return response('', 500);
        }

        try {
            // Get fresh Twitch data for this user
            $twitchApiService = app(\App\Services\TwitchApiService::class);
            $twitchData = $twitchApiService->getExtendedUserData($user->access_token, $user->twitch_id);

            // Here we'll implement the template parsing logic
            // For now, return a simple JSON response to test the hash system
            return response()->json([
                'overlay_name' => $hash->overlay_name,
                'user_name' => $user->name,
                'access_count' => $hash->access_count,
                'twitch_data_available' => !empty($twitchData),
                'timestamp' => now()->toISOString(),
                // Include some sample data to verify it's working
                'sample_data' => [
                    'channel_name' => $twitchData['channel']['broadcaster_name'] ?? 'N/A',
                    'followers_total' => $twitchData['channel_followers']['total'] ?? 0,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error serving overlay', [
                'hash_id' => $hash->id,
                'error' => $e->getMessage(),
            ]);
            
            return response('', 500);
        }
    }

    /**
     * Test endpoint to verify hash authentication (for debugging)
     */
    public function testHash(Request $request, string $hashKey)
    {
        $clientIp = $request->ip();
        $hash = OverlayHash::findValidHash($hashKey, $clientIp);
        
        if (!$hash) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired hash']);
        }

        return response()->json([
            'valid' => true,
            'overlay_name' => $hash->overlay_name,
            'access_count' => $hash->access_count,
            'last_accessed' => $hash->last_accessed_at,
        ]);
    }
}