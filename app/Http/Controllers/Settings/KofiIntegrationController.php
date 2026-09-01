<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $existing = $this->integration($user);
        $hadToken = ! empty($existing?->getCredentialsDecrypted()['verification_token']);

        // The token is required once, on first connect. After that the field is
        // shown empty with a "(token saved ...)" placeholder, and re-saving the
        // page to change the enabled events must not demand it again. An empty
        // field keeps the stored token; a filled one replaces it.
        $validated = $request->validate([
            'verification_token' => [Rule::requiredIf(! $hadToken), 'nullable', 'string', 'max:255'],
            'enabled_events' => 'nullable|array',
            'enabled_events.*' => 'string|in:donation,subscription,shop_order,commission',
            'enabled' => 'nullable|boolean',
        ]);

        $isNew = ! $existing;

        $integration = $this->connectIntegration($user);

        $newToken = $validated['verification_token'] ?? null;
        if ($newToken !== null && $newToken !== '') {
            $integration->setCredentialsEncrypted([
                'verification_token' => $newToken,
            ]);
        }

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
