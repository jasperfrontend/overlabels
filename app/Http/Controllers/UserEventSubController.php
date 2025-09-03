<?php

namespace App\Http\Controllers;

use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\UserEventsubSubscription;
use App\Services\UserEventSubManager;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class UserEventSubController extends Controller
{
    private UserEventSubManager $manager;
    
    public function __construct(UserEventSubManager $manager)
    {
        $this->manager = $manager;
    }
    
    /**
     * Show the EventSub management page
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $subscriptions = UserEventsubSubscription::where('user_id', $user->id)
            ->orderBy('event_type')
            ->get()
            ->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'event_type' => $sub->event_type,
                    'status' => $sub->status,
                    'is_active' => $sub->isActive(),
                    'needs_renewal' => $sub->needsRenewal(),
                    'created_at' => $sub->created_at->toISOString(),
                    'last_verified_at' => $sub->last_verified_at?->toISOString(),
                ];
            });
        
        return Inertia::render('EventSubManager', [
            'subscriptions' => $subscriptions,
            'isConnected' => $user->eventsub_connected_at !== null,
            'connectedAt' => $user->eventsub_connected_at?->toISOString(),
            'autoConnect' => $user->eventsub_auto_connect,
            'supportedEvents' => array_keys($this->getSupportedEvents()),
        ]);
    }
    
    /**
     * Connect EventSub (setup subscriptions)
     */
    public function connect(Request $request)
    {
        $user = $request->user();
        
        try {
            // Dispatch job to setup subscriptions
            SetupUserEventSubSubscriptions::dispatch($user, false);
            
            return response()->json([
                'success' => true,
                'message' => 'EventSub setup has been queued. Subscriptions will be created shortly.',
            ]);
            
        } catch (Exception $e) {
            Log::error("Failed to queue EventSub setup", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to setup EventSub connections.',
            ], 500);
        }
    }
    
    /**
     * Disconnect EventSub (remove subscriptions)
     */
    public function disconnect(Request $request)
    {
        $user = $request->user();
        
        try {
            $deletedCount = $this->manager->removeUserSubscriptions($user);
            
            return response()->json([
                'success' => true,
                'message' => "Removed $deletedCount EventSub subscriptions.",
                'deleted_count' => $deletedCount,
            ]);
            
        } catch (Exception $e) {
            Log::error("Failed to disconnect EventSub", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to disconnect EventSub.',
            ], 500);
        }
    }
    
    /**
     * Refresh subscriptions (verify and renew if needed)
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        
        try {
            $status = $this->manager->verifyUserSubscriptions($user);
            
            return response()->json([
                'success' => true,
                'message' => 'Subscriptions refreshed.',
                'status' => $status,
            ]);
            
        } catch (Exception $e) {
            Log::error("Failed to refresh EventSub subscriptions", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh subscriptions.',
            ], 500);
        }
    }
    
    /**
     * Toggle auto-connect setting
     */
    public function toggleAutoConnect(Request $request)
    {
        $user = $request->user();
        $enabled = $request->boolean('enabled');
        
        $user->update([
            'eventsub_auto_connect' => $enabled,
        ]);
        
        // If enabling and not connected, setup subscriptions
        if ($enabled && !$user->eventsub_connected_at) {
            SetupUserEventSubSubscriptions::dispatch($user, false);
            
            return response()->json([
                'success' => true,
                'message' => 'Auto-connect enabled. Setting up subscriptions...',
            ]);
        }
        
        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Auto-connect enabled.' : 'Auto-connect disabled.',
        ]);
    }
    
    /**
     * Get subscription status
     */
    public function status(Request $request)
    {
        $user = $request->user();
        
        $subscriptions = UserEventsubSubscription::where('user_id', $user->id)
            ->select('event_type', 'status', 'last_verified_at')
            ->get();
        
        $activeCount = $subscriptions->where('status', 'enabled')->count();
        $totalCount = $subscriptions->count();
        
        return response()->json([
            'is_connected' => $user->eventsub_connected_at !== null,
            'connected_at' => $user->eventsub_connected_at?->toISOString(),
            'auto_connect' => $user->eventsub_auto_connect,
            'subscription_count' => $totalCount,
            'active_count' => $activeCount,
            'subscriptions' => $subscriptions,
        ]);
    }
    
    /**
     * Get admin statistics (for super admins)
     */
    public function adminStats(Request $request)
    {
        // You might want to add admin authorization check here
        // if (!$request->user()->is_admin) { abort(403); }
        
        $stats = $this->manager->getGlobalStats();
        
        return response()->json($stats);
    }
    
    private function getSupportedEvents(): array
    {
        return [
            'channel.follow' => 'New Followers',
            'channel.subscribe' => 'New Subscribers',
            'channel.subscription.gift' => 'Gift Subscriptions',
            'channel.subscription.message' => 'Resubscriptions',
            'channel.raid' => 'Raids',
            'channel.channel_points_custom_reward_redemption.add' => 'Channel Points Redeemed',
            'channel.channel_points_custom_reward_redemption.update' => 'Channel Points Updated',
            'stream.online' => 'Stream Goes Live',
            'stream.offline' => 'Stream Goes Offline',
        ];
    }
}