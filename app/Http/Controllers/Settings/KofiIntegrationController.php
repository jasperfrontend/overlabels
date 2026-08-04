<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KofiIntegrationController extends DonationIntegrationController
{
    protected function service(): string
    {
        return 'kofi';
    }

    protected function credentialFlags(): array
    {
        return ['has_token' => 'verification_token'];
    }

    public function save(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $validated = $request->validate([
            'verification_token' => 'required|string|max:255',
            'enabled_events' => 'nullable|array',
            'enabled_events.*' => 'string|in:donation,subscription,shop_order,commission',
            'enabled' => 'nullable|boolean',
        ]);

        $isNew = ! $this->integration($user);

        $integration = $this->connectIntegration($user);

        $integration->setCredentialsEncrypted([
            'verification_token' => $validated['verification_token'],
        ]);

        // Merge so that one-time flags (e.g. donations_seed_set) survive a re-save
        $integration->settings = array_merge(
            $integration->settings ?? [],
            ['enabled_events' => $validated['enabled_events'] ?? ['donation', 'subscription', 'shop_order']],
        );

        // Force enabled on first connection; respect the submitted value on updates.
        $integration->enabled = $isNew || ($validated['enabled'] ?? true);
        $integration->save();

        return back()->with('success', 'Ko-fi integration saved.');
    }
}
