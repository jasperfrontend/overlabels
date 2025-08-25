<?php

namespace App\Http\Controllers;

use App\Models\Kit;
use App\Models\OverlayTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        return Inertia::render('Kits/Index', [
            'kits' => $kits,
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

        return Inertia::render('Kits/Create', [
            'templates' => $templates,
        ]);
    }

    /**
     * Store a newly created kit in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'required|boolean',
            'thumbnail' => 'nullable|image|max:10240|dimensions:max_width=2560,max_height=1440',
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

        $kit = new Kit();
        $kit->owner_id = $request->user()->id;
        $kit->title = $validated['title'];
        $kit->description = $validated['description'];
        $kit->is_public = $validated['is_public'];

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('kit-thumbnails', $filename, 'public');
            $kit->thumbnail = $path;
        }

        $kit->save();

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
        // Allow viewing if public or owned by user
        if (!$kit->is_public && $kit->owner_id !== auth()->id()) {
            abort(403);
        }

        $kit->load(['owner', 'templates', 'forkedFrom']);

        return Inertia::render('Kits/Show', [
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

        return Inertia::render('Kits/Edit', [
            'kit' => $kit,
            'templates' => $templates,
            'selectedTemplateIds' => $kit->templates->pluck('id')->toArray(),
        ]);
    }

    /**
     * Update the specified kit in storage.
     */
    public function update(Request $request, Kit $kit)
    {
        // Only owner can update
        if ($kit->owner_id !== auth()->id()) {
            abort(403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'required|boolean',
            'thumbnail' => 'nullable|image|max:10240|dimensions:max_width=2560,max_height=1440',
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

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if exists
            if ($kit->thumbnail && Storage::disk('public')->exists($kit->thumbnail)) {
                Storage::disk('public')->delete($kit->thumbnail);
            }

            $file = $request->file('thumbnail');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('kit-thumbnails', $filename, 'public');
            $kit->thumbnail = $path;
        }

        $kit->save();

        // Sync templates
        $kit->templates()->sync($userTemplateIds);

        return redirect()->route('kits.show', $kit)
            ->with('success', 'Kit updated successfully!');
    }

    /**
     * Remove the specified kit from storage.
     */
    public function destroy(Kit $kit)
    {
        // Only owner can delete
        if ($kit->owner_id !== auth()->id()) {
            abort(403);
        }

        // Check if kit can be deleted
        if (!$kit->canBeDeleted()) {
            return back()->withErrors(['error' => 'This kit has been forked and cannot be deleted.']);
        }

        $kit->delete();

        return redirect()->route('kits.index')
            ->with('success', 'Kit deleted successfully!');
    }

    /**
     * Fork a kit
     */
    public function fork(Request $request, Kit $kit)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Kit must be public or owned by user to fork
        if (!$kit->is_public && $kit->owner_id !== auth()->id()) {
            abort(403);
        }

        try {
            $forkedKit = $kit->fork($request->user());

            return redirect()->route('kits.show', $forkedKit)
                ->with('success', 'Kit forked successfully!');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to fork kit: ' . $e->getMessage()]);
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