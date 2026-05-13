<?php

namespace App\Http\Controllers;

use App\Models\RecipeInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Web-facing endpoints for installed recipe instances. Today only the
 * fire-button action is exposed - the dashboard-button trigger kind from
 * the manifest. A future Vue page will list a user's installed instances
 * + their dashboard buttons and POST here to invoke them.
 *
 * Auth: standard web/auth.redirect middleware. Ownership is verified per
 * request by matching recipe_instance.user_id against the authenticated
 * user.
 */
class RecipeInstanceController extends Controller
{
    /**
     * GET /dashboard/recipes
     *
     * Lists the authenticated user's installed Recipe instances. For each
     * instance, exposes the dashboard_button triggers as clickable rows
     * and surfaces the last picked result and timestamp for each picker.
     */
    public function index(Request $request): Response
    {
        $instances = RecipeInstance::with(['recipe', 'pickers'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (RecipeInstance $instance) {
                $manifest = $instance->recipe?->manifest ?? [];

                $buttonsByPickerRef = [];
                foreach ($manifest['triggers'] ?? [] as $trigger) {
                    if (($trigger['kind'] ?? null) !== 'dashboard_button') {
                        continue;
                    }
                    if (! preg_match('/^pickers\.([a-z][a-z0-9_]*)$/', $trigger['fires'] ?? '', $m)) {
                        continue;
                    }
                    $buttonsByPickerRef[$m[1]] = $trigger['label'] ?? 'Fire';
                }

                $pickerById = $instance->pickers->keyBy('id');

                $buttons = [];
                foreach ($instance->primitive_map['pickers'] ?? [] as $ref => $pickerId) {
                    if (! isset($buttonsByPickerRef[$ref])) {
                        continue;
                    }
                    $picker = $pickerById->get($pickerId);
                    if (! $picker) {
                        continue;
                    }
                    $buttons[] = [
                        'picker_ref' => $ref,
                        'label' => $buttonsByPickerRef[$ref],
                        'last_result' => $picker->last_result,
                        'last_result_at' => $picker->last_result_at?->timestamp,
                        'is_running' => $picker->is_running,
                    ];
                }

                return [
                    'id' => $instance->id,
                    'instance_slug' => $instance->instance_slug,
                    'label' => $instance->label,
                    'recipe' => [
                        'slug' => $instance->recipe?->slug,
                        'name' => $instance->recipe?->name,
                        'version' => $instance->recipe?->version,
                    ],
                    'tag_prefix' => "c:{$instance->recipe?->slug}:{$instance->instance_slug}",
                    'buttons' => $buttons,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('dashboard/recipes', [
            'instances' => $instances,
        ]);
    }

    /**
     * POST /recipes/instances/{instance}/fire-button
     *
     * Body: { picker_ref: string }
     *
     * Fires the picker named in the manifest's primitives.pickers[ref]
     * for this instance. The manifest must declare a dashboard_button
     * trigger that fires this picker; firing one that isn't button-wired
     * would be a confusing surprise to the installer.
     */
    public function fireButton(Request $request, RecipeInstance $instance): JsonResponse
    {
        if ($instance->user_id !== $request->user()->id) {
            throw new HttpException(404);
        }

        $data = $request->validate([
            'picker_ref' => 'required|string|max:50|regex:/^[a-z][a-z0-9_]{0,49}$/',
        ]);

        $pickerRef = $data['picker_ref'];
        $manifest = $instance->recipe->manifest ?? [];

        $hasButton = false;
        foreach ($manifest['triggers'] ?? [] as $trigger) {
            if (($trigger['kind'] ?? null) === 'dashboard_button'
                && ($trigger['fires'] ?? null) === "pickers.{$pickerRef}"
            ) {
                $hasButton = true;
                break;
            }
        }

        if (! $hasButton) {
            throw new HttpException(404);
        }

        $pickerId = $instance->primitive_map['pickers'][$pickerRef] ?? null;
        if ($pickerId === null) {
            throw new HttpException(404);
        }

        $picker = $instance->pickers->firstWhere('id', $pickerId);
        if (! $picker) {
            throw new HttpException(404);
        }

        $result = $picker->fire();

        return response()->json([
            'fired' => $result !== null,
            'result' => $result,
        ]);
    }
}
