<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kit;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        // Read every flagged kit, not just one. This screen can only ever
        // produce a single starter, but direct database edits have produced
        // several, and in that case an audit entry naming one arbitrary
        // "previous" kit records something that was not true.
        $previous = Kit::where('is_starter_kit', true)
            ->where('id', '!=', $kit->id)
            ->get(['id', 'title']);

        // Both writes go through the query builder, and the clear excludes the
        // target. Doing it as $kit->update(['is_starter_kit' => true]) after a
        // mass clear silently wrote nothing when the kit was already flagged:
        // route-model binding hydrated it as true, the mass update did not
        // refresh the instance, so the attribute was never dirty and save()
        // issued no UPDATE - leaving zero starter kits.
        DB::transaction(function () use ($kit) {
            Kit::where('is_starter_kit', true)
                ->where('id', '!=', $kit->id)
                ->update(['is_starter_kit' => false]);

            Kit::whereKey($kit->id)->update(['is_starter_kit' => true]);
        });

        $kit->refresh();

        $this->audit->log($request->user(), 'kit.starter_kit_changed', 'Kit', $kit->id, [
            'previous_kit_id' => $previous->first()?->id,
            'previous_kit_title' => $previous->first()?->title,
            'cleared_kits' => $previous->map(fn (Kit $k) => ['id' => $k->id, 'title' => $k->title])->all(),
            'new_kit_id' => $kit->id,
            'new_kit_title' => $kit->title,
        ], $request);

        return back()->with('message', "\"{$kit->title}\" is now the starter kit.");
    }
}
