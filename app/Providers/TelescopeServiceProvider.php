<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        // Outside local, Telescope's filter still records failed requests and
        // reportable exceptions. A donation webhook that 500s is a failed
        // request, so its body was being captured in full - supporter email,
        // postal address, the lot - and kept for 48 hours. These are the
        // parameter names those bodies carry.
        Telescope::hideRequestParameters([
            '_token',
            'data',
            'email',
            'supporter_email',
            'shipping',
            'shipping_address',
            'address',
            'phone',
            'telephone',
            'message',
            'verification_token',
            'discord_username',
            'discord_userid',
        ]);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-internal-secret',
            'x-listener-secret',
            'x-gpslogger-token',
            'authorization',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user) {
            return $user?->isAdmin() ?? false;
        });
    }
}
