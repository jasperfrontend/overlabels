<?php

namespace App\Http\Controllers\Settings;

use Illuminate\Http\RedirectResponse;

class ThroneIntegrationController extends DonationIntegrationController
{
    protected function service(): string
    {
        return 'throne';
    }

    /**
     * Throne needs no user-supplied credentials: it signs every webhook with its
     * own global Ed25519 key (pinned in config), so connecting just provisions the
     * integration and surfaces the webhook URL the user pastes into Throne. The
     * routing token (webhook_token) is generated on create by the model.
     */
    public function connect(): RedirectResponse
    {
        $this->connectIntegration(auth()->user());

        return back()->with('success', 'Throne connected. Copy your webhook URL into Throne.');
    }
}
