<?php

namespace App\Http\Controllers;

use App\Models\OverlayTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        // $request->user() grabs the currently authenticated user
        $user = $request->user();

        // Get user's latest alert templates (limit 5)
        $userAlertTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->alert()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        // Get user's latest static templates (limit 5)
        $userStaticTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->static()
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(5)
            ->get();

        // Get community templates (public templates from other users)
        $communityTemplates = OverlayTemplate::where('owner_id', '!=', $user->id)
            ->where('is_public', true)
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('dashboard/index', [
            'userName' => $user->name,
            'userId' => $user->id,
            'userAlertTemplates' => $userAlertTemplates,
            'userStaticTemplates' => $userStaticTemplates,
            'communityTemplates' => $communityTemplates,
        ]);
    }

    public function recentCommunityTemplates(): Response
    {
        $communityTemplates = OverlayTemplate::where('owner_id', '!=', auth()->id())
            ->where('is_public', true)
            ->with('owner:id,name,avatar')
            ->latest()
            ->limit(50) // @TODO: Make pagination of community templates page configurable
            ->get();

        return Inertia::render('dashboard/recents', [
            'communityTemplates' => $communityTemplates,
        ]);
    }
}
