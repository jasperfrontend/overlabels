<?php

namespace App\Http\Controllers;

use App\Services\TwitchApiService;
use App\Services\TwitchTokenService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TwitchDataController extends Controller
{
    protected TwitchApiService $twitch;
    protected TwitchTokenService $tokenService;

    public function __construct(TwitchApiService $twitch, TwitchTokenService $tokenService)
    {
        $this->twitch = $twitch;
        $this->tokenService = $tokenService;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            Log::error('No authenticated user or access token');
            abort(403);
        }

        try {
            $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            // If Laravel cache doesn't provide API info, retrieve it from Twitch directly
            if (empty($twitchData['channel']['broadcaster_login'])) {
                $this->twitch->clearAllUserCaches($user->twitch_id);
                $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);
            }

            return Inertia::render('TwitchData', [
                'twitchData' => $twitchData,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch Twitch data', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return Inertia::render('TwitchData', [
                'twitchData' => [],
                'error' => 'Failed to fetch Twitch data. Please try refreshing the page.'
            ]);
        }
    }

    private function getUserOrAbort(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }
        return $user;
    }

    public function refreshAllTwitchApiData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearAllUserCaches($user->twitch_id);

        $this->getLiveTwitchData($request);

        return redirect()->back()->with([
            'message' => 'Twitch data refreshed!',
            'type' => 'success',
        ]);
    }

    public function getLiveTwitchData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        try {
            $this->twitch->getFreshTwitchData($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Twitch data', [$e->getMessage()]);
        }
        return redirect()->back()->with([
            'message' => 'Twitch API data refreshed.',
            'type' => 'success',
        ]);
    }

    public function refreshUserInfoData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearUserInfoCache($user->twitch_id); // dump existing user info cache
        try {
            $this->twitch->getUserInfo($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Twitch data', [$e->getMessage()]);
        } // retrieve fresh info from Twitch API

        return redirect()->back()->with([
            'message' => 'Twitch User data refreshed!',
            'type' => 'success',
        ]);
    }

    public function refreshChannelInfoData(Request $request)
    {
        try {
            $user = $this->getUserOrAbort($request);
            $this->twitch->clearChannelInfoCaches($user->twitch_id);
            $this->twitch->getChannelInfo($user->access_token, $user->twitch_id);
            return redirect()->back()->with([
                'message' => 'Twitch channel info (bio, tags) refreshed!',
                'type' => 'success',
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with([
                'message' => 'Failed to refresh Twitch channel info: ' . $e->getMessage(),
                'type' => 'error',
            ]);
        } finally {
            return false;
        }
    }

    public function refreshFollowedChannelsData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearFollowedChannelsCaches($user->twitch_id);
        try {
            $this->twitch->getFollowedChannels($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Followed Channels data', [$e->getMessage()]);
        }
        return redirect()->back()->with([
            'message' => 'Twitch followed channels data refreshed!',
            'type' => 'success',
        ]);
    }

    public function refreshChannelFollowersData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearChannelFollowersCaches($user->twitch_id);
        try {
            $this->twitch->getChannelFollowers($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Channel Followers data', [$e->getMessage()]);
        }
        return redirect()->back()->with([
            'message' => 'Twitch channel followers data refreshed!',
            'type' => 'success',
        ]);
    }

    public function refreshSubscribersData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearSubscribersCaches($user->twitch_id);
        try {
            $this->twitch->getChannelSubscribers($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Channel Subscribers data', [$e->getMessage()]);
        }
        return redirect()->back()->with([
            'message' => 'Twitch subscribers data refreshed!',
            'type' => 'success',
        ]);
    }

    public function refreshGoalsData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearGoalsCaches($user->twitch_id);
        try {
            $this->twitch->getChannelGoals($user->access_token, $user->twitch_id);
        } catch (Exception $e) {
            Log::error('Failed to fetch Channel Goals data', [$e->getMessage()]);
        }
        return redirect()->back()->with([
            'message' => 'Twitch goals data refreshed!',
            'type' => 'success',
        ]);
    }

}
