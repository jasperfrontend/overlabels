<?php

namespace App\Providers;

use App\Events\UserRegistered;
use App\Listeners\OnboardNewUserListener;
use App\Listeners\SendSignupNotification;
use App\Services\DefaultTemplateProviderService;
use App\Services\TemplateDataMapperService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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

        Event::listen(function (SocialiteWasCalled $event) {
            $event->extendSocialite('twitch', Provider::class);
        });

        // Register user registration event listeners
        Event::listen(
            UserRegistered::class,
            SendSignupNotification::class
        );

        Event::listen(
            UserRegistered::class,
            OnboardNewUserListener::class
        );
    }
}
