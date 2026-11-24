## Audit of OL-2611-037 - feat(alerts): burst triggers, so a burst can have an alert of its own

**Audited:** 2026-11-24
**Commit:** d15bc6c7d8329bbdbd0504ef0ac2b6b7687c5b04
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/EventTemplateMapping.php:90,92,95,97,100 @d15bc6c7` - each `burst.*` key sits on the line after its base key (`channel.follow`, `channel.subscribe`, `channel.subscription.message`, `channel.cheer`, `channel.channel_points_custom_reward_redemption.add`); HEAD is d15bc6c7 |
| C2 | CONFIRMED | `app/Models/EventTemplateMapping.php:133 @d15bc6c7` - `BURST_PREFIX = 'burst.'`; `:136-139` `burstTypeFor()` returns `BURST_PREFIX.$eventType`; `:142-147` `baseEventType()` strips the prefix with `str_starts_with`/`substr`, else returns the input |
| C3 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:907-911 @d15bc6c7` - `whereIn('event_type', [$eventType, EventTemplateMapping::burstTypeFor($eventType)])->where('enabled', true)->whereHas('template')->exists()` gates the `AlertBursts` offer |
| C4 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:945-948 @d15bc6c7` - `isset($event['burst_count'])` resolves `burstTypeFor($eventType)` first, then `$mapping ??= resolveForEvent($user->id, $eventType, $event)`; `resolveForEvent()` returns null on no mapping (`app/Models/EventTemplateMapping.php:198-200 @HEAD`) |
| C5 | CONFIRMED | `app/Services/Alerts/AlertEventSamples.php:95-106 @d15bc6c7` - `$base = baseEventType($eventType)`, `twitchPayloads($base)`, `'subscription' => ['type' => $base]`, event merged with `['burst_count' => 24, 'burst_started_at' => strtotime(self::WHEN)]` when `$base !== $eventType` |
| C6 | CONFIRMED | `tests/Feature/AlertBurstTest.php:255-292 @d15bc6c7` - four tests assert exactly the four statements (burst -> `burst.channel.follow` alert count 2; lone follow -> `channel.follow` count null; burst-only mapping -> one burst alert and first row `DeliveryOutcome::NoMapping`; `burstTypeFor`/`baseEventType` round-trip incl. passthrough). `php artisan test --filter=AlertBurstTest`: 16 passed |
| C7 | CONFIRMED | `tests/Feature/EventSubExpansionTest.php:261-280 @d15bc6c7` - `array_map(baseEventType(...), array_keys(EVENT_TYPES))` diffed against `SUPPORTED_EVENTS` keys, expected `[]`. `php artisan test --filter=EventSubExpansionTest`: 18 passed |
| C8 | CONFIRMED | `tests/Feature/EventSubExpansionTest.php:282-292 @d15bc6c7` - every `BURST_PREFIX` key rejected only when `AlertBursts::kindOf(baseEventType($type), ['reward' => ['id' => 'r']]) !== null`, expected `[]`; passes (same run) |
| C9 | CONFIRMED | `tests/Feature/AlertEventSamplesTest.php:18-24 @HEAD` loops every `EVENT_TYPES` key and fails on no variants or empty tags; file not in the diff. `php artisan test --filter=AlertEventSamplesTest`: 6 passed |
| C10 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a modified tree |
| C11 | UNVERIFIABLE | tagged [unverified]; browser rendering check |

### Surface
Complete.

### Findings
None.

### Notes
- HEAD is d15bc6c7; there is no drift to report.
- C3 widens the gate recorded in OL-2611-036 C3 (offer only when a mapping exists "for that event type"): an event is now also held when only its burst trigger is mapped. The claim cites OL-2611-036 inline (Unchanged) and restates the rule in full, so it is noted, not a finding.
- Unchanged lines checked: `app/Services/Alerts/AlertBursts.php`, `app/Services/UserEventSubManager.php`, `resources/js/components/TriggerManager.vue` and `app/Http/Controllers/OverlayTemplateController.php` are absent from `git show --stat d15bc6c7`; the last two read `EVENT_TYPES` (`TriggerManager.vue:100`, `OverlayTemplateController.php:1517,1550 @HEAD`).
