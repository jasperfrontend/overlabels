<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Alerts\AlertEventSamples;
use App\Services\ImageUploadService;
use App\Services\Kits\KitPreview;
use App\Services\KitShareService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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

        // Get recent public kits for discovery. Copies are excluded so discovery
        // shows originals only - keyed off forked_from_id rather than the title
        // prefix, which is display copy and must stay free to change.
        $recentPublicKits = Kit::with(['owner:id,name,avatar', 'templates'])
            ->public()
            ->where('owner_id', '!=', $request->user()->id)
            ->whereNull('forked_from_id')
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
            'alertEvents' => AlertEventSamples::catalogue(),
            'authorTriggers' => $this->authorTriggers($request->user()),
        ]);
    }

    /**
     * Store a newly created kit in storage.
     */
    public function store(Request $request, ImageUploadService $images)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_public' => 'required|boolean',
            'thumbnail_url' => 'nullable|url',
            'template_ids' => 'required|array|min:1|max:'.Kit::MAX_TEMPLATES,
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

        $triggers = $this->validatedTriggers($request, $userTemplateIds);

        $kit = new Kit;
        $kit->owner_id = $request->user()->id;
        $kit->title = $validated['title'];
        $kit->description = $validated['description'] ?? null;
        $kit->is_public = $validated['is_public'];
        $kit->alert_triggers = $triggers;

        if ($request->filled('thumbnail_url')) {
            $kit->thumbnail = $request->input('thumbnail_url');
        }

        $kit->save();

        // Mark the upload as claimed so it survives the orphan sweep.
        $images->claim($kit->thumbnail);

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
        $preview = app(KitPreview::class)->build($kit, auth()->user());

        // The listing only needs what a card shows. The full models carried
        // every template's source to every viewer, private ones included;
        // the one place a template's source is sent now is `preview`, for
        // rendering, and only for alerts and the backdrop they play over.
        $kit->setRelation('templates', $kit->templates->map(
            fn (OverlayTemplate $t) => $t->setVisible(['id', 'name', 'type', 'slug', 'description', 'screenshot_url'])
        ));
        $kit->makeHidden('alert_triggers');

        return Inertia::render('kits/show', [
            'kit' => $kit,
            'preview' => $preview,
            'canEdit' => $kit->owner_id === auth()->id(),
            'canFork' => auth()->check(),
            // Only public kits have a markdown twin, so the affordance only
            // appears where the link would actually resolve for whoever the
            // owner sends it to.
            'markdownUrl' => $kit->is_public ? route('kits.markdown', $kit) : null,
        ]);
    }

    /**
     * The kit as one markdown document: the kit, then every overlay in it.
     *
     * Deliberately registered OUTSIDE the auth group that the rest of the kit
     * routes live in. `kits.show` requires a login, but the point of this
     * endpoint is that a URL can be handed to a language model, and one behind
     * an auth wall cannot be. It is safe to open because the document never
     * contains anything that is not already world-readable: private templates
     * inside a public kit are listed but their source is withheld, and every
     * template whose source IS included has its own public `.md`.
     */
    public function markdown(Kit $kit, KitShareService $share)
    {
        // The kit's own flag, not ownership: this route has no session to
        // check against, which is the whole reason it sits outside the group.
        if (! $kit->is_public) {
            abort(404, 'This kit is private');
        }

        return response(
            $share->markdown($kit, route('kits.markdown', $kit)),
            200,
            ['Content-Type' => 'text/markdown; charset=utf-8']
        );
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
            'alertEvents' => AlertEventSamples::catalogue(),
            'authorTriggers' => $this->authorTriggers(auth()->user()),
            'kitTriggers' => $kit->alertTriggers(),
        ]);
    }

    /**
     * Update the specified kit in storage.
     */
    public function update(Request $request, Kit $kit, ImageUploadService $images)
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
            'template_ids' => 'required|array|min:1|max:'.Kit::MAX_TEMPLATES,
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

        // Absent from the request = untouched, so an older page that never
        // sends triggers cannot wipe the ones the kit has.
        if ($request->has('alert_triggers')) {
            $kit->alert_triggers = $this->validatedTriggers($request, $userTemplateIds);
        }

        $kit->title = $validated['title'];
        $kit->description = $validated['description'] ?? null;
        $kit->is_public = $validated['is_public'];

        if ($request->filled('thumbnail_url')) {
            $oldThumbnail = $kit->thumbnail;
            $newThumbnail = $request->input('thumbnail_url');

            // Legacy local thumbnail cleanup (pre-object-storage kits).
            if ($oldThumbnail && ! filter_var($oldThumbnail, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($oldThumbnail)) {
                Storage::disk('public')->delete($oldThumbnail);
            }

            // Stored-image cleanup when replacing one URL with another.
            if ($oldThumbnail && filter_var($oldThumbnail, FILTER_VALIDATE_URL) && $oldThumbnail !== $newThumbnail) {
                $images->deleteByUrl($oldThumbnail, excludeKitId: $kit->id);
            }

            $kit->thumbnail = $newThumbnail;
        }

        $kit->save();

        $images->claim($kit->thumbnail);

        // Sync templates
        $kit->templates()->sync($userTemplateIds);

        return redirect()->route('kits.show', $kit)
            ->with('success', 'Kit updated successfully!');
    }

    /**
     * Remove the specified kit from storage.
     */
    public function destroy(Kit $kit, ImageUploadService $images)
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
            $images->deleteByUrl($thumbnail);
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

            // Copying a kit means wanting to use it: point the copier's events
            // at the copied alerts. Events they already have an alert for are
            // left alone, and the kit page shows which.
            $wiring = $forkedKit->wireAlertTriggers();
            $wired = count($wiring['wired']);
            $kept = count($wiring['kept']);
            $message = 'Kit copied!';
            if ($wired > 0) {
                $message .= " {$wired} ".($wired === 1 ? 'event now fires its alert' : 'events now fire their alerts').'.';
            }
            if ($kept > 0) {
                $message .= " {$kept} left alone because you already have an alert for ".($kept === 1 ? 'it' : 'them').'.';
            }

            return redirect()->route('kits.show', $forkedKit)
                ->with('success', $message);
        } catch (Exception $e) {
            return back()->withErrors(['error' => 'Failed to fork kit: '.$e->getMessage()]);
        }
    }

    /**
     * The kit's declared alert triggers from the request, validated against
     * the templates the kit holds: each names one of its ALERTS, an event
     * alerts can be triggered by, and no event twice. Null when there are none.
     *
     * @param  list<int>  $templateIds
     * @return list<array{template_id:int,service:string,event_type:string,duration_ms:int}>|null
     */
    private function validatedTriggers(Request $request, array $templateIds): ?array
    {
        $request->validate([
            'alert_triggers' => 'nullable|array|max:100',
            'alert_triggers.*.template_id' => 'required|integer',
            'alert_triggers.*.service' => 'required|string|max:50',
            'alert_triggers.*.event_type' => 'required|string|max:100',
            'alert_triggers.*.duration_ms' => 'nullable|integer|min:1000|max:60000',
        ]);

        $alertIds = OverlayTemplate::whereIn('id', $templateIds)->where('type', 'alert')->pluck('id')->all();
        $out = [];
        $seen = [];

        foreach ($request->input('alert_triggers', []) as $i => $trigger) {
            $templateId = (int) $trigger['template_id'];
            $service = (string) $trigger['service'];
            $eventType = (string) $trigger['event_type'];

            if (! in_array($templateId, $alertIds, true)) {
                throw ValidationException::withMessages(["alert_triggers.{$i}.template_id" => 'A trigger can only point at an alert in this kit.']);
            }
            if (! AlertEventSamples::isKnown($service, $eventType)) {
                throw ValidationException::withMessages(["alert_triggers.{$i}.event_type" => 'That is not an event alerts can be triggered by.']);
            }
            if (isset($seen["{$service}:{$eventType}"])) {
                throw ValidationException::withMessages(["alert_triggers.{$i}.event_type" => AlertEventSamples::label($service, $eventType).' is already claimed by another alert in this kit.']);
            }
            $seen["{$service}:{$eventType}"] = true;

            $out[] = [
                'template_id' => $templateId,
                'service' => $service,
                'event_type' => $eventType,
                'duration_ms' => (int) ($trigger['duration_ms'] ?? Kit::DEFAULT_TRIGGER_DURATION_MS),
            ];
        }

        return $out ?: null;
    }

    /**
     * What the author's own Triggers point at each of their alerts, so the kit
     * form can start from how they actually use them.
     *
     * @return array<int, list<array{service:string,event_type:string,duration_ms:int}>>
     */
    private function authorTriggers(User $user): array
    {
        $out = [];
        // A trigger whose alert was deleted keeps its row with no alert
        // (nullOnDelete); it points at nothing and prefills nothing.
        foreach (EventTemplateMapping::where('user_id', $user->id)->whereNotNull('template_id')->orderBy('id')->get() as $m) {
            $out[(int) $m->template_id][] = ['service' => AlertEventSamples::TWITCH, 'event_type' => $m->event_type, 'duration_ms' => (int) $m->duration_ms];
        }
        foreach (ExternalEventTemplateMapping::where('user_id', $user->id)->whereNotNull('overlay_template_id')->orderBy('id')->get() as $m) {
            $out[(int) $m->overlay_template_id][] = ['service' => $m->service, 'event_type' => $m->event_type, 'duration_ms' => (int) $m->duration_ms];
        }

        return $out;
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
