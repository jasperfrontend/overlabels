<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OverlayControl;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only "which overlays use which controls" view. Answers from the UI what
 * previously needed an SSH + ad-hoc query: every control the user owns, its
 * scope (user-scoped service controls apply to all overlays; template-scoped
 * controls list the specific overlays), and where the same key is duplicated
 * across overlays (the broadcast fan-out smell).
 */
class ControlUsageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $controls = OverlayControl::where('user_id', $user->id)
            ->with('template:id,name,slug')
            ->orderBy('source')
            ->orderBy('key')
            ->get();

        $groups = $controls
            ->groupBy(fn (OverlayControl $c) => ($c->source ? $c->source.':' : '').$c->key)
            ->map(function ($rows, string $displayKey) {
                /** @var OverlayControl $first */
                $first = $rows->first();
                $templateScoped = $rows->whereNotNull('overlay_template_id');

                return [
                    'key' => $displayKey,
                    'source' => $first->source,
                    'type' => $first->type,
                    'source_managed' => (bool) $first->source_managed,
                    'user_scoped' => $rows->contains(fn (OverlayControl $c) => $c->overlay_template_id === null),
                    'overlays' => $templateScoped
                        ->map(fn (OverlayControl $c) => [
                            'name' => $c->template?->name ?: 'Untitled overlay',
                            'slug' => $c->template?->slug,
                        ])
                        ->values(),
                    'instances' => $rows->count(),
                    'value' => (string) ($first->value ?? ''),
                ];
            })
            ->sortByDesc('instances')
            ->values();

        return Inertia::render('settings/Controls', [
            'groups' => $groups,
        ]);
    }
}
