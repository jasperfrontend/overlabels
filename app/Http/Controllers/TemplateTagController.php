<?php

namespace App\Http\Controllers;

use App\Services\JsonTemplateParserService;
use App\Services\TwitchApiService;
use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use Exception;
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

        try {
            // Get current Twitch data with error handling
            $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            // Get existing template tags organized by category
            $existingTags = $this->parser->getOrganizedTemplateTags();

            return Inertia::render('TemplateTagGenerator', [
                'twitchData' => $twitchData,
                'existingTags' => $existingTags,
                'hasExistingTags' => !empty($existingTags)
            ]);
        } catch (Exception $e) {
            Log::error('Error loading template generator', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            // Return with empty data rather than failing completely
            return Inertia::render('TemplateTagGenerator', [
                'twitchData' => $this->getEmptyTwitchData(),
                'existingTags' => $this->parser->getOrganizedTemplateTags(),
                'hasExistingTags' => false,
                'error' => 'Failed to load Twitch data. You can still generate template tags.'
            ]);
        }
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
            // Get fresh Twitch data with error handling
            $twitchData = $this->twitch->getExtendedUserData($user->access_token, $user->twitch_id);

            // Ensure data arrays exist before processing
            $twitchData = $this->ensureDataArraysExist($twitchData);

            Log::info('Starting template tag generation', [
                'user_id' => $user->id,
                'data_structure' => array_keys($twitchData)
            ]);

            // Generate template tags from the Twitch data
            $generatedTags = $this->parser->parseJsonAndCreateTags($twitchData);

            Log::info('Template tags generated successfully', [
                'categories' => count($generatedTags['categories']),
                'tags' => count($generatedTags['tags'])
            ]);

            // Save the generated tags to database
            $saved = $this->parser->saveTagsToDatabase($generatedTags);

            Log::info('Template tags saved to database', $saved);

            return response()->json([
                'success' => true,
                'message' => 'Template tags generated successfully!',
                'generated' => $generatedTags['total_tags'],
                'saved' => $saved
            ]);

        } catch (Exception $e) {
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
     * Preview a specific tag with current data
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

            // Ensure data arrays exist
            $twitchData = $this->ensureDataArraysExist($twitchData);

            // Get the formatted output for this tag
            $output = $tag->getFormattedOutput($twitchData);

            return response()->json([
                'tag' => $tag->display_tag,
                'output' => $output,
                'data_type' => $tag->data_type,
                'json_path' => $tag->json_path,
                'display_name' => $tag->display_name
            ]);

        } catch (Exception $e) {
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
    public function clearAllTags()
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
                'message' => "Cleared $tagCount template tags and $categoryCount categories"
            ]);

        } catch (Exception $e) {
            Log::error('Error clearing template tags', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to clear template tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean up redundant _data_X_ tags
     * Removes tags like channel_followers_data_3_user_id, followed_channels_data_7_broadcaster_name, etc.
     */
    public function cleanupRedundantTags()
    {
        try {
            // Pattern to match tags with _data_[number]_ in them
            //$redundantPattern = '/_data_\d+_/';
            $redundantPattern = '/_data_\d/';

            // Find all tags that match the pattern
            $redundantTags = TemplateTag::all()->filter(function($tag) use ($redundantPattern) {
                return preg_match($redundantPattern, $tag->tag_name);
            });

            $deletedCount = 0;
            $deletedTags = [];

            foreach ($redundantTags as $tag) {
                $deletedTags[] = $tag->tag_name;
                $tag->delete();
                $deletedCount++;
            }

            // Also clean up any empty categories
            $emptyCategories = TemplateTagCategory::doesntHave('templateTags')->get();
            $deletedCategoriesCount = 0;
            foreach ($emptyCategories as $category) {
                $category->delete();
                $deletedCategoriesCount++;
            }

            Log::info('Redundant template tags cleaned up', [
                'tags_deleted' => $deletedCount,
                'empty_categories_deleted' => $deletedCategoriesCount,
                'deleted_tags' => $deletedTags
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully cleaned up $deletedCount redundant tags",
                'deleted_count' => $deletedCount,
                'deleted_tags' => $deletedTags,
                'empty_categories_deleted' => $deletedCategoriesCount
            ]);

        } catch (Exception $e) {
            Log::error('Error cleaning up redundant template tags', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to clean up redundant tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all template tags (API endpoint)
     */
    public function getAllTags()
    {
        try {
            $tags = $this->parser->getOrganizedTemplateTags();

            return response()->json([
                'success' => true,
                'tags' => $tags
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching all template tags', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to fetch template tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export standardized tags for sharing
     */
    public function exportStandardTags()
    {
        try {
            $tags = $this->parser->getOrganizedTemplateTags();

            $filename = 'template-tags-' . date('Y-m-d-H-i-s') . '.json';

            return response()->json($tags)
                ->header('Content-Disposition', "attachment; filename=\"$filename\"");

        } catch (Exception $e) {
            Log::error('Error exporting template tags', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to export template tags',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ensure all required data arrays exist to prevent "Undefined array key" errors
     */
    private function ensureDataArraysExist(array $twitchData): array
    {
        // Ensure top-level structures exist
        $requiredStructures = [
            'user' => [],
            'channel' => [],
            'channel_followers' => ['total' => 0, 'data' => []],
            'followed_channels' => ['total' => 0, 'data' => []],
            'subscribers' => ['total' => 0, 'points' => 0, 'data' => []],
            'goals' => ['data' => []]
        ];

        foreach ($requiredStructures as $key => $defaultValue) {
            if (!isset($twitchData[$key])) {
                $twitchData[$key] = $defaultValue;
                Log::warning("Missing Twitch data structure: $key, using default");
            }
        }

        // Ensure data arrays have at least empty objects to prevent index errors
        $dataArrays = ['channel_followers', 'followed_channels', 'subscribers', 'goals'];

        foreach ($dataArrays as $arrayKey) {
            if (isset($twitchData[$arrayKey]['data']) && empty($twitchData[$arrayKey]['data'])) {
                // Add empty object so index 0 access doesn't fail
                $twitchData[$arrayKey]['data'] = [[]];
                Log::info("Added empty data object for $arrayKey to prevent index errors");
            }
        }

        return $twitchData;
    }

    /**
     * Get empty Twitch data structure for fallback
     */
    private function getEmptyTwitchData(): array
    {
        return [
            'user' => [
                'id' => '',
                'login' => '',
                'display_name' => '',
                'type' => '',
                'broadcaster_type' => '',
                'description' => '',
                'profile_image_url' => '',
                'offline_image_url' => '',
                'view_count' => 0,
                'email' => '',
                'created_at' => ''
            ],
            'channel' => [
                'broadcaster_id' => '',
                'broadcaster_login' => '',
                'broadcaster_name' => '',
                'broadcaster_language' => '',
                'game_id' => '',
                'game_name' => '',
                'title' => '',
                'delay' => 0,
                'tags' => [],
                'content_classification_labels' => [],
                'is_branded_content' => false
            ],
            'channel_followers' => [
                'total' => 0,
                'data' => [[]]
            ],
            'followed_channels' => [
                'total' => 0,
                'data' => [[]]
            ],
            'subscribers' => [
                'total' => 0,
                'points' => 0,
                'data' => [[]]
            ],
            'goals' => [
                'data' => [[]]
            ]
        ];
    }
}
