<?php

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Services\Alerts\AlertEventSamples;

/*
 * The kit preview plays alerts with these samples, so a hole here is an alert
 * that previews with blank text, and a drift here is a preview that lies about
 * what the alert shows on stream.
 */

function eventSamples(): AlertEventSamples
{
    return app(AlertEventSamples::class);
}

test('every event the Triggers page offers has at least one sample with tags', function () {
    $missing = [];
    foreach (EventTemplateMapping::EVENT_TYPES as $type => $label) {
        $variants = eventSamples()->for('twitch', $type);
        if ($variants === [] || collect($variants)->contains(fn ($v) => $v['tags'] === [])) {
            $missing[] = "twitch:{$type}";
        }
    }
    foreach (ExternalEventTemplateMapping::SERVICE_EVENT_TYPES as $service => $types) {
        foreach ($types as $type => $label) {
            $variants = eventSamples()->for($service, $type);
            if ($variants === [] || collect($variants)->contains(fn ($v) => $v['tags'] === [])) {
                $missing[] = "{$service}:{$type}";
            }
        }
    }

    expect($missing)->toBe([]);
});

test('every Twitch sample produces every event tag its reference page documents', function () {
    $gaps = [];
    $checked = 0;
    foreach (glob(resource_path('help/reference/eventsub-tags/*.md')) as $file) {
        $body = file_get_contents($file);
        if (! preg_match('/EventSub event `([^`]+)`/', $body, $m) || ! isset(EventTemplateMapping::EVENT_TYPES[$m[1]])) {
            continue;
        }
        preg_match_all('/\[\[\[(event\.[a-z0-9_.]+)/', $body, $tags);
        $checked += count(array_unique($tags[1]));
        $produced = collect(eventSamples()->for('twitch', $m[1]))->flatMap(fn ($v) => array_keys($v['tags']))->unique()->all();
        foreach (array_unique($tags[1]) as $tag) {
            if (! in_array($tag, $produced, true)) {
                $gaps[] = "{$m[1]}: {$tag}";
            }
        }
    }

    // A drift guard that matches nothing passes forever: 27 pages, ~280 tags.
    expect($checked)->toBeGreaterThan(200)
        ->and($gaps)->toBe([]);
});

test('a donation sample is the driver\'s own output, formatted in the viewer\'s locale', function () {
    $tip = eventSamples()->for('kofi', 'donation', 'nl-NL')[0]['tags'];

    expect($tip['event.source'])->toBe('Ko-fi')
        ->and($tip['event.type'])->toBe('donation')
        ->and($tip['event.formatted_amount'])->toContain('5,00');
});

test('samples cover the branches an alert typically makes', function () {
    $sub = collect(eventSamples()->for('kofi', 'subscription'))->keyBy('key');
    $throne = collect(eventSamples()->for('throne', 'donation'))->keyBy('key');

    expect($sub['first']['tags']['event.is_first_sub'])->toBe('1')
        ->and($sub['renewal']['tags']['event.is_first_sub'])->toBe('0')
        ->and($throne['surprise']['tags']['event.is_surprise_gift'])->toBe('1')
        ->and($throne['crowdfund']['tags']['event.throne_event_type'])->toBe('gift_crowdfunded')
        ->and($throne['crowdfund']['tags']['event.from_name'])->toBe('');
});

test('an event alerts cannot be triggered by has no sample', function () {
    expect(eventSamples()->for('kofi', 'refund'))->toBe([])
        ->and(eventSamples()->for('twitch', 'channel.ban'))->toBe([])
        ->and(AlertEventSamples::isKnown('twitch', 'channel.follow'))->toBeTrue();
});

test('the catalogue lists every event, Twitch first', function () {
    $catalogue = AlertEventSamples::catalogue();
    $count = collect($catalogue)->sum(fn ($g) => count($g['events']));
    $expected = count(EventTemplateMapping::EVENT_TYPES) + collect(ExternalEventTemplateMapping::SERVICE_EVENT_TYPES)->sum(fn ($t) => count($t));

    expect($catalogue[0]['service'])->toBe('twitch')
        ->and($count)->toBe($expected);
});
