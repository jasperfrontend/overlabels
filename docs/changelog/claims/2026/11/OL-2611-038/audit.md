## Audit of OL-2611-038 - feat(eventsub): floods become numbers - the last hundred kept, the rest counted

**Audited:** 2026-11-26
**Commit:** 1764167ba23ff4d2779a297ec6bde40b95ad43cc (the only commit with the trailer; it is also HEAD, so @1764167 and @HEAD are the same tree)

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/Floods/FloodGate.php:52,55,58,61,64,67 @1764167` - `EVENT_TYPES` is the two types, `PASS_THROUGH` 100, `KEPT` 100, `KEEP_OPEN_SECONDS = AlertBursts::KEEP_OPEN_SECONDS`, `TICK_SECONDS` 2, `DEDUP_SECONDS` 900 |
| C2 | CONFIRMED | `FloodGate.php:86-119 @1764167` - other type -> `PASS` (86); `flood:msg:{id}` present -> `DUPLICATE` (90); `increment(:n) <= PASS_THROUGH` -> `PASS` (103); else `COUNTED` (119) |
| C3 | CONFIRMED | `FloodGate.php:94 @1764167` - `AlertBursts::kindOf($eventType, $event) ?? $eventType`; `app/Services/Alerts/AlertBursts.php:87-89 @1764167` suffixes redemptions with `reward.id` |
| C4 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:579,590-597 @1764167` - gate after `TwitchPayloadScrubber::scrub()`, inside `if ($user)`, returns `'duplicate'` / `'stored'` before `TwitchEvent::create()` and the `ProcessTwitchEvent` dispatch |
| C5 | CONFIRMED | `FloodGate.php:107-117 @1764167` - message key put for `DEDUP_SECONDS`, `flood:{user}:{kind}:dropped` incremented, `keep()` slices to `-KEPT` (289-296), `Cache::add(:tick, 1, 60)` gates a `FloodTick` delayed `TICK_SECONDS`. The append can be skipped on lock timeout (see F2) |
| C6 | CONFIRMED | `app/Jobs/FloodTick.php:24,34 @1764167` - `$tries = 1`, `onQueue(ProcessTwitchEvent::queueFor($broadcasterTwitchId))` |
| C7 | CONFIRMED | `FloodGate.php:156-179 @1764167` - `summary()` (outcome `FloodCounted`, 253-278) + `addToSummary($n)`, `EventMeter::record($user->id, $n)`, `handleEvent($user, $eventType, [], $n)`, broadcast of `[...$latest, 'flood_count' => $n, 'message_id' => 'flood-'.uuid]` with `markFeedHandled()`, then `AlertBursts::grow()` |
| C8 | CONFIRMED | `FloodGate.php:176-177,186-209 @1764167` - on `REANNOUNCE` (and a non-empty tail) `takeNewest()`, `TwitchEvent::create()`, `addToSummary(-1)`, `burst_count`/`burst_started_at` set when `announced()` returns a burst, `fireAlert()` |
| C9 | CONFIRMED | `FloodGate.php:138-145,215-250 @1764167` - re-dispatch when `$n > 0 \|\| Cache::has(:last)`; `finish()` inserts tail with `BurstAbsorbed`, subtracts `count($tail)`, `handleTwitchEventOnLane()` when rest > 0, else `delete()`, forgets `tail, summary, tick, dropped, n` |
| C10 | CONFIRMED | `app/Services/StreamSessionService.php:206 @1764167` - `$current + $step * max(1, $times)`; the `$isCheer` blocks (176-190, after 210) do not reference `$times` |
| C11 | CONFIRMED | `app/Http/Controllers/StreamSessionController.php:443,455,471,473 @1764167` - COALESCE(...,1) in `event_counts`, follows, redemptions, cost x count; 290,325,358-359 - `flood_counted` = SUM over `flood_counted` rows, added to `total` |
| C12 | CONFIRMED | `app/Enums/DeliveryOutcome.php:52 @1764167` - `FloodCounted = 'flood_counted'`; `isScored()` (58-64) lists only the five delivery cases |
| C13 | CONTRADICTED | `php artisan test --filter=FloodGateTest` - 11 passed. Narrower than claimed in one item: `tests/Feature/FloodGateTest.php:132-138 @1764167` asserts the first `flood_counted` row has count 200 and that at least one matching `TwitchEventReceived` was dispatched (`assertDispatched` with a callback); it does not assert there is exactly one summary row or exactly one broadcast. Every other listed case is asserted as stated |
| C14 | CONFIRMED | `tests/Feature/StreamSessionDeliveryTest.php:163-181 @1764167` - follows 5001, `event_counts['channel.follow']` 5001, `no_target.flood_counted` 5000; `php artisan test --filter=StreamSessionDeliveryTest` - 7 passed |
| C15 | CONFIRMED | `resources/js/stores/overlayState.test.ts:18-35 @1764167` - 202 after Alice / flood 200 / Bob; second test names Viewer299; `npx vitest run` on it and `deliveryOutcome.test.ts` - 5 passed |
| C16 | UNVERIFIABLE | tagged [unverified] (fail-first runs against a modified tree) |

### Surface
Complete.

### Findings
- **F1** contradiction with the record - OL-2611-036's Unchanged line says every event in a burst still does the per-event work in `processStoredEvent()` ("row save, `EventMeter`, `refreshCachesForEvent()`, `LivingTitleService`, `StreamSessionService::handleEvent()`) and the `TwitchEventReceived` broadcast"; this change narrows that for follows and redemptions past `PASS_THROUGH` (`TwitchEventSubController.php:590-597 @1764167` returns before the row, and `FloodGate::apply()` at `FloodGate.php:156-179 @1764167` calls neither `refreshCachesForEvent()` nor `LivingTitleService`), without citing OL-2611-036 anywhere in the claim; a later claim should record the narrowing and say whether the skipped cache refresh and title scheduling are intended.
- **F2** scope - `FloodGate::withTail()` (`FloodGate.php:318-327 @1764167`) silently skips the kept-list write when the lock is not got within 2 s, so a counted event can be missing from the kept rows; no claim, Surface line or Risk line discloses this (C5 states the append unconditionally); record it in a later claim.
- **F3** scope - `FloodTick::handle()` (`app/Jobs/FloodTick.php:37-45 @1764167`) calls the new `FloodGate::forget()` (`FloodGate.php:149-154`) and drops the flood's cache state when the user no longer exists; no claim or Surface line accounts for this path or for `forget()`.

### Notes
- C13's "one summary" is implied by `FloodGate::summary()` reusing the cached row id (`FloodGate.php:255-257 @1764167`), but no test asserts it.
- Unchanged lines checked: `ProcessTwitchEvent`, `processStoredEvent()`, `UnifiedEventFeedService` and `useProcessEvent.ts` are not in the diff; `useProcessEvent.ts:35 @HEAD` gives `by` precedence over `byPath`, and `stores/overlayState.ts:68-74 @HEAD` already honoured `byPath`.
- Also ran `php artisan test --filter=DeliveryLedgerTest` - 15 passed.
