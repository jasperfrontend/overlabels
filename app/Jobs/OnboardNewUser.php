<?php

namespace App\Jobs;

use App\Models\EventTemplateMapping;
use App\Models\Kit;
use App\Models\TemplateTagJob;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OnboardNewUser implements ShouldQueue
{
    use Queueable;

    public $tries = 2;

    public $backoff = [30, 60];

    public $timeout = 120;

    public function __construct(
        public User $user
    ) {}

    public function handle(): void
    {
        Log::info('OnboardNewUser: Starting onboarding pipeline', [
            'user_id' => $this->user->id,
            'twitch_id' => $this->user->twitch_id,
        ]);

        // Guard: skip if user already has alert mappings (manual setup)
        if ($this->user->hasAlertMappings()) {
            Log::info('OnboardNewUser: User already has alert mappings, skipping', [
                'user_id' => $this->user->id,
            ]);

            return;
        }

        $this->generateWebhookSecret();
        $forkedKit = $this->forkStarterKit();
        if ($forkedKit) {
            $this->autoAssignEventMappings($forkedKit);
        }
        $this->dispatchTagGeneration();

        Log::info('OnboardNewUser: Pipeline completed', [
            'user_id' => $this->user->id,
            'has_kit' => $forkedKit !== null,
        ]);
    }

    private function generateWebhookSecret(): void
    {
        // Skip if already generated (e.g., at signup time in OAuth callback)
        if ($this->user->webhook_secret) {
            return;
        }

        $this->user->update([
            'webhook_secret' => bin2hex(random_bytes(32)),
        ]);

        Log::info('OnboardNewUser: Generated webhook secret', [
            'user_id' => $this->user->id,
        ]);
    }

    private function forkStarterKit(): ?Kit
    {
        $starterKit = Kit::find(config('app.starter_kit_id'));

        if (! $starterKit) {
            Log::warning('OnboardNewUser: Starter kit not found', [
                'starter_kit_id' => config('app.starter_kit_id'),
            ]);

            return null;
        }

        $forkedKit = $starterKit->fork($this->user);

        Log::info('OnboardNewUser: Forked starter kit', [
            'user_id' => $this->user->id,
            'original_kit_id' => $starterKit->id,
            'forked_kit_id' => $forkedKit->id,
            'template_count' => $forkedKit->templates()->count(),
        ]);

        return $forkedKit;
    }

    private function autoAssignEventMappings(Kit $forkedKit): void
    {
        // Event types in specificity order (most specific first)
        $eventKeywords = [
            'channel.subscription.gift' => ['gift sub', 'gift'],
            'channel.subscription.message' => ['resub', 'resubscri'],
            'channel.cheer' => ['cheer', 'bits'],
            'channel.raid' => ['raid'],
            'channel.channel_points_custom_reward_redemption.add' => ['channel point', 'redempt', 'reward'],
            'channel.follow' => ['follow'],
            'channel.subscribe' => ['subscribe', 'sub alert', 'new sub', ' sub'],
        ];

        $templates = $forkedKit->templates()->get();
        $candidates = $templates->keyBy('id');
        $matched = 0;

        foreach ($eventKeywords as $eventType => $keywords) {
            $matchedTemplate = null;

            foreach ($candidates as $template) {
                $name = Str::lower($template->name);
                foreach ($keywords as $keyword) {
                    if (Str::contains($name, $keyword)) {
                        $matchedTemplate = $template;
                        break 2;
                    }
                }
            }

            if ($matchedTemplate) {
                EventTemplateMapping::updateOrCreate(
                    [
                        'user_id' => $this->user->id,
                        'event_type' => $eventType,
                    ],
                    [
                        'template_id' => $matchedTemplate->id,
                        'enabled' => true,
                        'duration_ms' => 5000,
                        'transition_type' => 'fade',
                    ]
                );

                // Remove from candidate pool so it's not double-assigned
                $candidates->forget($matchedTemplate->id);
                $matched++;
            }
        }

        Log::info('OnboardNewUser: Auto-assigned event mappings', [
            'user_id' => $this->user->id,
            'matched' => $matched,
            'total_events' => count($eventKeywords),
        ]);
    }

    private function dispatchTagGeneration(): void
    {
        if (! $this->user->access_token) {
            Log::warning('OnboardNewUser: No access token, skipping tag generation', [
                'user_id' => $this->user->id,
            ]);

            return;
        }

        $jobRecord = TemplateTagJob::create([
            'user_id' => $this->user->id,
            'job_type' => 'generate',
            'status' => 'pending',
        ]);

        GenerateTemplateTags::dispatch($this->user, $jobRecord);

        Log::info('OnboardNewUser: Dispatched tag generation', [
            'user_id' => $this->user->id,
            'job_record_id' => $jobRecord->id,
        ]);
    }
}
