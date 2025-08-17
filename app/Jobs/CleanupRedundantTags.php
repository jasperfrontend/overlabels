<?php

namespace App\Jobs;

use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Models\TemplateTagJob;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanupRedundantTags implements ShouldQueue
{
    use Queueable;

    public $timeout = 120; // 2 minutes

    public function __construct(
        public User $user,
        public TemplateTagJob $jobRecord
    ) {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Mark job as processing
            $this->jobRecord->markAsProcessing();

            Log::info('Starting template tag cleanup job', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id
            ]);

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'scanning_tags',
                'message' => 'Scanning for redundant tags...',
                'progress' => 20
            ]);

            // Pattern to match tags with _data_[number]_ in them
            $redundantPattern = '/_data_\d/';

            // Find all tags that match the pattern for this user
            $redundantTags = TemplateTag::where('user_id', $this->user->id)
                ->get()
                ->filter(function($tag) use ($redundantPattern) {
                    return preg_match($redundantPattern, $tag->tag_name);
                });

            $totalRedundant = $redundantTags->count();

            Log::info('Found redundant tags for cleanup', [
                'user_id' => $this->user->id,
                'count' => $totalRedundant
            ]);

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'deleting_tags',
                'message' => "Deleting {$totalRedundant} redundant tags...",
                'progress' => 40
            ]);

            $deletedCount = 0;
            $deletedTags = [];

            foreach ($redundantTags as $index => $tag) {
                $deletedTags[] = $tag->tag_name;
                $tag->delete();
                $deletedCount++;

                // Update progress every 10 tags
                if ($deletedCount % 10 === 0) {
                    $progressPercent = 40 + (($deletedCount / $totalRedundant) * 40);
                    $this->jobRecord->updateProgress([
                        'step' => 'deleting_tags',
                        'message' => "Deleted {$deletedCount}/{$totalRedundant} redundant tags...",
                        'progress' => min(80, $progressPercent)
                    ]);
                }
            }

            // Update progress
            $this->jobRecord->updateProgress([
                'step' => 'cleaning_categories',
                'message' => 'Cleaning up empty categories...',
                'progress' => 85
            ]);

            // Also clean up any empty categories for this user
            $emptyCategories = TemplateTagCategory::where('user_id', $this->user->id)
                ->doesntHave('templateTags')
                ->get();
            
            $deletedCategoriesCount = 0;
            foreach ($emptyCategories as $category) {
                $category->delete();
                $deletedCategoriesCount++;
            }

            // Clear the cache when tags are cleaned up
            cache()->forget('template_tags_v1_user_' . $this->user->id);

            // Update progress to completion
            $this->jobRecord->updateProgress([
                'step' => 'completed',
                'message' => "Cleanup completed! Removed {$deletedCount} redundant tags.",
                'progress' => 100
            ]);

            // Mark job as completed
            $this->jobRecord->markAsCompleted([
                'deleted_tags_count' => $deletedCount,
                'deleted_categories_count' => $deletedCategoriesCount,
                'deleted_tags' => $deletedTags
            ]);

            Log::info('Template tag cleanup job completed', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
                'deleted_tags_count' => $deletedCount,
                'deleted_categories_count' => $deletedCategoriesCount
            ]);

        } catch (Exception $e) {
            Log::error('Template tag cleanup job failed', [
                'user_id' => $this->user->id,
                'job_id' => $this->jobRecord->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->jobRecord->markAsFailed($e->getMessage());
            throw $e;
        }
    }
}
