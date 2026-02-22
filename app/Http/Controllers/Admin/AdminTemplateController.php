<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OverlayTemplate;
use App\Services\AdminAuditService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTemplateController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $query = OverlayTemplate::with('owner:id,name,twitch_id');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->has('is_public')) {
            $query->where('is_public', $request->boolean('is_public'));
        }

        if ($owner = $request->input('owner')) {
            $query->whereHas('owner', function ($q) use ($owner) {
                $q->where('name', 'like', "%{$owner}%")
                    ->orWhere('twitch_id', 'like', "%{$owner}%");
            });
        }

        $templates = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('admin/templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'type', 'is_public', 'owner']),
        ]);
    }

    public function show(OverlayTemplate $template): Response
    {
        $template->load('owner:id,name,twitch_id');

        $forksCount = $template->forks()->count();
        $controlsCount = $template->controls()->count();
        $eventMappingsCount = $template->eventMappings()->count();

        return Inertia::render('admin/templates/show', [
            'template' => $template,
            'forksCount' => $forksCount,
            'controlsCount' => $controlsCount,
            'eventMappingsCount' => $eventMappingsCount,
        ]);
    }

    public function update(Request $request, OverlayTemplate $template): RedirectResponse
    {
        $request->validate(['is_public' => 'required|boolean']);

        $oldValue = $template->is_public;
        $template->update(['is_public' => $request->boolean('is_public')]);

        $this->audit->log($request->user(), 'template.visibility_changed', 'OverlayTemplate', $template->id, [
            'slug' => $template->slug,
            'from' => $oldValue,
            'to' => $request->boolean('is_public'),
        ], $request);

        return back()->with('message', 'Template updated.');
    }

    public function destroy(Request $request, OverlayTemplate $template): RedirectResponse
    {
        try {
            $this->audit->log($request->user(), 'template.deleted', 'OverlayTemplate', $template->id, [
                'slug' => $template->slug,
                'name' => $template->name,
                'owner_id' => $template->owner_id,
            ], $request);

            $template->delete();
        } catch (Exception $e) {
            return back()->withErrors(['template' => $e->getMessage()]);
        }

        return redirect()->route('admin.templates.index')->with('message', 'Template deleted.');
    }
}
