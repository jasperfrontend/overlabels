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

    public int $timeout = 300; // 5 minutes

    public function __construct(
        public User $user,
        public TemplateTagJob $jobRecord
    ) {
        //
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle(
        TwitchApiService $twitchService,
        JsonTemplateParserService $parser
    ): void {
        try {
            $this->jobRecord->markAsProcessing();

            Log::info('Starting template tag generation job', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
            ]);

            $this->jobRecord->updateProgress([
                'step' => 'fetching_twitch_data',
                'message' => 'Fetching Twitch data...',
                'progress' => 20,
            ]);

            if (! $this->user->access_token) {
                throw new Exception('User has no Twitch access token');
            }

            // The tag list no longer depends on the shape of this payload - it
            // comes from the catalogue. This is read only for the sample values
            // shown in the tag browser and for the account's broadcaster_type,
            // so a partial or empty response degrades to catalogue samples
            // instead of silently producing a different set of tags.
            $twitchData = $twitchService->getExtendedUserData(
                $this->user->access_token,
                $this->user->twitch_id
            );

            $this->jobRecord->updateProgress([
                'step' => 'generating_tags',
                'message' => 'Generating template tags...',
                'progress' => 60,
            ]);

            $result = $parser->syncTagsForUser($this->user, $twitchData);

            cache()->forget('template_tags_v1_user_'.$this->user->id);

            $this->jobRecord->updateProgress([
                'step' => 'completed',
                'message' => 'Template tags generated successfully!',
                'progress' => 100,
            ]);

            $this->jobRecord->markAsCompleted($result);

            Log::info('Template tag generation job completed', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
                ...$result,
            ]);

        } catch (Exception $e) {
            Log::error('Template tag generation job failed', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->jobRecord->markAsFailed($e->getMessage());
            throw $e;
        }
    }
}
