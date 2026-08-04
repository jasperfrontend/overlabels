<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BMACIntegrationController extends DonationIntegrationController
{
    private const array SUPPORTED_EVENTS = [
        'donation',
        'commission',
        'extra',
        'membership',
        'recurring',
        'wishlist',
    ];

    protected function service(): string
    {
        return 'bmac';
    }

    protected function credentialFlags(): array
    {
        return ['has_secret' => 'webhook_secret'];
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        // BMAC's webhook secret is generated AFTER you paste the Overlabels
        // webhook URL into BMAC, so the first save creates the integration
        // (and thus the webhook_token URL) with no secret. The user comes back
        // with the secret on a second save.
        $validated = $request->validate([
            'webhook_secret' => 'nullable|string|max:512',
            'enabled_events' => 'nullable|array',
            'enabled_events.*' => 'string|in:'.implode(',', self::SUPPORTED_EVENTS),
            'enabled' => 'nullable|boolean',
        ]);

        $integration = $this->connectIntegration($user, ['enabled' => false]);

        $existingCredentials = $integration->getCredentialsDecrypted();
        $hadSecret = ! empty($existingCredentials['webhook_secret']);
        $newSecret = $validated['webhook_secret'] ?? null;

        if ($newSecret !== null && $newSecret !== '') {
            $integration->setCredentialsEncrypted(['webhook_secret' => $newSecret]);
        }

        // Merge so one-time flags (donations_seed_set) survive a re-save.
        $integration->settings = array_merge(
            $integration->settings ?? [],
            ['enabled_events' => $validated['enabled_events'] ?? self::SUPPORTED_EVENTS],
        );

        // Only enable once a secret exists - until then, incoming webhooks
        // would 403 anyway and BMAC would disable the webhook on us.
        $hasSecret = $hadSecret || ($newSecret !== null && $newSecret !== '');
        $integration->enabled = $hasSecret && ($validated['enabled'] ?? true);
        $integration->save();

        $message = $hasSecret
            ? 'Buy Me a Coffee integration saved.'
            : 'Webhook URL generated. Copy it into your BMAC webhook, then come back here with the secret BMAC shows you.';

        return back()->with('success', $message);
    }
}
