<?php

namespace App\Http\Controllers;

use App\Services\TwitchApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TwitchDataController extends Controller
{
    protected TwitchApiService $twitch;

    public function __construct(TwitchApiService $twitch)
    {
        $this->twitch = $twitch;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            Log::error('No authenticated user or access token');
            abort(403);
        }
        $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);
        return Inertia::render('TwitchData', [
            'twitchData' => $twitchData,
        ]);
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
        return redirect()->back()->with([
            'message' => 'Twitch data refreshed!', 
            'type' => 'success',
        ]);
    }

    public function refreshChannelInfoData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearChannelInfoCaches($user->twitch_id);
        return redirect()->back()->with([
            'message' => 'Twitch channel info (bio, tags, profile pic) refreshed!', 
            'type' => 'success',
        ]);
    }

    public function refreshFollowedChannelsData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearFollowedChannelsCaches($user->twitch_id);
        return redirect()->back()->with([
            'message' => 'Twitch followed channels data refreshed!', 
            'type' => 'success',
        ]);
    }

    public function refreshChannelFollowersData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearChannelFollowersCaches($user->twitch_id);
        return redirect()->back()->with([
            'message' => 'Twitch channel followers data refreshed!', 
            'type' => 'success',
        ]);
    }

    public function refreshSubscribersData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearSubscribersCaches($user->twitch_id);
        return redirect()->back()->with([
            'message' => 'Twitch subscribers data refreshed!', 
            'type' => 'success',
        ]);
    }

    public function refreshGoalsData(Request $request)
    {
        $user = $this->getUserOrAbort($request);
        $this->twitch->clearGoalsCaches($user->twitch_id);
        return redirect()->back()->with([
            'message' => 'Twitch goals data refreshed!', 
            'type' => 'success',
        ]);
    }

}
