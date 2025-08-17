<?php

namespace App\Jobs;

use App\Models\TemplateTagJob;
use App\Models\User;
use App\Services\JsonTemplateParserService;
use App\Services\TwitchApiService;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class GenerateTemplateTags implements ShouldQueue
{
    use Queueable;

    public $timeout = 300; // 5 minutes

    public function __construct(
        public User $user,
        public TemplateTagJob $jobRecord
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(
        TwitchApiService $twitchService,
        JsonTemplateParserService $parser
    ): void {
        try {
            // Mark job as processing
            $this->jobRecord->markAsProcessing();

            Log::info('Starting template tag generation job', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id
            ]);

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'fetching_twitch_data',
                'message' => 'Fetching Twitch data...',
                'progress' => 10
            ]);

            // Get Twitch data
            if (!$this->user->access_token) {
                throw new Exception('User has no Twitch access token');
            }

            $twitchData = $twitchService->getExtendedUserData(
                $this->user->access_token,
                $this->user->twitch_id
            );

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'processing_data',
                'message' => 'Processing Twitch data structures...',
                'progress' => 30
            ]);

            // Ensure data arrays exist before processing
            $twitchData = $this->ensureDataArraysExist($twitchData);

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'generating_tags',
                'message' => 'Generating template tags...',
                'progress' => 50
            ]);

            // Generate template tags from the Twitch data
            $generatedTags = $parser->parseJsonAndCreateTags($twitchData);

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'saving_tags',
                'message' => 'Saving tags to database...',
                'progress' => 80
            ]);

            // Save the generated tags to database with user_id
            $saved = $parser->saveTagsToDatabaseForUser($generatedTags, $this->user->id);

            // Clear the cache when tags are updated
            cache()->forget('template_tags_v1_user_' . $this->user->id);

            // Update progress to completion
            $this->jobRecord->updateProgress([
                'step' => 'completed',
                'message' => 'Template tags generated successfully!',
                'progress' => 100
            ]);

            // Mark job as completed
            $this->jobRecord->markAsCompleted([
                'generated' => $generatedTags['total_tags'],
                'saved' => $saved,
                'categories' => count($generatedTags['categories']),
                'tags' => count($generatedTags['tags'])
            ]);

            Log::info('Template tag generation job completed', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
                'generated' => $generatedTags['total_tags'],
                'saved' => $saved
            ]);

            // Dispatch cleanup job automatically
            $cleanupJobRecord = TemplateTagJob::create([
                'user_id' => $this->user->id,
                'job_type' => 'cleanup',
                'status' => 'pending'
            ]);

            CleanupRedundantTags::dispatch($this->user, $cleanupJobRecord);

        } catch (Exception $e) {
            Log::error('Template tag generation job failed', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->jobRecord->markAsFailed($e->getMessage());
            throw $e;
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
}
