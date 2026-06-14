<?php

namespace App\Providers;

use App\Broadcasting\MeteredBroadcaster;
use App\Events\UserRegistered;
use App\Listeners\OnboardNewUserListener;
use App\Models\User;
use App\Observers\UserObserver;
use App\Services\Bot\RateLimitLog as BotRateLimitLog;
use App\Services\BroadcastMeter;
use App\Services\DefaultTemplateProviderService;
use App\Services\EventMeter;
use App\Services\TemplateDataMapperService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Twitch\Provider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register DefaultTemplateProviderService as singleton
        // This ensures we only load template files once per request cycle
        $this->app->singleton(DefaultTemplateProviderService::class, function ($app) {
            return new DefaultTemplateProviderService;
        });

        // Register TemplateDataMapperService as singleton
        // This handles transformation of nested API data to flat template structure
        $this->app->singleton(TemplateDataMapperService::class, function ($app) {
            return new TemplateDataMapperService;
        });

        // One BroadcastMeter per request so its fail-fast "redis down" flag is
        // shared across the dashboard share and the Usage page reads.
        $this->app->singleton(BroadcastMeter::class);

        // Same per-request singleton treatment for the inbound-event meter.
        $this->app->singleton(EventMeter::class);

        // Register Telescope only in local development
        // Use class_exists() to avoid autoload failure when Telescope is not installed (--no-dev)
        if ($this->app->isLocal() && class_exists(TelescopeServiceProvider::class)) {
            $this->app->register(TelescopeServiceProvider::class);
            $this->app->register(\App\Providers\TelescopeServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS in production
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // Alternative: Force HTTPS if running on Railway
        if (isset($_SERVER['RAILWAY_ENVIRONMENT'])) {
            URL::forceScheme('https');
        }

        // Force HTTPS if APP_URL contains https
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Additional Railway detection
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            URL::forceScheme('https');
        }

        // Metered broadcaster: decorate the configured underlying connection
        // (reverb) so every broadcast is counted per user before delivery.
        // Active only when BROADCAST_CONNECTION=metered.
        Broadcast::extend('metered', function ($app, array $config) {
            $inner = $app->make(BroadcastFactory::class)->connection($config['connection'] ?? 'reverb');

            return new MeteredBroadcaster($inner, $app->make(BroadcastMeter::class));
        });

        // Configure rate limiters
        RateLimiter::for('overlay', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // You can also add more specific rate limiters
        RateLimiter::for('overlay-api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Rate limiter for failed authentication attempts
        RateLimiter::for('overlay-auth-failed', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Cloudinary uploads: 20/hour per user, 100/hour per IP. Generous for
        // normal use, hostile to abuse. Frontend uploads are now routed
        // through our backend so this is the only choke point.
        RateLimiter::for('cloudinary-upload', function (Request $request) {
            $userId = $request->user()?->id;

            return [
                Limit::perHour(20)->by($userId ?: $request->ip()),
                Limit::perHour(100)->by($request->ip()),
            ];
        });

        // Bot internal endpoints (token/commands/controls/outbox/settings).
        // The bot is a single Railway service hitting us from one IP, so the
        // bucket is global. Outbox alone polls every 2s = 30/min, so the limit
        // has to leave headroom above that for controls writes and bursts.
        RateLimiter::for('bot-internal', function (Request $request) {
            return Limit::perMinute(600)->by('bot-internal:'.$request->ip());
        });

        // Bot gamejam votes: own bucket, keyed per-channel so a flood in one
        // channel can't starve another. 50 players * 2 rounds/min = 100 votes
        // baseline; multi-key spam pushes higher. 1200/min (20/sec) per login.
        RateLimiter::for('bot-gamejam-action', function (Request $request) {
            $login = $request->route('login') ?? 'unknown';

            return Limit::perMinute(1200)
                ->by('bot-gamejam-action:'.$login)
                ->response(function (Request $request, array $headers) use ($login) {
                    BotRateLimitLog::record('gamejam-action', $login, $request->ip());

                    return response()->json(['message' => 'Too Many Attempts.'], 429, $headers);
                });
        });

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('twitch', Provider::class);
        });

        Event::listen(
            UserRegistered::class,
            OnboardNewUserListener::class
        );

        // BridgePickerLandedToControl is registered via auto-discovery
        // (Laravel scans app/Listeners and binds any handle* method by its
        // typed event parameter), so no explicit Event::listen() call is
        // needed. This also auto-binds RecomputeExpressionControls::handleBatch
        // and ListWriterAppend::handleBatch to ControlValuesBatchUpdated, so a
        // batched service tick drives the same cascades as a single update.

        User::observe(UserObserver::class);
    }
}
