<?php

namespace App\Http\Controllers;

use App\Models\OverlayHash;
use App\Services\DefaultTemplateProviderService;
use App\Services\OverlayTemplateParserService;
use App\Services\TemplateDataMapperService;
use App\Services\TwitchApiService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OverlayHashController extends Controller
{
    private DefaultTemplateProviderService $defaultTemplateProvider;

    private TemplateDataMapperService $templateDataMapper;

    public function __construct(
        DefaultTemplateProviderService $defaultTemplateProvider,
        TemplateDataMapperService $templateDataMapper
    ) {
        $this->defaultTemplateProvider = $defaultTemplateProvider;
        $this->templateDataMapper = $templateDataMapper;
    }

    /**
     * Display the overlay hash management interface
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $hashes = OverlayHash::forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($hash) {
                return [
                    'id' => $hash->id,
                    'overlay_name' => $hash->overlay_name,
                    'description' => $hash->description,
                    'hash_key' => $hash->hash_key,
                    'is_active' => $hash->is_active,
                    'access_count' => $hash->access_count,
                    'last_accessed_at' => $hash->last_accessed_at?->diffForHumans(),
                    'expires_at' => $hash->expires_at?->format('Y-m-d H:i:s'),
                    'overlay_url' => $hash->getOverlayUrl(),
                    'created_at' => $hash->created_at->format('Y-m-d H:i:s'),
                    'is_valid' => $hash->isValid(),
                ];
            });

        return Inertia::render('overlayhashes/index', [
            'hashes' => $hashes,
        ]);
    }

    /**
     * Create a new overlay hash
     */
    public function store(Request $request)
    {
        $request->validate([
            'overlay_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expires_in_days' => 'nullable|integer|min:1|max:365',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'ip',
        ]);

        $user = $request->user();

        // Calculate expiration if provided
        $expiresAt = null;
        if ($request->expires_in_days) {
            $expiresAt = Carbon::now()->addDays($request->expires_in_days);
        }

        $hash = OverlayHash::createForUser(
            $user->id,
            $request->overlay_name,
            $request->description,
            $expiresAt,
            $request->allowed_ips
        );

        Log::info('Overlay hash created', [
            'user_id' => $user->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash created successfully',
            'hash' => [
                'id' => $hash->id,
                'hash_key' => $hash->hash_key,
                'overlay_url' => $hash->getOverlayUrl(),
                'overlay_name' => $hash->overlay_name,
            ],
        ]);
    }

    /**
     * Revoke (disable) an overlay hash
     */
    public function revoke(Request $request, OverlayHash $hash)
    {
        // Ensure the hash belongs to the authenticated user
        if ($hash->user_id !== $request->user()->id) {
            abort(403, 'You can only revoke your own overlay hashes');
        }

        $hash->revoke();

        Log::info('Overlay hash revoked', [
            'user_id' => $request->user()->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash revoked successfully',
        ]);
    }

    /**
     * Regenerate an overlay hash (create a new hash key)
     */
    public function regenerate(Request $request, OverlayHash $hash)
    {
        // Ensure the hash belongs to the authenticated user
        if ($hash->user_id !== $request->user()->id) {
            abort(403, 'You can only regenerate your own overlay hashes');
        }

        $oldHashKey = $hash->hash_key;
        $newHashKey = $hash->regenerateHash();

        Log::info('Overlay hash regenerated', [
            'user_id' => $request->user()->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
            'old_hash' => substr($oldHashKey, 0, 8).'...',
            'new_hash' => substr($newHashKey, 0, 8).'...',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash regenerated successfully',
            'new_hash_key' => $newHashKey,
            'new_overlay_url' => $hash->getOverlayUrl(),
        ]);
    }

    /**
     * Delete an overlay hash permanently
     */
    public function destroy(Request $request, OverlayHash $hash)
    {
        // Ensure the hash belongs to the authenticated user
        if ($hash->user_id !== $request->user()->id) {
            abort(403, 'You can only delete your own overlay hashes');
        }

        Log::info('Overlay hash deleted', [
            'user_id' => $request->user()->id,
            'hash_id' => $hash->id,
            'overlay_name' => $hash->overlay_name,
        ]);

        $hash->delete();

        return response()->json([
            'success' => true,
            'message' => 'Overlay hash deleted successfully',
        ]);
    }

    /**
     * Serve overlay data in various formats
     */
    public function show(Request $request, string $hashKey)
    {
        try {
            // Find the overlay hash
            $hash = OverlayHash::where('hash_key', $hashKey)
                ->where('is_active', true)
                ->first();

            if (! $hash) {
                return response('Overlay not found', 404);
            }

            // Increment access count
            $hash->increment('access_count');

            // Get fresh Twitch data
            $twitchService = app(TwitchApiService::class);
            $twitchData = $twitchService->getOverlayData($hash->user);

            // Prepare overlay data
            $overlayData = [
                'overlay_name' => $hash->overlay_name,
                'overlay_slug' => $hash->slug,
                'user_name' => $hash->user->name,
                'access_count' => $hash->access_count,
                'timestamp' => now()->toISOString(),
                'data' => $twitchData,
            ];

            // Determine output format
            $format = $this->determineOutputFormat($request);

            // Return different formats based on request
            return match ($format) {
                'json' => $this->returnJsonResponse($overlayData),
                'csv' => $this->returnCsvResponse($overlayData),
                default => $this->returnHtmlOverlay($hash, $overlayData),
            };

        } catch (Exception $e) {
            Log::error('Error serving overlay', [
                'hash_key' => $hashKey,
                'error' => $e->getMessage(),
            ]);

            return response('', 500);
        }
    }

    /**
     * Serve overlay content using hash authentication with slug
     * DUAL-MODE: Returns HTML overlay OR JSON API based on request
     */
    public function serveOverlay(Request $request, string $slug, string $hashKey)
    {
        $clientIp = $request->ip();

        // Find and validate the hash (we still use the hashKey for security)
        $hash = OverlayHash::findValidHash($hashKey, $clientIp);
        Log::info($hash);
        if (! $hash) {
            // Return a completely empty response for invalid hashes (as requested)
            return response('', 404);
        }

        // Optional: Verify the slug matches (for consistency but not required for security)
        if ($hash->slug !== $slug) {
            // You could redirect to the correct URL or just continue - up to you
            // For now, let's log it and continue
            Log::info('Slug mismatch in overlay request', [
                'expected_slug' => $hash->slug,
                'provided_slug' => $slug,
                'hash_id' => $hash->id,
            ]);
        }

        // Get the user who owns this hash
        $user = $hash->user;

        if (! $user || ! $user->access_token) {
            Log::error('Overlay hash has no valid user or access token', [
                'hash_id' => $hash->id,
                'user_id' => $hash->user_id,
            ]);

            return response('', 500);
        }

        try {
            // Get fresh Twitch data for this user
            $twitchApiService = app(\App\Services\TwitchApiService::class);
            $twitchData = $twitchApiService->getExtendedUserData($user->access_token, $user->twitch_id);

            // Determine an output format based on request
            $format = $this->determineOutputFormat($request);

            // Prepare the data
            $overlayData = [
                'overlay_name' => $hash->overlay_name,
                'overlay_slug' => $hash->slug,
                'user_name' => $user->name,
                'access_count' => $hash->access_count,
                'timestamp' => now()->toISOString(),
                'data' => $twitchData, // Full Twitch data for template parsing/API use
            ];

            // Return different formats based on request
            return match ($format) {
                'json' => $this->returnJsonResponse($overlayData),
                'csv' => $this->returnCsvResponse($overlayData),
                default => $this->returnHtmlOverlay($hash, $overlayData),
            };

        } catch (Exception $e) {

            return response('', 500);
        }
    }

    /**
     * Serve overlay content publicly using only slug (no hash authentication)
     * Returns the raw HTML and CSS templates without placeholder replacement
     */
    public function serveOverlayPublic(Request $request, string $slug)
    {
        try {
            // Find the overlay hash by slug (no authentication required)
            $hash = OverlayHash::where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if (! $hash) {
                return response('Overlay not found', 404);
            }

            // Check if hash has expired (still respect expiration for public access)
            if ($hash->expires_at && $hash->expires_at->isPast()) {
                return response('Overlay has expired', 404);
            }

            // Get the raw templates from metadata
            $metadata = $hash->metadata ?? [];
            $htmlTemplate = $metadata['html_template'] ?? $this->defaultTemplateProvider->getDefaultHtml();
            $cssTemplate = $metadata['css_template'] ?? $this->defaultTemplateProvider->getDefaultCss();

            $htmlStripped = str_replace('[[[style]]]', '', $htmlTemplate);

            // Record access (optional - you may want to track public access differently)
            $hash->recordAccess($request->ip());

            // Return the raw HTML document with embedded CSS
            $htmlDocument = $this->buildRawHtmlDocument($htmlStripped, $cssTemplate, $hash);

            return response($htmlDocument)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');

        } catch (Exception $e) {
            Log::error('Error serving public overlay', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response('Internal Server Error', 500);
        }
    }

    /**
     * Build the raw HTML document with CSS embedded
     */
    private function buildRawHtmlDocument(string $htmlTemplate, string $cssTemplate, OverlayHash $hash): string
    {
        return "<!DOCTYPE html>
<html lang=\"en\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>{$hash->overlay_name} - Public Preview</title>
    <style>
        {$cssTemplate}
    </style>
</head>
<body>
    {$htmlTemplate}
</body>
</html>";
    }

    /**
     * Determine output format from request
     */
    private function determineOutputFormat(Request $request): string
    {
        // Check query parameter first (?format=json)
        if ($request->has('format')) {
            $format = strtolower($request->get('format'));
            if (in_array($format, ['json', 'html', 'csv'])) {
                return $format;
            }
        }

        // Check Accept header for content negotiation
        $accept = $request->header('Accept', '');

        if (str_contains($accept, 'application/json')) {
            return 'json';
        }

        if (str_contains($accept, 'text/csv')) {
            return 'csv';
        }

        // Default to HTML for browsers/OBS
        return 'html';
    }

    /**
     * Return JSON API response
     */
    private function returnJsonResponse(array $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'overlay' => [
                'name' => $data['overlay_name'],
                'slug' => $data['overlay_slug'],
                'user' => $data['user_name'],
                'access_count' => $data['access_count'],
                'last_updated' => $data['timestamp'],
            ],
            'twitch' => $data['data'],
            'api_info' => [
                'format' => 'json',
                'documentation' => 'Add ?format=html for overlay, ?format=csv for CSV export',
                'rate_limit' => 'No rate limit - data is always fresh from Twitch API',
                'authentication' => 'Hash-based - no API keys required',
            ],
        ]);
    }

    /**
     * Return CSV response (flattened data)
     */
    private function returnCsvResponse(array $data): Response
    {
        $flattened = $this->flattenArray($data['data']);

        // Add overlay metadata
        $flattened['overlay_name'] = $data['overlay_name'];
        $flattened['overlay_slug'] = $data['overlay_slug'];
        $flattened['user_name'] = $data['user_name'];
        $flattened['access_count'] = $data['access_count'];
        $flattened['timestamp'] = $data['timestamp'];

        // Create CSV
        $csv = [];
        $csv[] = array_keys($flattened); // Headers
        $csv[] = array_values($flattened); // Data

        $output = '';
        foreach ($csv as $row) {
            $output .= implode(',', array_map(function ($field) {
                return '"'.str_replace('"', '""', $field).'"';
            }, $row))."\n";
        }

        return response($output, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="overlay-data.csv"');
    }

    /**
     * Return HTML overlay with template parsing!
     * Now uses the centralized DefaultTemplateProviderService
     */
    private function returnHtmlOverlay(OverlayHash $hash, array $data): Response
    {
        // Check if the hash has a stored HTML template
        $htmlTemplate = $hash->metadata['html_template'] ?? null;
        $cssTemplate = $hash->metadata['css_template'] ?? null;

        if (! $htmlTemplate) {
            // No custom template - return default overlay using the service
            return $this->returnDefaultHtmlOverlay($hash, $data);
        }

        // Transform Twitch data into a template-friendly structure
        $templateData = $this->templateDataMapper->mapTwitchDataForTemplates($data['data'], $hash->overlay_name);

        // Parse the template with [[[template_tags]]]
        $templateParserService = app(OverlayTemplateParserService::class);
        $parsedHtml = $templateParserService->parseTemplate($htmlTemplate, $templateData);
        $parsedCss = $cssTemplate ? $templateParserService->parseTemplate($cssTemplate, $templateData) : '';

        // Build a complete HTML document
        $fullHtml = $this->buildCompleteHtmlDocument($parsedHtml, $parsedCss, $hash);

        return response($fullHtml, 200)
            ->header('Content-Type', 'text/html')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function returnEmptyHtmlOverlay(string $slug, array $data) {}

    /**
     * Build a complete HTML document with a parsed template
     */
    private function buildCompleteHtmlDocument(string $parsedHtml, string $parsedCss, OverlayHash $hash): string
    {
        return $this->templateDataMapper->wrapHtmlAndCssIntoDocument(
            $parsedHtml,
            $parsedCss,
            $hash->overlay_name
        );
    }

    /**
     * Return the default HTML overlay using DefaultTemplateProviderService
     * Now also uses TemplateDataMapperService for a consistent data structure
     */
    private function returnDefaultHtmlOverlay(OverlayHash $hash, array $data): Response
    {
        // Transform Twitch data into a template-friendly structure
        $templateData = $this->templateDataMapper->mapTwitchDataForTemplates($data['data'], $hash->overlay_name);

        // Get complete HTML with CSS injected and data substituted
        $html = $this->defaultTemplateProvider->getCompleteDefaultHtml($templateData);

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Helper: Flatten nested array for CSV
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix.'.'.$key : $key;

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenArray($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }

        return $result;
    }

    /**
     * Test endpoint to verify hash authentication (for debugging)
     */
    public function testHash(Request $request, string $slug, string $hashKey)
    {
        $clientIp = $request->ip();
        $hash = OverlayHash::findValidHash($hashKey, $clientIp);

        if (! $hash) {
            return response()->json(['valid' => false, 'message' => 'Invalid or expired hash']);
        }

        return response()->json([
            'valid' => true,
            'overlay_name' => $hash->overlay_name,
            'overlay_slug' => $hash->slug,
            'shareable_url' => $hash->getShareableUrl(),
            'access_count' => $hash->access_count,
            'last_accessed' => $hash->last_accessed_at,
        ]);
    }
}
