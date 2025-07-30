<?php

namespace App\Http\Controllers;

use App\Models\OverlayHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Carbon\Carbon;

class OverlayHashController extends Controller
{
    /**
     * Display the overlay hash management interface
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $hashes = OverlayHash::forUser($user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($hash) {
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
            ]
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
            'message' => 'Overlay hash revoked successfully'
        ]);
    }

    /**
     * Regenerate an overlay hash (create new hash key)
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
            'old_hash' => substr($oldHashKey, 0, 8) . '...',
            'new_hash' => substr($newHashKey, 0, 8) . '...',
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
            'message' => 'Overlay hash deleted successfully'
        ]);
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
        
        if (!$hash) {
            // Return completely empty response for invalid hashes (as requested)
            return response('', 404);
        }

        // Optional: Verify the slug matches (for consistency, but not required for security)
        if ($hash->slug !== $slug) {
            // You could redirect to correct URL or just continue - up to you
            // For now, let's just log it and continue
            Log::info('Slug mismatch in overlay request', [
                'expected_slug' => $hash->slug,
                'provided_slug' => $slug,
                'hash_id' => $hash->id
            ]);
        }

        // Get the user who owns this hash
        $user = $hash->user;
        
        if (!$user || !$user->access_token) {
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

            // Determine output format based on request
            $format = $this->determineOutputFormat($request);
            
            // Prepare the data
            $overlayData = [
                'overlay_name' => $hash->overlay_name,
                'overlay_slug' => $hash->slug,
                'user_name' => $user->name,
                'access_count' => $hash->access_count,
                'timestamp' => now()->toISOString(),
                'data' => $twitchData // Full Twitch data for template parsing/API use
            ];

            // Return different formats based on request
            switch ($format) {
                case 'json':
                    return $this->returnJsonResponse($overlayData);
                    
                case 'csv':
                    return $this->returnCsvResponse($overlayData);
                    
                case 'html':
                default:
                    // NEW: Template parsing with [[[template_tags]]]
                    return $this->returnHtmlOverlay($hash, $overlayData);
            }

        } catch (\Exception $e) {
            Log::error('Error serving overlay', [
                'hash_id' => $hash->id,
                'error' => $e->getMessage(),
            ]);
            
            return response('', 500);
        }
    }

    /**
     * Determine output format from request
     */
    private function determineOutputFormat(Request $request): string
    {
        // Check query parameter first (?format=json)
        if ($request->has('format')) {
            $format = strtolower($request->get('format'));
            if (in_array($format, ['json', 'html', 'csv'])) { // Removed XML
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
        
        // Default to HTML for browsers/OBS (this fixes your issue!)
        return 'html';
    }

    /**
     * Return JSON API response
     */
    private function returnJsonResponse(array $data): \Illuminate\Http\JsonResponse
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
            'twitch' => $data['data'], // Full Twitch API data
            'api_info' => [
                'format' => 'json',
                'documentation' => 'Add ?format=html for overlay, ?format=csv for CSV export',
                'rate_limit' => 'No rate limit - data is always fresh from Twitch API',
                'authentication' => 'Hash-based - no API keys required'
            ]
        ]);
    }

    /**
     * Return CSV response (flattened data)
     */
    private function returnCsvResponse(array $data): \Illuminate\Http\Response
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
            $output .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        return response($output, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="overlay-data.csv"');
    }

    /**
     * Return HTML overlay with template parsing!
     */
    private function returnHtmlOverlay(OverlayHash $hash, array $data): \Illuminate\Http\Response
    {
        // Check if the hash has a stored HTML template
        $htmlTemplate = $hash->metadata['html_template'] ?? null;
        $cssTemplate = $hash->metadata['css_template'] ?? null;
        
        if (!$htmlTemplate) {
            // No custom template - return a default overlay
            return $this->returnDefaultHtmlOverlay($hash, $data);
        }
        
        // Parse the template with [[[template_tags]]]
        $templateParserService = app(\App\Services\OverlayTemplateParserService::class);
        $parsedHtml = $templateParserService->parseTemplate($htmlTemplate, $data['data']);
        $parsedCss = $cssTemplate ? $templateParserService->parseTemplate($cssTemplate, $data['data']) : '';
        
        // Build complete HTML document
        $fullHtml = $this->buildCompleteHtmlDocument($parsedHtml, $parsedCss, $hash, $data);
        
        return response($fullHtml, 200)
            ->header('Content-Type', 'text/html')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate') // Always fresh data
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Return a beautiful default HTML overlay when no template is set
     */
    private function returnDefaultHtmlOverlay(OverlayHash $hash, array $data): \Illuminate\Http\Response
    {
        $twitch = $data['data'];
        
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($hash->overlay_name) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: transparent;
            color: white;
            overflow: hidden;
        }
        
        .overlay-container {
            padding: 20px;
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.9) 0%, rgba(30, 30, 60, 0.9) 100%);
            border-radius: 15px;
            border: 2px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            margin: 20px;
        }
        
        .overlay-title {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 15px;
            text-align: center;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }
        
        .stat-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stat-label {
            font-weight: 500;
            opacity: 0.8;
        }
        
        .stat-value {
            font-weight: bold;
            color: #FFD700;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }
        
        .timestamp {
            text-align: center;
            font-size: 0.8em;
            opacity: 0.6;
            margin-top: 15px;
        }
        
        .setup-note {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 0.85em;
            text-align: center;
            border-left: 4px solid #FFD700;
        }
    </style>
</head>
<body>
    <div class="overlay-container">
        <div class="overlay-title">' . htmlspecialchars($hash->overlay_name) . '</div>
        
        <div class="stat-row">
            <span class="stat-label">Channel:</span>
            <span class="stat-value">' . htmlspecialchars($twitch['channel']['broadcaster_name'] ?? 'N/A') . '</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Followers:</span>
            <span class="stat-value">' . number_format($twitch['channel_followers']['total'] ?? 0) . '</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Latest Follower:</span>
            <span class="stat-value">' . htmlspecialchars($twitch['channel_followers']['data'][0]['user_name'] ?? 'None') . '</span>
        </div>
        
        <div class="stat-row">
            <span class="stat-label">Subscribers:</span>
            <span class="stat-value">' . number_format($twitch['subscribers']['total'] ?? 0) . '</span>
        </div>
        
        <div class="timestamp">
            Last updated: ' . date('Y-m-d H:i:s') . '
        </div>

    </div>
    
</body>
</html>';

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Build complete HTML document with parsed template
     */
    private function buildCompleteHtmlDocument(string $parsedHtml, string $parsedCss, OverlayHash $hash, array $data): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($hash->overlay_name) . '</title>
    <style>
        ' . $parsedCss . '
    </style>
</head>
<body>
    ' . $parsedHtml . '
    
</body>
</html>';
    }

    /**
     * Helper: Flatten nested array for CSV
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? $prefix . '.' . $key : $key;
            
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
        
        if (!$hash) {
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