<?php

namespace App\Http\Controllers;

use App\Services\JsonTemplateParserService;
use App\Services\TwitchApiService;
use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Models\TemplateTagJob;
use App\Jobs\GenerateTemplateTags;
use App\Jobs\CleanupRedundantTags;
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

            // Get existing template tags organized by category for this user
            $existingTags = $this->parser->getOrganizedTemplateTagsForUser($user->id);

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
                'existingTags' => $this->parser->getOrganizedTemplateTagsForUser($user->id),
                'hasExistingTags' => false,
                'error' => 'Failed to load Twitch data. You can still generate template tags.'
            ]);
        }
    }

    /**
     * Generate standardized template tags from current Twitch data (async)
     */
    public function generateTags(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->access_token) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        try {
            // Check if there's already a pending or processing generation job
            $existingJob = TemplateTagJob::where('user_id', $user->id)
                ->where('job_type', 'generate')
                ->whereIn('status', ['pending', 'processing'])
                ->first();

            if ($existingJob) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template tag generation is already in progress',
                    'job_id' => $existingJob->id,
                    'status' => $existingJob->status
                ]);
            }

            // Create job record
            $jobRecord = TemplateTagJob::create([
                'user_id' => $user->id,
                'job_type' => 'generate',
                'status' => 'pending'
            ]);

            // Dispatch the job
            GenerateTemplateTags::dispatch($user, $jobRecord);

            Log::info('Template tag generation job dispatched', [
                'user_id' => $user->id,
                'job_id' => $jobRecord->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template tag generation started! This may take a few minutes.',
                'job_id' => $jobRecord->id,
                'status' => 'pending'
            ]);

        } catch (Exception $e) {
            Log::error('Error dispatching template tag generation job', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to start template tag generation',
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
    public function clearAllTags(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $tagCount = TemplateTag::where('user_id', $user->id)->count();
            $categoryCount = TemplateTagCategory::where('user_id', $user->id)->count();

            TemplateTag::where('user_id', $user->id)->delete();
            TemplateTagCategory::where('user_id', $user->id)->delete();

            Log::info('User template tags cleared', [
                'user_id' => $user->id,
                'tags_deleted' => $tagCount,
                'categories_deleted' => $categoryCount
            ]);

            // Clear the cache when tags are cleared
            cache()->forget('template_tags_v1_user_' . $user->id);

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
     * Clean up redundant _data_X_ tags (async)
     * Removes tags like channel_followers_data_3_user_id, followed_channels_data_7_broadcaster_name, etc.
     */
    public function cleanupRedundantTags(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            // Check if there's already a pending or processing cleanup job
            $existingJob = TemplateTagJob::where('user_id', $user->id)
                ->where('job_type', 'cleanup')
                ->whereIn('status', ['pending', 'processing'])
                ->first();

            if ($existingJob) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template tag cleanup is already in progress',
                    'job_id' => $existingJob->id,
                    'status' => $existingJob->status
                ]);
            }

            // Create job record
            $jobRecord = TemplateTagJob::create([
                'user_id' => $user->id,
                'job_type' => 'cleanup',
                'status' => 'pending'
            ]);

            // Dispatch the job
            CleanupRedundantTags::dispatch($user, $jobRecord);

            Log::info('Template tag cleanup job dispatched', [
                'user_id' => $user->id,
                'job_id' => $jobRecord->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template tag cleanup started!',
                'job_id' => $jobRecord->id,
                'status' => 'pending'
            ]);

        } catch (Exception $e) {
            Log::error('Error dispatching template tag cleanup job', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to start template tag cleanup',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get job status for template tag operations
     */
    public function getJobStatus(Request $request, string $jobType = null)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }

            $query = TemplateTagJob::where('user_id', $user->id);
            
            if ($jobType) {
                $query->where('job_type', $jobType);
            }

            $jobs = $query->orderBy('created_at', 'desc')->limit(10)->get();

            return response()->json([
                'success' => true,
                'jobs' => $jobs->map(function($job) {
                    return [
                        'id' => $job->id,
                        'job_type' => $job->job_type,
                        'status' => $job->status,
                        'progress' => $job->progress,
                        'result' => $job->result,
                        'error_message' => $job->error_message,
                        'started_at' => $job->started_at?->toIso8601String(),
                        'completed_at' => $job->completed_at?->toIso8601String(),
                        'created_at' => $job->created_at->toIso8601String(),
                    ];
                })
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching job status', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to fetch job status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all template tags (API endpoint)
     */
    public function getAllTags(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'User not authenticated'], 401);
            }
            
            // Cache the tags per user for 1 hour (3600 seconds)
            // The cache key includes user ID to separate tags per user
            $cacheKey = 'template_tags_v1_user_' . $user->id;
            $cacheDuration = 3600; // 1 hour
            
            $tags = cache()->remember($cacheKey, $cacheDuration, function () use ($user) {
                return $this->parser->getOrganizedTemplateTagsForUser($user->id);
            });

            return response()->json([
                'success' => true,
                'tags' => $tags,
                'cached_at' => now()->toIso8601String(),
                'cache_ttl' => $cacheDuration
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
