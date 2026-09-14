<?php

namespace App\Support;

use App\Services\External\ExternalServiceRegistry;

/**
 * How to make a donation service send a test event, per service: the page
 * to open, and what to press once there. A product that connects a donation
 * service shows the guide for the one the streamer picked, so the moment
 * after install has a payoff - an alert actually landing - instead of a
 * stage sitting in OBS with nothing on it.
 *
 * Knowledge about the SERVICE, not about any product, which is why it lives
 * here and not in a manifest: every product connecting Ko-fi wants the same
 * three lines. The first press lands without test mode; a second identical
 * press is dropped as a retry, which is what the test-mode switch on the
 * service's settings page is for.
 *
 * Every step was walked by hand on 2026-09-14 from a desktop browser.
 * Streamlabs was also checked on mobile, because its Test menu moves; the
 * other four dashboards read the same on both.
 */
final class ServiceTestGuides
{
    /**
     * `{webhook_prefix}` in a step becomes this install's own webhook URL
     * prefix for the service, so a local install reads its own host.
     *
     * @var array<string, array{url: string, label: string, steps: list<string>}>
     */
    private const array GUIDES = [
        'kofi' => [
            'url' => 'https://ko-fi.com/manage/webhooks',
            'label' => 'Open Ko-fi webhooks',
            'steps' => [
                'Log in to Ko-fi if it asks.',
                'Scroll down to "Send a test".',
                'Press "Send single tip test".',
            ],
        ],
        'streamlabs' => [
            'url' => 'https://streamlabs.com/dashboard#/alertbox/twitch/follows',
            'label' => 'Open the Streamlabs alert box',
            'steps' => [
                'Log in to Streamlabs if it asks. If it does not bring you back to the alert box, pick Alert Box under Streaming essentials in the main menu.',
                'Find the Test menu: to the right of "Filter Events" on desktop, below it on mobile.',
                'Pick Test, then Streamlabs, then Tipping.',
            ],
        ],
        'bmac' => [
            'url' => 'https://studio.buymeacoffee.com/dashboard',
            'label' => 'Open Buy Me a Coffee',
            'steps' => [
                'Log in if it asks, then pick Integrations under Settings in the menu.',
                'Scroll to the bottom, to "Webhooks". Press the count next to it.',
                'Open the webhook whose URL starts with {webhook_prefix}',
                'At the bottom of that page, press "Send test event".',
            ],
        ],
        'fourthwall' => [
            'url' => 'https://admin.fourthwall.com',
            'label' => 'Open Fourthwall admin',
            'steps' => [
                'Log in if it asks. It lands on your store.',
                'Pick Settings in the main menu, then "For developers".',
                'Under "Created by Overlabels", press "Send test notification".',
            ],
        ],
        'throne' => [
            'url' => 'https://throne.com/profile/integrations/webhook',
            'label' => 'Open Throne webhooks',
            'steps' => [
                'Log in to Throne if it asks.',
                'Find the webhook whose URL starts with {webhook_prefix}',
                'Under "Test delivery", press "Test webhook".',
            ],
        ],
    ];

    /**
     * The guide for a service, or null for one that has no test button of
     * its own (Overlabels' own channels: checkin, tower, gps).
     *
     * @return array{service: string, service_label: string, url: string, label: string, steps: list<string>, settings_url: string}|null
     */
    public static function for(string $service): ?array
    {
        $guide = self::GUIDES[$service] ?? null;
        if ($guide === null) {
            return null;
        }

        $prefix = url('/api/webhooks/'.$service).'/';

        return [
            'service' => $service,
            'service_label' => ExternalServiceRegistry::displayName($service),
            'url' => $guide['url'],
            'label' => $guide['label'],
            'steps' => array_map(fn (string $step) => str_replace('{webhook_prefix}', $prefix, $step), $guide['steps']),
            'settings_url' => route('settings.integrations.'.$service.'.show'),
        ];
    }

    /**
     * The first of these services that has a guide, or null.
     *
     * @param  list<string>  $services
     * @return array<string, mixed>|null
     */
    public static function firstFor(array $services): ?array
    {
        foreach ($services as $service) {
            $guide = self::for((string) $service);
            if ($guide !== null) {
                return $guide;
            }
        }

        return null;
    }
}
