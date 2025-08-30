<?php

namespace App\Http\Controllers;

use App\Models\OverlayTemplate;
use App\Models\OverlayAccessToken;
use App\Services\TwitchApiService;
use App\Services\TwitchEventSubService;
use App\Services\TemplateDataMapperService;
use Exception;
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
                    $searchTerm = '%' . strtolower($search) . '%';
                    $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm]);
                });
            })
            ->with('owner:id,name,avatar')
            ->with(['eventMappings' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->withCount('forks')

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
    public function create()
    {
        return Inertia::render('templates/create');
    }

    /**
     * Display the specified template
     */
    public function show(OverlayTemplate $template)
    {
        // Check if the user can view this template
        if (!$template->is_public && $template->owner_id !== auth()->id()) {
            abort(403, 'This template is private');
        }

        $template->load(['owner:id,name,avatar', 'forkParent:id,name,slug']);
        $template->loadCount('forks');

        return Inertia::render('templates/show', [
            'template' => $template,
            'canEdit' => auth()->id() === $template->owner_id,
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



        return Inertia::render('templates/edit', [
            'template' => $template,
            'availableTags' => $availableTags,
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

        $template->delete();

        // For API/JSON requests
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
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
        if (!$template->is_public) {
            abort(404, 'This overlay is private');
        }

        $template->recordView();

        return view('overlay.render', [
            'head' => $template->head,
            'html' => $template->html,
            'css' => $template->css,
            'js' => $template->js,
            'isParsed' => false,
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

        if (!$token) {
            return response()->json(['error' => 'Invalid token.'], 401);
        }

        // Find template
        $template = OverlayTemplate::where('slug', $validated['slug'])->firstOrFail();

        // Get user and their Twitch data
        $user = $token->user;

        if (!$user->access_token) {
            return response()->json(['error' => 'User has no Twitch connection.'], 400);
        }

        // Check if the template is public or set to private by the owner
        if(!$template->is_public && $template->owner_id !== $token->user_id){
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

            // NEW: directly return JSON as a response and don't pass the parsed HTML at all
            return response()->json([
                'template' => [
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
                'data' => array_merge($mapped, [
                    'user_twitch_id' => $user->twitch_id, // Add user's Twitch ID for alert channels
                ]),
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
            'html' => 'required|string',
            'css' => 'nullable|string',
            'type' => 'required|in:static,alert',
            'is_public' => 'boolean',
        ]);

        $template = $request->user()->overlayTemplates()->create($validated);

        // Extract and store template tags
        $template->template_tags = $template->extractTemplateTags();
        $template->save();

        // For Inertia requests, redirect to the show page
        if ($request->wantsJson() && !$request->header('X-Inertia')) {
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
            'html' => 'sometimes|string',
            'css' => 'nullable|string',
            'type' => 'sometimes|in:static,alert',
            'is_public' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        // Re-extract template tags if content changed
        if (isset($validated['html']) || isset($validated['css']) || isset($validated['js'])) {
            $template->template_tags = $template->extractTemplateTags();
            $template->save();
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
     * Fork a template
     */
    public function fork(Request $request, OverlayTemplate $template)
    {
        // Check if the template is public or set to private by the owner
        if (!$template->is_public && $template->owner_id !== $request->user()->id) {
            abort(403, 'Cannot fork private template');
        }

        $fork = $template->fork($request->user());

        // Check if this is an AJAX request or a regular form submission
        if ($request->wantsJson()) {
            return response()->json([
                'template' => $fork,
                'message' => 'Template forked successfully',
            ]);
        }

        // For regular form submissions, redirect to templates index
        return redirect()->route('templates.index')
            ->with('success', 'Template forked successfully! The template "' . $fork->name . '" has been added to your templates.');
    }
}
