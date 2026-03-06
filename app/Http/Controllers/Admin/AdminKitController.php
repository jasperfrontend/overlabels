<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kit;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminKitController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(): Response
    {
        $kits = Kit::with('owner:id,name,twitch_id')
            ->whereNull('forked_from_id')
            ->orderByDesc('is_starter_kit')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'owner_id', 'is_public', 'is_starter_kit', 'fork_count', 'created_at']);

        return Inertia::render('admin/kits/index', [
            'kits' => $kits,
        ]);
    }

    public function setStarter(Request $request, Kit $kit): RedirectResponse
    {
        $previous = Kit::where('is_starter_kit', true)->first();

        Kit::where('is_starter_kit', true)->update(['is_starter_kit' => false]);
        $kit->update(['is_starter_kit' => true]);

        $this->audit->log($request->user(), 'kit.starter_kit_changed', 'Kit', $kit->id, [
            'previous_kit_id' => $previous?->id,
            'previous_kit_title' => $previous?->title,
            'new_kit_id' => $kit->id,
            'new_kit_title' => $kit->title,
        ], $request);

        return back()->with('message', "\"{$kit->title}\" is now the starter kit.");
    }
}
