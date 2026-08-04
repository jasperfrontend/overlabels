<?php

use App\Models\ExternalIntegration;
use App\Services\External\ExternalControlService;
use App\Services\External\ExternalServiceRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Backfills the controls that Ko-fi, Buy Me a Coffee and Throne never created.
 *
 * Only Streamlabs, Fourthwall and GPS called `provision()` on connect. The other
 * three left the user with a working webhook, verified signatures and events
 * landing in `external_events`, and nothing to read them from: the overlay render
 * payload is built from control rows, so a hand-typed
 * `[[[c:throne:latest_donor_name]]]` resolved to nothing at all.
 *
 * Connecting provisions uniformly now (see DonationIntegrationController), but
 * that only helps someone who connects again. Anyone already connected keeps an
 * empty control list until this runs.
 *
 * Idempotent in both directions: `provision()` does not overwrite an existing
 * control, so a user who added some by hand from the presets modal keeps their
 * values and gains only what was missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $controlService = app(ExternalControlService::class);

        ExternalIntegration::with('user')->chunkById(100, function ($integrations) use ($controlService) {
            foreach ($integrations as $integration) {
                if (! $integration->user || ! ExternalServiceRegistry::has($integration->service)) {
                    continue;
                }

                $driver = ExternalServiceRegistry::driver($integration->service);

                if ($driver->getAutoProvisionedControls() === []) {
                    continue;
                }

                $controlService->provision($integration->user, $driver);
            }
        });

        Log::info('Backfilled service-managed controls for existing integrations.');
    }

    public function down(): void
    {
        // Not reversible. Removing these would delete controls that are now
        // indistinguishable from ones the user added from the presets modal
        // themselves, along with whatever values have since accumulated.
    }
};
