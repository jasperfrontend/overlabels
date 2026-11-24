## Audit of OL-2611-036 - feat(alerts): many of the same Twitch event at once play one alert

**Audited:** 2026-11-24
**Commit:** ad81c2d6 (HEAD at audit time; @ad81c2d6 and @HEAD are the same tree)
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Alerts/AlertBursts.php:43 @ad81c2d6` - `OPEN_WINDOW_SECONDS = 1`; `:46` - `KEEP_OPEN_SECONDS = 4` |
| C2 | CONFIRMED | `app/Services/Alerts/AlertBursts.php:67-76 @ad81c2d6` - match arm `'channel.follow', 'channel.subscription.message', 'channel.cheer' => $eventType` (:70); `'channel.subscribe' => ($event['is_gift'] ?? false) === true ? null : $eventType` (:71); redemption suffixed `:reward.id` when `isset($event['reward']['id'])` (:72-74); `default => null` (:75) |
| C3 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:903-910 @ad81c2d6` - `$alerts = $kind !== null && EventTemplateMapping::where('user_id', ...)->where('event_type', $eventType)->where('enabled', true)->whereHas('template')->exists()`; `offer()` is called only inside `if ($alerts)` |
| C4 | CONFIRMED | `TwitchEventSubController.php:915-919 @ad81c2d6` - on `AlertBursts::HOLD`, `FlushAlertBurst::dispatch(...)->delay(now()->addSeconds(AlertBursts::OPEN_WINDOW_SECONDS))` then `return` before `fireAlert()` |
| C5 | CONFIRMED | `TwitchEventSubController.php:922-926 @ad81c2d6` - on `AlertBursts::JOINED`, `record($row, DeliveryOutcome::BurstAbsorbed)` then `return` |
| C6 | CONFIRMED | `app/Jobs/FlushAlertBurst.php:27 @ad81c2d6` - `$tries = 1`; `:40` - `$this->onQueue(ProcessTwitchEvent::QUEUE)` (`app/Jobs/ProcessTwitchEvent.php:34` - `'twitch-events'`) |
| C7 | CONFIRMED | `app/Jobs/FlushAlertBurst.php:60-65 @ad81c2d6` - `burst_count`/`burst_started_at` set only `if ($burst !== null)`; `AlertBursts::flush()` returns null when `count < 2` (`AlertBursts.php` `flush()`); then `$controller->fireAlert($user, $data, $row)` |
| C8 | CONFIRMED | `app/Jobs/FlushAlertBurst.php:68-73 @ad81c2d6` - `failed()` calls `app(AlertBursts::class)->forget($user, $this->kind)` when the user exists |
| C9 | CONFIRMED | `app/Services/Alerts/AlertBursts.php:103-104 @ad81c2d6` - `if ($actor !== null && in_array($actor, $state['actors'], true)) return self::ALONE;`; `$actor` is set only for `channel.cheer` and is null for `is_anonymous` true (`actorOf()`) |
| C10 | CONFIRMED | `TwitchEventSubController.php:843-851 @ad81c2d6` - `GiftSubAbsorber::absorb()` branch (:843), then `elseif ... self::isLate($sentAt)` (:847), then `elseif ($user) $this->alertOrBurst(...)` (:851) |
| C11 | CONFIRMED | `TwitchEventSubController.php:936-964 @ad81c2d6` - `fireAlert()` holds `resolveForEvent()` (:943), `GiftSubAbsorber::expect()` for `channel.subscription.gift` (:950), `renderEventAlert()` (:954), `DeliveryOutcome::NoMapping` (:956); the diff shows the same lines removed from `processStoredEvent()` |
| C12 | CONFIRMED | `app/Enums/DeliveryOutcome.php:48 @ad81c2d6` - `case BurstAbsorbed = 'burst_absorbed'`; `isScored()` (:54-60) returns true only for the five delivery cases, `default => false` |
| C13 | CONFIRMED | `tests/Feature/AlertBurstTest.php @ad81c2d6` - one test per listed case (lines 110, 123, 140, 150, 172, 186, 196, 207, 224, 234, 243), `FlushAlertBurst` faked (:107) and run via `burstFlushAll()` (:77-84). `php artisan test --filter=AlertBurstTest`: 12 passed, 56 assertions |
| C14 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a modified tree |

### Surface
Complete.

### Findings
None.

### Notes
- Tests run: `--filter=AlertBurstTest` 12 passed; `--filter=DeliveryLedgerTest` 15 passed; `--filter=StreamSessionDeliveryTest` 6 passed; `npm test -- resources/js/utils/deliveryOutcome.test.ts` 3 passed.
- Unchanged lines hold: the only `processStoredEvent()` hunk replaces the alert block (@ad81c2d6 :851); `replay()` (:1097), `replayForUser()` (:1112), `testCheer()` (:1150) and `ExternalAlertService` are not in the diff.
- OL-2611-013's Unchanged line ("the alert path inside the moved block are moved verbatim") described that commit; this change moves the block again into `fireAlert()`, which C11 discloses. OL-2611-011 C15 (`expect()` only for a resolved gift mapping) still holds at `fireAlert()` :949-951.
