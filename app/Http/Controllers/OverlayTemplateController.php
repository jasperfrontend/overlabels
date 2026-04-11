<?php

namespace App\Http\Controllers;

use App\Events\TemplateUpdated;
use App\Models\ExternalIntegration;
use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Services\HtmlSanitizationService;
use App\Services\StreamSessionService;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Services\TwitchTokenService;
use Cloudinary\Cloudinary;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OverlayTemplateController extends Controller
{
    protected TwitchApiService $twitchService;

    protected TemplateTagController $templateTagController;

    protected TwitchEventSubService $eventSubService;

    protected TemplateDataMapperService $mapper;

    public function __construct(

        TwitchApiService $twitchService,
        TwitchEventSubService $eventSubService,
        TemplateDataMapperService $mapper,
        TemplateTagController $templateTagController
    ) {
        $this->twitchService = $twitchService;
        $this->eventSubService = $eventSubService;
        $this->mapper = $mapper;
        $this->templateTagController = $templateTagController;
    }

    /**
     * Display a listing of templates
     */
    public function index(Request $request)
    {
        $templates = OverlayTemplate::query()
            ->where(function ($query) use ($request) {
                $query->where('is_public', true)
                    ->orWhere('owner_id', $request->user()->id);
            })
            ->when($request->input('filter') === 'mine', function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id);
            })
            ->when($request->input('filter') === 'public', function ($query) {
                $query->where('is_public', true);
            })
            ->when($request->input('type'), function ($query, $type) {
                $query->where('type', $type);
            })
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $searchTerm = '%'.strtolower($search).'%';
                    $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm]);
                });
            })
            ->with('owner:id,name,avatar')
            ->with(['eventMappings' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->with(['externalEventMappings' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->withCount('forks')
            ->withExists('kits')
            ->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))
            ->paginate(12);

        return Inertia::render('templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['filter', 'search', 'type', 'sort', 'direction']),
        ]);
    }

    /**
     * Show the form for creating a new template
     */
    public function create(TemplateDataMapperService $templateDataMapper)
    {
        return Inertia::render('templates/create', [
            'sampleData' => $templateDataMapper->getSampleTemplateData(),
        ]);
    }

    /**
     * Display the specified template
     */
    public function show(OverlayTemplate $template)
    {
        // Check if the user can view this template
        if (! $template->is_public && $template->owner_id !== auth()->id()) {
            abort(403, 'This template is private');
        }

        $template->load(['owner:id,name,avatar', 'forkParent:id,name,slug']);
        $template->loadCount('forks');
        $template->loadExists('kits');

        $canEdit = auth()->id() === $template->owner_id;
        $controls = $canEdit
            ? $template->controls()->orderBy('sort_order')->get()
            : collect();

        $connectedServices = $canEdit
            ? ExternalIntegration::where('user_id', auth()->id())->pluck('service')->toArray()
            : [];

        $targetStaticOverlayIds = ($canEdit && $template->type === 'alert')
            ? $template->targetStaticOverlays()->pluck('overlay_templates.id')->all()
            : [];
        $staticOverlays = ($canEdit && $template->type === 'alert')
            ? OverlayTemplate::where('owner_id', auth()->id())
                ->where('type', 'static')
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get()
            : collect();

        $isLive = $canEdit && StreamSessionService::isLive(auth()->user());

        $userScopedControls = $canEdit
            ? OverlayControl::where('user_id', auth()->id())
                ->whereNull('overlay_template_id')
                ->where('source_managed', true)
                ->orderBy('sort_order')
                ->get()
            : collect();

        return Inertia::render('templates/show', [
            'template' => $template,
            'canEdit' => $canEdit,
            'controls' => $controls,
            'connectedServices' => $connectedServices,
            'isLive' => $isLive,
            'targetStaticOverlayIds' => $targetStaticOverlayIds,
            'staticOverlays' => $staticOverlays,
            'userScopedControls' => $userScopedControls,
        ]);
    }

    /**
     * Show the form for editing the specified template
     */
    public function edit(Request $request, OverlayTemplate $template)
    {
        // Check ownership
        if ($template->owner_id !== auth()->id()) {
            abort(403);
        }

        $template->loadExists('kits');

        $availableTags = [];

        try {
            $availableTagsResponse = $this->templateTagController->getAllTags($request);

            // Extract JSON from the response
            $jsonContent = $availableTagsResponse->getContent();

            // Decode the JSON content into an array
            $decodedResponse = json_decode($jsonContent, true);

            if ($decodedResponse && isset($decodedResponse['success']) && $decodedResponse['success'] === true) {
                $availableTags = $decodedResponse['tags'] ?? [];
            } else {
                Log::error('Failed to fetch available tags', ['response' => $decodedResponse]);
            }
        } catch (Exception $e) {
            Log::error('Failed to fetch available tags', ['error' => $e->getMessage()]);
        }

        $controls = $template->controls()->orderBy('sort_order')->get();

        $connectedServices = ExternalIntegration::where('user_id', auth()->id())->pluck('service')->toArray();

        $targetStaticOverlayIds = $template->type === 'alert'
            ? $template->targetStaticOverlays()->pluck('overlay_templates.id')->all()
            : [];
        $staticOverlays = $template->type === 'alert'
            ? OverlayTemplate::where('owner_id', auth()->id())
                ->where('type', 'static')
                ->select(['id', 'name', 'slug'])
                ->orderBy('name')
                ->get()
            : collect();

        $isLive = StreamSessionService::isLive(auth()->user());

        $userScopedControls = OverlayControl::where('user_id', auth()->id())
            ->whereNull('overlay_template_id')
            ->where('source_managed', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('templates/edit', [
            'template' => $template,
            'availableTags' => $availableTags,
            'controls' => $controls,
            'connectedServices' => $connectedServices,
            'isLive' => $isLive,
            'targetStaticOverlayIds' => $targetStaticOverlayIds,
            'staticOverlays' => $staticOverlays,
            'userScopedControls' => $userScopedControls,
        ]);

    }

    /**
     * Remove the specified template from storage
     */
    public function destroy(Request $request, OverlayTemplate $template)
    {
        // Check ownership
        if ($template->owner_id !== $request->user()->id) {
            abort(403);
        }

        // Prevent deletion if template belongs to a kit
        if ($template->kits()->exists()) {
            $message = 'This template is part of a kit and cannot be deleted. Remove it from all kits first.';

            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['error' => $message], 422);
            }

            return back()->withErrors(['error' => $message]);
        }

        $template->delete();

        // For API/JSON requests
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Template deleted successfully',
            ]);
        }

        return redirect()->route('templates.index')
            ->with('success', 'Template deleted successfully');
    }

    /**
     * Serve public overlay (unparsed)
     */
    public function servePublic(string $slug)
    {
        $template = OverlayTemplate::where('slug', $slug)->firstOrFail();

        // Check if the template is public
        if (! $template->is_public) {
            abort(404, 'This overlay is private');
        }

        $template->recordView();

        // Rewrite src/srcset attributes that contain unresolved [[[tags]]] to data-src
        // so the browser doesn't try to fetch them as relative URLs.
        $html = preg_replace(
            '/\b(src|srcset)(\s*=\s*["\'][^"\']*\[\[\[)/i',
            'data-$1$2',
            $template->html ?? ''
        );

        return view('overlay.render', [
            'head' => $template->head,
            'html' => $html,
            'css' => $template->css,
            'js' => $template->js,
            'isParsed' => false,
            'template' => $template,
        ]);
    }

    public function servePublicScreenshot(string $slug)
    {
        $template = OverlayTemplate::where('slug', $slug)->firstOrFail();

        if (! $template->is_public) {
            abort(404, 'This overlay is private');
        }

        if (! $template->screenshot_url) {
            abort(404, 'No screenshot available');
        }

        return view('overlay.screenshot', [
            'template' => $template,
        ]);
    }

    /**
     * Serve authenticated overlay (parsed with user data)
     */
    public function serveAuthenticated(string $slug)
    {
        // Get token from fragment (handled by JavaScript)
        // The fragment (#token) isn't sent to the server, so we need JavaScript to handle it
        return view('overlay.authenticate', [
            'slug' => $slug,
        ]);
    }

    /**
     * API endpoint to render authenticated overlay
     */
    public function renderAuthenticated(Request $request)
    {
        $validated = $request->validate([
            'slug' => 'required|string',
            'token' => 'required|string|size:64',
        ]);

        // Find and validate token
        $token = OverlayAccessToken::findByToken($validated['token'], $request->ip());

        if (! $token) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        // Find template
        $template = OverlayTemplate::where('slug', $validated['slug'])->firstOrFail();

        // Get user and ensure their Twitch token is still valid
        $user = $token->user;

        $tokenService = app(TwitchTokenService::class);
        if (! $tokenService->ensureValidToken($user)) {
            $user->refresh();
        }

        if (! $user->access_token) {
            return response()->json(['error' => 'User has no Twitch connection.'], 400);
        }

        // Check if the template is public or set to private by the owner
        if (! $template->is_public && $template->owner_id !== $token->user_id) {
            return response()->json(['error' => 'This overlay is private.'], 403);
        }

        try {
            // Get Twitch data (cached snapshot)
            $twitchData = $this->twitchService->getExtendedUserData(
                $user->access_token,
                $user->twitch_id
            );

            // Map and prune via the single source of truth
            $mapped = $this->mapper->mapForTemplate(
                $twitchData,
                $template->name,
                $template->template_tags // allowlist: only tags the template actually uses
            );

            // Record access
            $token->recordAccess(
                $request->ip(),
                $request->userAgent(),
                $template->slug
            );

            // Inject control values: template-scoped + user-scoped (source_managed)
            $controls = OverlayControl::where(function ($q) use ($template, $user) {
                $q->where('overlay_template_id', $template->id)
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('user_id', $user->id)
                            ->whereNull('overlay_template_id')
                            ->where('source_managed', true);
                    });
            })->orderBy('sort_order')->get();

            $controlData = [];
            $timerStates = [];
            $expressionControls = [];
            $randomControls = [];
            foreach ($controls as $control) {
                // Service-managed controls use namespaced broadcast key (e.g. "kofi:kofis_received")
                // matching the [[[c:kofi:kofis_received]]] template tag syntax.
                $dataKey = $control->source_managed
                    ? 'c:'.$control->broadcastKey()
                    : 'c:'.$control->key;
                $controlData[$dataKey] = $control->resolveDisplayValue();
                // Inject companion _at timestamp (Unix epoch seconds)
                $controlData[$dataKey.'_at'] = $control->updated_at
                    ? (string) $control->updated_at->timestamp
                    : (string) $control->created_at->timestamp;
                if ($control->type === 'timer') {
                    $cfg = $control->config ?? [];
                    $timerStates[$control->key] = [
                        'mode' => $cfg['mode'] ?? 'countup',
                        'base_seconds' => (int) ($cfg['base_seconds'] ?? 0),
                        'offset_seconds' => (int) ($cfg['offset_seconds'] ?? 0),
                        'running' => (bool) ($cfg['running'] ?? false),
                        'started_at' => $cfg['started_at'] ?? null,
                        'target_datetime' => $cfg['target_datetime'] ?? null,
                    ];
                }
                if ($control->type === 'expression') {
                    $expressionControls[] = [
                        'key' => $control->broadcastKey(),
                        'expression' => $control->config['expression'] ?? '',
                    ];
                }
                if ($control->isRandom()) {
                    $cfg = $control->config ?? [];
                    $randomControls[] = [
                        'key' => $control->source_managed
                            ? $control->broadcastKey()
                            : $control->key,
                        'min' => (int) ($cfg['min'] ?? 0),
                        'max' => (int) ($cfg['max'] ?? 100),
                        'interval' => max(100, (int) ($cfg['random_interval'] ?? 1000)),
                    ];
                }
            }

            // Build final data: Twitch data + controls + Twitch ID
            $finalData = array_merge($mapped, $controlData, [
                'user_twitch_id' => $user->twitch_id,
            ]);

            // Directly return JSON as a response so frontend can handle rendering
            return response()->json([
                'template' => [
                    'head' => $template->head,
                    'html' => $template->html,
                    'css' => $template->css,
                    'tags' => $template->template_tags,
                ],
                'meta' => [
                    'name' => $template->name,
                    'slug' => $template->slug,
                    'description' => $template->description,
                    'is_public' => $template->is_public,
                    'created_at' => $template->created_at,
                    'updated_at' => $template->updated_at,
                ],
                'data' => $finalData,
                'timer_states' => $timerStates,
                'expression_controls' => $expressionControls,
                'random_controls' => $randomControls,
                'stream_live' => StreamSessionService::isLive($user),
                'locale' => $user->locale ?? 'en-US',
            ]);

        } catch (Exception $e) {
            Log::error('Failed to render authenticated overlay', [
                'error' => $e->getMessage(),
                'template_slug' => $template->slug,
                'user_id' => $user->id,
            ]);

            return response()->json(['error' => 'Failed to render overlay'], 500);
        }
    }

    /**
     * Store new template
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'head' => 'nullable|string',
            'html' => 'present|nullable|string',
            'css' => 'nullable|string',
            'type' => 'required|in:static,alert',
            'is_public' => 'boolean',
        ]);

        $validated = HtmlSanitizationService::sanitizeTemplateFields($validated);

        $template = $request->user()->overlayTemplates()->create($validated);

        // Extract and store template tags
        $template->template_tags = $template->extractTemplateTags();
        $template->save();

        // For Inertia requests, redirect to the show page
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'template' => $template,
                'message' => 'Template created successfully',
            ]);
        }

        return redirect()->route('templates.show', $template)
            ->with('success', 'Template created successfully!');
    }

    /**
     * Update template
     */
    public function update(Request $request, OverlayTemplate $template)
    {
        // Check ownership
        if ($template->owner_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'head' => 'nullable|string',
            'html' => 'sometimes|nullable|string',
            'css' => 'nullable|string',
            'type' => 'sometimes|in:static,alert',
            'is_public' => 'sometimes|boolean',
        ]);

        $validated = HtmlSanitizationService::sanitizeTemplateFields($validated);

        $template->update($validated);

        // Re-extract template tags if content changed
        if (isset($validated['html']) || isset($validated['css']) || isset($validated['js'])) {
            $template->template_tags = $template->extractTemplateTags();
            $template->save();
        }

        // Notify any open overlays to reload
        if ($request->user()->twitch_id) {
            TemplateUpdated::dispatch($template->slug, $request->user()->twitch_id);
        }

        // For Inertia requests, redirect to the show page with a success message
        if ($request->wantsJson()) {
            return response()->json([
                'template' => $template,
                'message' => 'Template updated successfully',
            ]);
        }

        return redirect()->route('templates.edit', $template)
            ->with('success', 'Template updated successfully!');
    }

    /**
     * Update target static overlays for an alert template
     */
    public function updateTargetOverlays(Request $request, OverlayTemplate $template): RedirectResponse
    {
        abort_unless($template->owner_id === auth()->id(), 403);
        abort_unless($template->type === 'alert', 422);

        $validated = $request->validate([
            'overlay_ids' => ['nullable', 'array'],
            'overlay_ids.*' => ['integer', 'exists:overlay_templates,id'],
        ]);

        $ids = $validated['overlay_ids'] ?? [];

        if (! empty($ids)) {
            $validCount = OverlayTemplate::whereIn('id', $ids)
                ->where('owner_id', auth()->id())
                ->where('type', 'static')
                ->count();
            abort_if($validCount !== count($ids), 422, 'Invalid overlay IDs.');
        }

        $template->targetStaticOverlays()->sync($ids);

        return back()->with('message', 'Targeting settings saved.')->with('type', 'success');
    }

    /**
     * Update screenshot URL for a template
     */
    public function updateScreenshot(Request $request, OverlayTemplate $template): RedirectResponse
    {
        abort_unless($template->owner_id === auth()->id(), 403);

        $validated = $request->validate([
            'screenshot_url' => ['nullable', 'url', 'max:2048'],
        ]);

        // Delete old screenshot from Cloudinary if replacing or removing
        $oldUrl = $template->screenshot_url;
        if ($oldUrl && $oldUrl !== $validated['screenshot_url']) {
            $this->deleteCloudinaryAsset($oldUrl);
        }

        $template->update(['screenshot_url' => $validated['screenshot_url']]);

        return back()->with('message', 'Screenshot updated.')->with('type', 'success');
    }

    /**
     * Delete a Cloudinary asset by its URL
     */
    private function deleteCloudinaryAsset(string $url): void
    {
        try {
            // Extract public_id from Cloudinary URL
            // Format: https://res.cloudinary.com/{cloud}/image/upload/v{version}/{public_id}.{ext}
            if (preg_match('#/upload/(?:v\d+/)?(.+)\.\w+$#', $url, $matches)) {
                $publicId = $matches[1];
                $cloudinary = new Cloudinary(config('services.cloudinary.url'));
                $cloudinary->adminApi()->deleteAssets($publicId);
            }
        } catch (Exception $e) {
            Log::warning('Failed to delete Cloudinary asset', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fork a template
     */
    public function fork(Request $request, OverlayTemplate $template)
    {
        // Check if the template is public or set to private by the owner
        if (! $template->is_public && $template->owner_id !== $request->user()->id) {
            abort(403, 'Cannot fork private template');
        }

        $fork = $template->fork($request->user());

        // Check if this is an AJAX request or a regular form submission
        if ($request->wantsJson()) {
            $connectedServices = ExternalIntegration::where('user_id', $request->user()->id)
                ->where('enabled', true)
                ->pluck('service')
                ->toArray();

            return response()->json([
                'template' => $fork,
                'message' => 'Template forked successfully',
                'source_controls' => $fork->_sourceControls,
                'has_controls' => $fork->_hasControls,
                'required_services' => $fork->_requiredServices,
                'connected_services' => $connectedServices,
            ]);
        }

        // For regular form submissions, redirect to templates index
        return redirect()->route('templates.index')
            ->with('success', 'Template forked successfully! The template "'.$fork->name.'" has been added to your templates.');
    }
}
