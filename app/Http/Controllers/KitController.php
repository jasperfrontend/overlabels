<?php

namespace App\Http\Controllers;

use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Services\CloudinaryUploadService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class KitController extends Controller
{
    /**
     * Display a listing of the kits.
     */
    public function index(Request $request)
    {
        $kits = Kit::with(['owner', 'templates'])
            ->where('owner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        // Get recent public kits for discovery
        $recentPublicKits = Kit::with(['owner:id,name,avatar', 'templates'])
            ->public()
            ->where('owner_id', '!=', $request->user()->id)
            ->where('title', 'not like', 'Fork of%')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('kits/index', [
            'kits' => $kits,
            'recentPublicKits' => $recentPublicKits,
        ]);
    }

    /**
     * Show the form for creating a new kit.
     */
    public function create(Request $request)
    {
        $templates = OverlayTemplate::where('owner_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'type', 'slug']);

        return Inertia::render('kits/create', [
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created kit in storage.
     */
    public function store(Request $request, CloudinaryUploadService $cloudinary)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'required|boolean',
            'thumbnail_url' => 'nullable|url',
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'exists:overlay_templates,id',
        ]);

        // Verify all templates belong to the user
        $userTemplateIds = OverlayTemplate::where('owner_id', $request->user()->id)
            ->whereIn('id', $validated['template_ids'])
            ->pluck('id')
            ->toArray();

        if (count($userTemplateIds) !== count($validated['template_ids'])) {
            return back()->withErrors(['template_ids' => 'Invalid template selection.']);
        }

        $kit = new Kit;
        $kit->owner_id = $request->user()->id;
        $kit->title = $validated['title'];
        $kit->description = $validated['description'];
        $kit->is_public = $validated['is_public'];

        if ($request->filled('thumbnail_url')) {
            $kit->thumbnail = $request->input('thumbnail_url');
        }

        $kit->save();

        // Mark the upload as claimed so it survives the orphan sweep.
        $cloudinary->claim($kit->thumbnail);

        // Attach templates
        $kit->templates()->attach($userTemplateIds);

        return redirect()->route('kits.show', $kit)
            ->with('success', 'Kit created successfully!');
    }

    /**
     * Display the specified kit.
     */
    public function show(Kit $kit)
    {
        // Allow viewing if public or owned by a user
        if (! $kit->is_public && $kit->owner_id !== auth()->id()) {
            abort(403);
        }

        $kit->load(['owner', 'templates', 'forkedFrom']);

        return Inertia::render('kits/show', [
            'kit' => $kit,
            'canEdit' => $kit->owner_id === auth()->id(),
            'canFork' => auth()->check(),
        ]);
    }

    /**
     * Show the form for editing the specified kit.
     */
    public function edit(Kit $kit)
    {
        // Only owner can edit
        if ($kit->owner_id !== auth()->id()) {
            abort(403);
        }

        $templates = OverlayTemplate::where('owner_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'type', 'slug']);

        $kit->load('templates');

        return Inertia::render('kits/edit', [
            'kit' => $kit,
            'templates' => $templates,
            'selectedTemplateIds' => $kit->templates->pluck('id')->toArray(),
        ]);
    }

    /**
     * Update the specified kit in storage.
     */
    public function update(Request $request, Kit $kit, CloudinaryUploadService $cloudinary)
    {
        // Only owner can update
        if ($kit->owner_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'required|boolean',
            'thumbnail_url' => 'nullable|url',
            'template_ids' => 'required|array|min:1',
            'template_ids.*' => 'exists:overlay_templates,id',
        ]);

        // Verify all templates belong to the user
        $userTemplateIds = OverlayTemplate::where('owner_id', auth()->id())
            ->whereIn('id', $validated['template_ids'])
            ->pluck('id')
            ->toArray();

        if (count($userTemplateIds) !== count($validated['template_ids'])) {
            return back()->withErrors(['template_ids' => 'Invalid template selection.']);
        }

        $kit->title = $validated['title'];
        $kit->description = $validated['description'];
        $kit->is_public = $validated['is_public'];

        if ($request->filled('thumbnail_url')) {
            $oldThumbnail = $kit->thumbnail;
            $newThumbnail = $request->input('thumbnail_url');

            // Legacy local thumbnail cleanup (pre-Cloudinary kits).
            if ($oldThumbnail && ! filter_var($oldThumbnail, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($oldThumbnail)) {
                Storage::disk('public')->delete($oldThumbnail);
            }

            // Cloudinary cleanup when replacing one URL with another.
            if ($oldThumbnail && filter_var($oldThumbnail, FILTER_VALIDATE_URL) && $oldThumbnail !== $newThumbnail) {
                $cloudinary->deleteByUrl($oldThumbnail, excludeKitId: $kit->id);
            }

            $kit->thumbnail = $newThumbnail;
        }

        $kit->save();

        $cloudinary->claim($kit->thumbnail);

        // Sync templates
        $kit->templates()->sync($userTemplateIds);

        return redirect()->route('kits.show', $kit)
            ->with('success', 'Kit updated successfully!');
    }

    /**
     * Remove the specified kit from storage.
     */
    public function destroy(Kit $kit, CloudinaryUploadService $cloudinary)
    {
        // Only the owner can delete a kit
        if ($kit->owner_id !== auth()->id()) {
            abort(403);
        }

        // Check if the kit can be deleted
        if (! $kit->canBeDeleted()) {
            return back()->withErrors(['error' => 'This kit has been forked and cannot be deleted.']);
        }

        $thumbnail = $kit->thumbnail;

        $kit->delete();

        if ($thumbnail && filter_var($thumbnail, FILTER_VALIDATE_URL)) {
            $cloudinary->deleteByUrl($thumbnail);
        }

        return redirect()->route('kits.index')
            ->with('success', 'Kit deleted successfully!');
    }

    /**
     * Fork a kit
     */
    public function fork(Request $request, Kit $kit)
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        // Kit must be public or owned by user to fork
        if (! $kit->is_public && $kit->owner_id !== auth()->id()) {
            abort(403);
        }

        try {
            $forkedKit = $kit->fork($request->user());

            return redirect()->route('kits.show', $forkedKit)
                ->with('success', 'Kit forked successfully!');
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Failed to fork kit: '.$e->getMessage()]);
        }
    }

    /**
     * Get recent community kits
     */
    public function recent()
    {
        $kits = Kit::with(['owner', 'templates'])
            ->public()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json($kits);
    }
}
