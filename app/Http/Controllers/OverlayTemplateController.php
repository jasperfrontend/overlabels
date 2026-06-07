<?php

namespace App\Http\Controllers;

use App\Events\TemplateUpdated;
use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OptionSet;
use App\Models\OverlayAccessToken;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\UserFreesoundSound;
use App\Services\CloudinaryUploadService;
use App\Services\HtmlSanitizationService;
use App\Services\StreamSessionService;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Services\TwitchTokenService;
use App\Support\ListItems;
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

        // User's lists, surfaced so the ControlFormModal's list_writer picker
        // has something to choose from. Light projection (just the columns
        // the picker renders) plus the items count rather than the full
        // array - avoids shipping potentially large list contents that
        // aren't needed for picking.
        $userLists = $canEdit
            ? OptionSet::where('user_id', auth()->id())
                ->orderBy('slug')
                ->get(['id', 'slug', 'label', 'items', 'disabled_at'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'slug' => $l->slug,
                    'label' => $l->label,
                    'items_count' => count($l->items ?? []),
                    'disabled' => $l->disabled_at !== null,
                ])
            : collect();

        $triggers = ($canEdit && $template->type === 'alert')
            ? $this->buildTriggerData($template)
            : null;

        return Inertia::render('templates/show', [
            'template' => $template,
            'canEdit' => $canEdit,
            'controls' => $controls,
            'connectedServices' => $connectedServices,
            'isLive' => $isLive,
            'targetStaticOverlayIds' => $targetStaticOverlayIds,
            'staticOverlays' => $staticOverlays,
            'userScopedControls' => $userScopedControls,
            'userLists' => $userLists,
            'triggers' => $triggers,
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

        // Same shape as in show() - the modal's list_writer picker needs
        // these to populate the target dropdown.
        $userLists = OptionSet::where('user_id', auth()->id())
            ->orderBy('slug')
            ->get(['id', 'slug', 'label', 'items', 'disabled_at'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'slug' => $l->slug,
                'label' => $l->label,
                'items_count' => count($l->items ?? []),
                'disabled' => $l->disabled_at !== null,
            ]);

        $triggers = $template->type === 'alert'
            ? $this->buildTriggerData($template)
            : null;

        // Sound library for the Sound tab on alert templates. Hotlink-only -
        // these rows are metadata pointing at Freesound's CDN, not stored audio.
        $freesoundLibrary = $template->type === 'alert'
            ? UserFreesoundSound::where('user_id', auth()->id())
                ->orderBy('name')
                ->get()
            : collect();

        return Inertia::render('templates/edit', [
            'template' => $template,
            'availableTags' => $availableTags,
            'controls' => $controls,
            'connectedServices' => $connectedServices,
            'isLive' => $isLive,
            'targetStaticOverlayIds' => $targetStaticOverlayIds,
            'staticOverlays' => $staticOverlays,
            'userScopedControls' => $userScopedControls,
            'userLists' => $userLists,
            'triggers' => $triggers,
            'freesoundLibrary' => $freesoundLibrary,
        ]);

    }

    /**
     * Remove the specified template from storage
     */
    public function destroy(Request $request, OverlayTemplate $template, CloudinaryUploadService $cloudinary)
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

        $screenshotUrl = $template->screenshot_url;

        $template->delete();

        // Delete the Cloudinary asset only if no other template/kit still
        // references it (forks share screenshot_url via replicate()).
        $cloudinary->deleteByUrl($screenshotUrl);

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
     * Serve the public overlay preview page: 2-column screenshot + raw source viewer.
     *
     * Shares an `$og` payload into the root blade view so social-media scrapers
     * (which don't execute JS and never see Inertia's client-side Head) get
     * per-overlay OpenGraph + Twitter card metadata in the initial HTML.
     *
     * Screenshots are served with the "Powered by Overlabels" watermark
     * applied via Cloudinary URL transformation - the watermark lives in the
     * delivery layer only, so the owner's edit screen still sees their raw
     * upload.
     */
    public function servePublic(string $slug, CloudinaryUploadService $cloudinary)
    {
        $template = OverlayTemplate::where('slug', $slug)
            ->with('owner:id,name,avatar')
            ->firstOrFail();

        if (! $template->is_public) {
            abort(404, 'This overlay is private');
        }

        $template->recordView();

        $ownerName = $template->owner?->name ?: 'an Overlabels user';
        $typeLabel = $template->type === 'alert' ? 'event alert' : 'overlay';

        $description = $template->description
            ?: "A Twitch {$typeLabel} by {$ownerName} on Overlabels. View the source, copy it to your account, and customise it for your stream.";

        $fallbackImage = 'https://res.cloudinary.com/dy185omzf/image/upload/v1771771091/ogimage_fepcyf.jpg';
        $hasScreenshot = ! empty($template->screenshot_url);
        $brandedScreenshot = $hasScreenshot ? $cloudinary->brandedUrl($template->screenshot_url) : null;
        $image = $brandedScreenshot ?? $fallbackImage;

        view()->share('og', [
            'title' => "{$template->name} - Overlabels {$typeLabel} by {$ownerName}",
            'description' => $description,
            'url' => route('overlay.public', $template->slug),
            'image' => $image,
            'image_alt' => $hasScreenshot
                ? "Screenshot of {$template->name}, an Overlabels {$typeLabel} by {$ownerName}"
                : 'Overlabels - reactive Twitch overlays for people who code',
            'twitter_card' => 'summary_large_image',
        ]);

        return Inertia::render('overlay/public-preview', [
            'template' => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'description' => $template->description,
                'type' => $template->type,
                'head' => $template->head,
                'html' => $template->html,
                'css' => $template->css,
                'screenshot_url' => $brandedScreenshot,
                'view_count' => $template->view_count,
                'fork_count' => $template->fork_count,
                'created_at' => $template->created_at,
                'owner' => $template->owner ? [
                    'name' => $template->owner->name,
                    'avatar' => $template->owner->avatar,
                ] : null,
            ],
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
            $expressionsByKey = [];
            $randomControls = [];
            foreach ($controls as $control) {
                // Service-managed controls use namespaced broadcast key (e.g. "kofi:donations_received")
                // matching the [[[c:kofi:donations_received]]] template tag syntax.
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
                    $expressionsByKey[$control->broadcastKey()] = [
                        'expression' => $control->config['expression'] ?? '',
                        'dependencies' => array_values((array) ($control->config['dependencies'] ?? [])),
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

            // Inject user-owned Lists (OptionSets) into the data store. Each
            // list ships as both a JSON-encoded full array (so a template
            // can render the whole list directly or have inline JS parse
            // it) AND as flat indexed scalar keys + .count, which is what
            // the existing foreach machinery materialises against. So
            // [[[c:list:donors]]] gives the array string, while
            // [[[foreach:c:list:donors as donor]]][[[donor]]][[[endforeach]]]
            // iterates and renders each item. Plus derived read tags
            // (:first, :last, :empty, :random, :sum) for template-side
            // convenience without needing a foreach.
            $userLists = OptionSet::where('user_id', $user->id)->get();
            $listData = [];
            foreach ($userLists as $list) {
                // Items are objects ({id,value,...}); every scalar list tag
                // (.N, :first, :last, :random, :sum, the bare JSON-array
                // tag) projects to the value string for backward
                // compatibility, so existing templates and
                // [[[foreach:c:list:slug as item]]] keep iterating scalar
                // values. The full item objects are exposed separately under
                // the colon-namespaced :json tag (invisible to the foreach
                // index synthesiser), which is the rail a richer consumer
                // (a custom wheel/leaderboard) reads label/weight/color off.
                // Per-index rich field access (.N.label etc.) needs a
                // foreach-resolver rework and is a later slice.
                $items = ListItems::values($list->items ?? []);
                $baseKey = 'c:list:'.$list->slug;
                $count = count($items);

                $listData[$baseKey] = json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $listData[$baseKey.':json'] = json_encode(array_values($list->items ?? []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $listData[$baseKey.'.count'] = (string) $count;
                $listData[$baseKey.':count'] = (string) $count;
                foreach ($items as $i => $item) {
                    $listData[$baseKey.'.'.$i] = (string) $item;
                }

                // Derived read tags. Empty list -> empty string for value
                // tags, "1" for :empty boolean, "0" for :sum.
                $listData[$baseKey.':first'] = $count > 0 ? (string) $items[0] : '';
                $listData[$baseKey.':last'] = $count > 0 ? (string) $items[$count - 1] : '';
                $listData[$baseKey.':empty'] = $count === 0 ? '1' : '0';
                $listData[$baseKey.':random'] = $count > 0 ? (string) $items[array_rand($items)] : '';
                $listData[$baseKey.':sum'] = $this->sumListItems($list->slug, $items);

                // Expiry-related tags: static Unix timestamp + a synthetic
                // timer state the renderer ticks via the same RAF machinery
                // it uses for [type=timer] controls. The countdown key
                // resolves to seconds-until-expiry (clamped >= 0), so a
                // template can render [[[c:list:slug:countdown|duration:mm:ss]]]
                // and get a live ticker without any per-list timer control.
                $listData[$baseKey.':expires_at'] = $list->expires_at
                    ? (string) $list->expires_at->timestamp
                    : '';

                if ($list->expires_at !== null) {
                    $timerStates['list:'.$list->slug.':countdown'] = [
                        'mode' => 'countto',
                        'base_seconds' => 0,
                        'offset_seconds' => 0,
                        'running' => true,
                        'started_at' => null,
                        // Renderer parses with new Date(...) - ISO 8601 is
                        // the safe interchange format.
                        'target_datetime' => $list->expires_at->toIso8601String(),
                    ];
                }
            }

            // Filter expression controls: only ship those whose c:<broadcastKey>
            // is referenced in template_tags, plus any they transitively depend
            // on. An unreferenced expression with `now_ms()` still ticks the RAF
            // loop on every frame and cascades through every other watcher
            // subscribed to the data ref, so leaving them in the payload costs
            // O(N^2) re-evaluations per frame for a template that doesn't even
            // use them. Note: alert templates that reference c:<key> are not
            // considered here - those tags live on the alert template, not the
            // static one.
            $referencedControlKeys = [];
            foreach ((array) ($template->template_tags ?? []) as $tag) {
                if (is_string($tag) && str_starts_with($tag, 'c:')) {
                    $referencedControlKeys[substr($tag, 2)] = true;
                }
            }

            $registeredExpressionKeys = [];
            $queue = array_keys($referencedControlKeys);
            while (! empty($queue)) {
                $key = array_shift($queue);
                if (isset($registeredExpressionKeys[$key])) {
                    continue;
                }
                if (! isset($expressionsByKey[$key])) {
                    continue;
                }
                $registeredExpressionKeys[$key] = true;
                foreach ($expressionsByKey[$key]['dependencies'] as $dep) {
                    if (is_string($dep) && ! isset($registeredExpressionKeys[$dep])) {
                        $queue[] = $dep;
                    }
                }
            }

            $expressionControls = [];
            foreach (array_keys($registeredExpressionKeys) as $key) {
                $expressionControls[] = [
                    'key' => $key,
                    'expression' => $expressionsByKey[$key]['expression'],
                ];
            }

            // Expand the template-tag allowlist with any `t.<name>` references
            // that appear in expression controls but not in the template HTML.
            // Without this, an expression like `t.twitch.followers_total + t.twitch.subscribers_total`
            // would resolve empty unless the same tags were also written as `[[[...]]]`
            // somewhere in the HTML.
            $allowlist = (array) ($template->template_tags ?? []);
            foreach ($expressionControls as $exprCtrl) {
                foreach (OverlayControl::extractTwitchTagReferences((string) ($exprCtrl['expression'] ?? '')) as $tagName) {
                    $allowlist[] = $tagName;
                }
            }
            $allowlist = array_values(array_unique($allowlist));

            // Map and prune via the single source of truth
            $mapped = $this->mapper->mapForTemplate(
                $twitchData,
                $template->name,
                $allowlist,
                null,
                $user->foreachCaps()
            );

            // Build final data: Twitch data + controls + lists + Twitch ID
            $finalData = array_merge($mapped, $controlData, $listData, [
                'user_twitch_id' => $user->twitch_id,
            ]);

            // Preload compiled_css for every alert template owned by this user that
            // could fire on this static overlay (no targeting = fires everywhere, or
            // explicitly targets this overlay). Shipped once on overlay mount so the
            // per-alert Reverb payload stays slim - AlertTriggered carries only a slug
            // reference and this map resolves it client-side.
            // alert_sound_url for the same set is shipped alongside so the renderer
            // can emit <link rel="preload" as="audio"> tags on mount, shaving ~1s
            // off first-play latency vs. instantiating Audio() lazily on dispatch.
            $alertCssPreload = [];
            $alertSoundPreload = [];
            if ($template->type === 'static') {
                $alertTemplates = OverlayTemplate::query()
                    ->select(['id', 'slug', 'compiled_css', 'alert_sound_url'])
                    ->where('owner_id', $user->id)
                    ->where('type', 'alert')
                    ->with(['targetStaticOverlays:id'])
                    ->get();

                foreach ($alertTemplates as $alertTemplate) {
                    $targetIds = $alertTemplate->targetStaticOverlays->pluck('id')->all();
                    $firesHere = empty($targetIds) || in_array($template->id, $targetIds, true);
                    if (! $firesHere) {
                        continue;
                    }
                    if (! empty($alertTemplate->compiled_css)) {
                        $alertCssPreload[$alertTemplate->slug] = $alertTemplate->compiled_css;
                    }
                    if (! empty($alertTemplate->alert_sound_url)) {
                        $alertSoundPreload[$alertTemplate->slug] = $alertTemplate->alert_sound_url;
                    }
                }
            }

            // Directly return JSON as a response so frontend can handle rendering
            return response()->json([
                'template' => [
                    'head' => $template->head,
                    'html' => $template->html,
                    'css' => $template->css,
                    'compiled_css' => $template->compiled_css,
                    'tags' => $template->template_tags,
                ],
                'alert_css_preload' => $alertCssPreload,
                'alert_sound_preload' => $alertSoundPreload,
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
     * Sum the numeric items in a List for the [[[c:list:slug:sum]]] tag.
     * Whitespace-only / empty items are skipped (treated as 0, since
     * empties are common as placeholders and aren't really mistakes).
     * Any other non-numeric content fails loudly with an inline error
     * string identifying the list slug + offending value + position.
     * Streamers see the error directly in their overlay and can find +
     * fix the broken row.
     *
     * @param  array<int, string>  $items
     */
    private function sumListItems(string $slug, array $items): string
    {
        $total = 0.0;
        $sawNumber = false;

        foreach ($items as $i => $raw) {
            $trimmed = trim((string) $raw);
            if ($trimmed === '') {
                continue;
            }
            if (! is_numeric($trimmed)) {
                return "ERR: list '{$slug}' has non-numeric item '{$raw}' at position {$i}";
            }
            $total += (float) $trimmed;
            $sawNumber = true;
        }

        // Avoid scientific notation; let the streamer's |number pipe
        // format if they want commas. Integer-valued sums render
        // without trailing zeros.
        if (! $sawNumber) {
            return '0';
        }

        return $total == (int) $total
            ? (string) (int) $total
            : (string) $total;
    }

    /**
     * Store new template
     */
    public function store(Request $request, CloudinaryUploadService $cloudinary)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'head' => 'nullable|string',
            'html' => 'present|nullable|string',
            'css' => 'nullable|string',
            'compiled_css' => 'nullable|string',
            'type' => 'required|in:static,alert',
            'is_public' => 'boolean',
            'screenshot_url' => 'nullable|url|max:2048',
            'tts_expression' => 'nullable|string|max:2000',
            'bot_message_expression' => 'nullable|string|max:500',
            'tts_delay_ms' => 'nullable|integer|min:0|max:60000',
            'alert_sound_url' => 'nullable|url|max:2048',
        ]);

        $validated = HtmlSanitizationService::sanitizeTemplateFields($validated);

        $template = $request->user()->overlayTemplates()->create($validated);

        // Extract and store template tags
        $template->template_tags = $template->extractTemplateTags($request->user()->foreachCaps());
        $template->save();

        if (! empty($validated['screenshot_url'])) {
            $cloudinary->claim($validated['screenshot_url']);
        }

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
            'compiled_css' => 'nullable|string',
            'type' => 'sometimes|in:static,alert',
            'is_public' => 'sometimes|boolean',
            'tts_expression' => 'sometimes|nullable|string|max:2000',
            'bot_message_expression' => 'sometimes|nullable|string|max:500',
            'tts_delay_ms' => 'sometimes|nullable|integer|min:0|max:60000',
            'alert_sound_url' => 'sometimes|nullable|url|max:2048',
        ]);

        $validated = HtmlSanitizationService::sanitizeTemplateFields($validated);

        $template->update($validated);

        // Re-extract template tags if content changed
        if (isset($validated['html']) || isset($validated['css']) || isset($validated['js'])) {
            $template->template_tags = $template->extractTemplateTags($request->user()->foreachCaps());
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
     * Build the trigger preload payload for an alert template:
     *   - eventTypes: full Twitch event-type catalogue (display labels)
     *   - externalEventTypes: per-service external event-type catalogue
     *   - connectedServices: the services this user has enabled integrations for
     *   - assigned: { twitch: [{event_type, duration_ms, enabled}], external: [{service, event_type, duration_ms, enabled}] }
     *
     * "assigned" only contains rows currently bound to THIS template - other
     * templates' mappings stay invisible to this UI.
     */
    private function buildTriggerData(OverlayTemplate $template): array
    {
        $userId = $template->owner_id;

        $assignedTwitch = EventTemplateMapping::where('user_id', $userId)
            ->where('template_id', $template->id)
            ->get(['event_type', 'duration_ms', 'enabled'])
            ->map(fn (EventTemplateMapping $m) => [
                'event_type' => $m->event_type,
                'duration_ms' => $m->duration_ms,
                'enabled' => (bool) $m->enabled,
            ])
            ->values();

        $assignedExternal = ExternalEventTemplateMapping::where('user_id', $userId)
            ->where('overlay_template_id', $template->id)
            ->get(['service', 'event_type', 'duration_ms', 'enabled'])
            ->map(fn (ExternalEventTemplateMapping $m) => [
                'service' => $m->service,
                'event_type' => $m->event_type,
                'duration_ms' => $m->duration_ms,
                'enabled' => (bool) $m->enabled,
            ])
            ->values();

        $connectedServices = ExternalIntegration::where('user_id', $userId)
            ->where('enabled', true)
            ->pluck('service')
            ->toArray();

        return [
            'eventTypes' => EventTemplateMapping::EVENT_TYPES,
            'externalEventTypes' => ExternalEventTemplateMapping::SERVICE_EVENT_TYPES,
            'connectedServices' => $connectedServices,
            'assigned' => [
                'twitch' => $assignedTwitch,
                'external' => $assignedExternal,
            ],
        ];
    }

    /**
     * Replace this alert template's trigger bindings.
     *
     * The UI is per-template: only rows where template_id = $template->id
     * (or overlay_template_id = $template->id for external) are owned by this
     * editor. Anything not present in the request body for THIS template is
     * deleted; rows owned by other templates are left untouched. Reassigning a
     * Twitch event to this template overrides the previous (user, event_type)
     * row because of the unique index - that is intentional.
     */
    public function updateTriggers(Request $request, OverlayTemplate $template): RedirectResponse
    {
        abort_unless($template->owner_id === auth()->id(), 403);
        abort_unless($template->type === 'alert', 422);

        $validated = $request->validate([
            'twitch' => ['nullable', 'array'],
            'twitch.*.event_type' => ['required', 'string', 'in:'.implode(',', array_keys(EventTemplateMapping::EVENT_TYPES))],
            'twitch.*.duration_ms' => ['required', 'integer', 'min:1000', 'max:999000'],
            'twitch.*.enabled' => ['required', 'boolean'],
            'external' => ['nullable', 'array'],
            'external.*.service' => ['required', 'string', 'in:'.implode(',', array_keys(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES))],
            'external.*.event_type' => ['required', 'string'],
            'external.*.duration_ms' => ['required', 'integer', 'min:1000', 'max:999000'],
            'external.*.enabled' => ['required', 'boolean'],
        ]);

        $userId = auth()->id();
        $twitch = $validated['twitch'] ?? [];
        $external = $validated['external'] ?? [];

        foreach ($external as $row) {
            $service = $row['service'];
            $validEventTypes = array_keys(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service] ?? []);
            abort_if(
                ! in_array($row['event_type'], $validEventTypes, true),
                422,
                "Invalid event type '{$row['event_type']}' for service '{$service}'"
            );
        }

        $keptTwitchEventTypes = array_map(fn ($row) => $row['event_type'], $twitch);
        EventTemplateMapping::where('user_id', $userId)
            ->where('template_id', $template->id)
            ->when(! empty($keptTwitchEventTypes), fn ($q) => $q->whereNotIn('event_type', $keptTwitchEventTypes))
            ->delete();

        foreach ($twitch as $row) {
            EventTemplateMapping::updateOrCreate(
                [
                    'user_id' => $userId,
                    'event_type' => $row['event_type'],
                ],
                [
                    'template_id' => $template->id,
                    'duration_ms' => $row['duration_ms'],
                    'enabled' => $row['enabled'],
                ]
            );
        }

        $keptExternalKeys = array_map(fn ($row) => $row['service'].':'.$row['event_type'], $external);
        ExternalEventTemplateMapping::where('user_id', $userId)
            ->where('overlay_template_id', $template->id)
            ->get()
            ->each(function (ExternalEventTemplateMapping $m) use ($keptExternalKeys) {
                if (! in_array($m->service.':'.$m->event_type, $keptExternalKeys, true)) {
                    $m->delete();
                }
            });

        foreach ($external as $row) {
            ExternalEventTemplateMapping::updateOrCreate(
                [
                    'user_id' => $userId,
                    'service' => $row['service'],
                    'event_type' => $row['event_type'],
                ],
                [
                    'overlay_template_id' => $template->id,
                    'duration_ms' => $row['duration_ms'],
                    'enabled' => $row['enabled'],
                ]
            );
        }

        return back()->with('message', 'Triggers saved.')->with('type', 'success');
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
    public function updateScreenshot(Request $request, OverlayTemplate $template, CloudinaryUploadService $cloudinary): RedirectResponse
    {
        abort_unless($template->owner_id === auth()->id(), 403);

        $validated = $request->validate([
            'screenshot_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $oldUrl = $template->screenshot_url;
        $newUrl = $validated['screenshot_url'];

        if ($oldUrl !== $newUrl) {
            // Delete the previous asset (guarded against forks that share the URL).
            $cloudinary->deleteByUrl($oldUrl, excludeTemplateId: $template->id);
        }

        $template->update(['screenshot_url' => $newUrl]);

        // Mark the new upload as claimed so the orphan sweeper leaves it alone.
        $cloudinary->claim($newUrl);

        return back()->with('message', 'Screenshot updated.')->with('type', 'success');
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

        $connectedServices = ExternalIntegration::where('user_id', $request->user()->id)
            ->where('enabled', true)
            ->pluck('service')
            ->toArray();

        $wizardPayload = [
            'template' => $fork,
            'source_controls' => $fork->_sourceControls,
            'has_controls' => $fork->_hasControls,
            'required_services' => $fork->_requiredServices,
            'connected_services' => $connectedServices,
        ];

        // Check if this is an AJAX request or a regular form submission
        if ($request->wantsJson()) {
            return response()->json([
                ...$wizardPayload,
                'message' => 'Template forked successfully',
            ]);
        }

        // Non-AJAX paths (Inertia router.post, blade form on overlay preview, etc.)
        // can't open the wizard inline, so flash the wizard payload onto the
        // session and redirect to the new template - the show page reads the
        // flash and triggers the wizard there. Without this, controls on the
        // forked template would never be imported through these entry points.
        return redirect()->route('templates.show', $fork)
            ->with('fork_wizard', $wizardPayload)
            ->with('success', 'Template forked successfully! The template "'.$fork->name.'" has been added to your templates.');
    }
}
