<?php

namespace App\Http\Controllers;

use App\Models\OverlayTemplate;
use App\Models\OverlayAccessToken;
use App\Services\OverlayTemplateParserService;
use App\Services\TwitchApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OverlayTemplateController extends Controller
{
    protected OverlayTemplateParserService $parserService;
    protected TwitchApiService $twitchService;

    public function __construct(
        OverlayTemplateParserService $parserService,
        TwitchApiService $twitchService
    ) {
        $this->parserService = $parserService;
        $this->twitchService = $twitchService;
    }

    /**
     * Display a listing of templates
     */
    public function index(Request $request)
    {
        $templates = OverlayTemplate::query()
            ->when($request->input('filter') === 'mine', function ($query) use ($request) {
                $query->where('owner_id', $request->user()->id);
            })
            ->when($request->input('filter') === 'public', function ($query) {
                $query->where('is_public', true);
            })
            ->when($request->input('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->with('owner:id,name,avatar')
            ->withCount('forks')
            ->orderBy($request->input('sort', 'created_at'), $request->input('direction', 'desc'))
            ->paginate(12);

        return Inertia::render('templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['filter', 'search', 'sort', 'direction']),
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
        // Check if user can view this template
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
    public function edit(OverlayTemplate $template)
    {
        // Check ownership
        if ($template->owner_id !== auth()->id()) {
            abort(403);
        }

        return Inertia::render('templates/edit', [
            'template' => $template,
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

        return redirect()->route('templates.index')
            ->with('success', 'Template deleted successfully');
    }

    /**
     * Serve public overlay (unparsed)
     */
    public function servePublic(Request $request, string $slug)
    {
        $template = OverlayTemplate::where('slug', $slug)->firstOrFail();

        // Check if template is public
        if (!$template->is_public) {
            abort(404, 'This overlay is private');
        }

        $template->recordView();

        return view('overlay.render', [
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
    public function serveAuthenticated(Request $request, string $slug)
    {
        // Get token from fragment (handled by JavaScript)
        // The fragment (#token) isn't sent to server, so we need JavaScript to handle it
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
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // Find template
        $template = OverlayTemplate::where('slug', $validated['slug'])->firstOrFail();

        // Get user and their Twitch data
        $user = $token->user;

        if (!$user->access_token) {
            return response()->json(['error' => 'User has no Twitch connection'], 400);
        }

        try {
            // Get Twitch data
            $twitchData = $this->twitchService->getExtendedUserData(
                $user->access_token,
                $user->twitch_id
            );

            // Parse template with user data
            $parsedHtml = $this->parserService->parse($template->html, $twitchData);
            $parsedCss = $this->parserService->parse($template->css, $twitchData);


            // Record access
            $token->recordAccess(
                $request->ip(),
                $request->userAgent(),
                $template->slug
            );

            return response()->json([
                'html' => $parsedHtml,
                'css' => $parsedCss,

                'template' => $template,
                'isParsed' => true,
            ]);

        } catch (\Exception $e) {
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
            'html' => 'required|string',
            'css' => 'nullable|string',
            'js' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $template = $request->user()->overlayTemplates()->create($validated);

        // Extract and store template tags
        $template->template_tags = $template->extractTemplateTags();
        $template->save();

        return response()->json([
            'template' => $template,
            'message' => 'Template created successfully',
        ]);
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
            'html' => 'sometimes|string',
            'css' => 'nullable|string',
            'js' => 'nullable|string',
            'is_public' => 'sometimes|boolean',
        ]);

        $template->update($validated);

        // Re-extract template tags if content changed
        if (isset($validated['html']) || isset($validated['css']) || isset($validated['js'])) {
            $template->template_tags = $template->extractTemplateTags();
            $template->save();
        }

        return response()->json([
            'template' => $template,
            'message' => 'Template updated successfully',
        ]);
    }

    /**
     * Fork a template
     */
    public function fork(Request $request, OverlayTemplate $template)
    {
        // Check if template is public or owned by user
        if (!$template->is_public && $template->owner_id !== $request->user()->id) {
            abort(403, 'Cannot fork private template');
        }

        $fork = $template->fork($request->user());

        return response()->json([
            'template' => $fork,
            'message' => 'Template forked successfully',
        ]);
    }
}
