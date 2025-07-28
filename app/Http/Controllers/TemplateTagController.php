<?php
// app/Http/Controllers/TemplateTagController.php
// Simplified version without editing - focused on portability

namespace App\Http\Controllers;

use App\Services\JsonTemplateParserService;
use App\Services\TwitchApiService;
use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class TemplateTagController extends Controller
{
    protected JsonTemplateParserService $parser;
    protected TwitchApiService $twitch;

    public function __construct(JsonTemplateParserService $parser, TwitchApiService $twitch)
    {
        $this->parser = $parser;
        $this->twitch = $twitch;
    }

    /**
     * Show the template tag generator interface
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            abort(403, 'User not authenticated with Twitch');
        }

        // Get current Twitch data
        $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

        // Get existing template tags organized by category
        $existingTags = $this->parser->getOrganizedTemplateTags();

        return Inertia::render('TemplateTagGenerator', [
            'twitchData' => $twitchData,
            'existingTags' => $existingTags,
            'hasExistingTags' => !empty($existingTags)
        ]);
    }

    /**
     * Generate standardized template tags from current Twitch data
     */
    public function generateTags(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Get fresh Twitch data
            $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            if (empty($twitchData)) {
                return response()->json(['error' => 'No Twitch data available'], 400);
            }

            // Parse the JSON and generate STANDARDIZED tags
            $parsedData = $this->parser->parseJsonAndCreateTags($twitchData);

            // Save to database (will overwrite existing with same names)
            $saved = $this->parser->saveTagsToDatabase($parsedData);

            Log::info('Standardized template tags generated', [
                'user_id' => $user->id,
                'categories_created' => $saved['categories'],
                'tags_created' => $saved['tags'],
                'errors' => $saved['errors']
            ]);

            return response()->json([
                'success' => true,
                'message' => "Generated {$saved['tags']} standardized template tags in {$saved['categories']} categories",
                'data' => [
                    'categories' => $saved['categories'],
                    'tags' => $saved['tags'],
                    'total_tags' => $parsedData['total_tags'],
                    'errors' => $saved['errors']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error generating template tags', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to generate template tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a preview of what a specific tag would output with current data
     */
    public function previewTag(Request $request, TemplateTag $tag)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Get current Twitch data
            $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            // Get the formatted output for this tag
            $output = $tag->getFormattedOutput($twitchData);

            return response()->json([
                'tag' => $tag->display_tag,
                'output' => $output,
                'data_type' => $tag->data_type,
                'json_path' => $tag->json_path,
                'display_name' => $tag->display_name
            ]);

        } catch (\Exception $e) {
            Log::error('Error previewing template tag', [
                'tag_id' => $tag->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to preview tag',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all template tags (useful for regenerating)
     */
    public function clearAllTags(Request $request)
    {
        try {
            $tagCount = TemplateTag::count();
            $categoryCount = TemplateTagCategory::count();

            TemplateTag::truncate();
            TemplateTagCategory::truncate();

            Log::info('All template tags cleared', [
                'tags_deleted' => $tagCount,
                'categories_deleted' => $categoryCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Cleared {$tagCount} template tags and {$categoryCount} categories"
            ]);

        } catch (\Exception $e) {
            Log::error('Error clearing template tags', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to clear template tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available template tags for the frontend
     */
    public function getAllTags()
    {
        try {
            $organizedTags = $this->parser->getOrganizedTemplateTags();

            return response()->json([
                'success' => true,
                'data' => $organizedTags,
                'total_categories' => count($organizedTags),
                'total_tags' => TemplateTag::where('is_active', true)->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting template tags', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to get template tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export standard tags for template portability
     */
    public function exportStandardTags()
    {
        try {
            $standardTags = TemplateTag::standard()
                ->with('category')
                ->orderBy('tag_name')
                ->get()
                ->map(function($tag) {
                    return [
                        'tag_name' => $tag->tag_name,
                        'display_tag' => $tag->display_tag,
                        'json_path' => $tag->json_path,
                        'data_type' => $tag->data_type,
                        'category' => $tag->category->name,
                        'version' => $tag->version
                    ];
                });

            return response()->json([
                'version' => '1.0',
                'generated_at' => now()->toISOString(),
                'total_tags' => $standardTags->count(),
                'tags' => $standardTags
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to export tags'], 500);
        }
    }
}