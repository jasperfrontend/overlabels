## Audit of OL-2611-011 - feat(alerts): alerts take turns, and a gift bomb is one alert

**Audited:** 2026-11-14
**Commit:** 817413395df625896ffcb2e19b6f14daf154d03a (HEAD at audit time; `git diff 8174133 HEAD` is empty, so every @HEAD reading equals @8174133)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/OverlayRenderer.vue:1824 @8174133` - `handleAlertTriggered()` (from :1749) ends in `alertQueue.enqueue({...})`; the only `showAlert(` call is :558 inside the queue's `show` callback; same at @HEAD |
| C2 | CONTRADICTED | `resources/js/utils/alertQueue.test.ts:31-47 @8174133` - pins the timing of the first hand-over only: `hide` at 6000 ms (:40) and `b` shown at 6500 ms, not at 6000 (:41-42). For `c` it asserts only that `c` is the latest show at 13000 ms (:43-44) and the final order show/hide/show/hide/show/hide (:46); neither `b`'s duration nor the gap before `c` is asserted. Test passes (`npx vitest run`, 12/12 in file) |
| C3 | CONFIRMED | `alertQueue.test.ts:49-64 @8174133` - shows `first`, `early-stamp`(102), `same-a`(103), `same-b`(103), `late-stamp`(105); passes |
| C4 | CONFIRMED | `alertQueue.test.ts:66-78 @8174133` - log empty while `fetched` is pending (:71), `fetched` shown on resolve (:75), `inline` after (:77); passes |
| C5 | CONFIRMED | `resources/js/utils/alertQueue.ts:30-31 @8174133` - `MAX_WAITING = 25`, `MAX_WAIT_MS = 180_000`; :196-199 shifts `waiting[0]` (earliest by timestamp) while `length > maxWaiting` and calls `drop(..., 'overflow')`; :102-105 drops a head with `now() - enqueuedAt > maxWaitMs` as `'stale'`; `drop()` (:90) calls `options.onDrop`; same at @HEAD |
| C6 | CONFIRMED | `alertQueue.test.ts:119-130 @8174133` - second keyed enqueue at 4000 ms re-shows (`show poll 40%`), no `hide` at 8000 ms, `hide` at 10000 ms; passes |
| C7 | CONFIRMED | `OverlayRenderer.vue:1822 @8174133` - `key` is `${eventType}:${alertSlug}` only when `eventType.endsWith('.progress')`, else `undefined`; no other `key` passed to `enqueue`; same at @HEAD |
| C8 | CONFIRMED | `OverlayRenderer.vue @8174133` - `playAlertSound(` is called at :572 (queue `show` callback) and :1813 (the branch after `plan.kind` is neither `ready` nor `fetch`), nowhere else; same at @HEAD |
| C9 | CONFIRMED | `OverlayRenderer.vue:1976-1977 @8174133` - `entry.shownAt === null` stores `entry.audioUrl` and returns; `markTtsShown()` plays it via `scheduleTts(entry.audioUrl, ...)` at :1945; `entry?.dropped` deletes and returns at :1971; same at @HEAD |
| C10 | CONFIRMED | `app/Events/AlertTriggered.php:151 @8174133` - `'event_type' => $this->eventType`; constructor default `?string $eventType = null` at :78; same at @HEAD |
| C11 | CONFIRMED | `tests/Feature/AlertEventTypeBroadcastTest.php:44-72 @8174133` - dataset `channel.poll.progress` and `channel.cheer`, asserts `event_type === $type` and no `event.type` key in `data`; `php artisan test --filter=AlertEventTypeBroadcastTest`: 3 passed |
| C12 | UNVERIFIABLE | tagged [unverified] (fail-first run against a tree that no longer exists) |
| C13 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:615-618 @8174133` - `channel.subscribe` with `absorb()` true records `DeliveryOutcome::GiftAbsorbed`; the `elseif` holding `resolveForEvent()`/`renderEventAlert()` is skipped; same at @HEAD |
| C14 | CONFIRMED | `app/Services/Alerts/GiftSubAbsorber.php:51 @8174133` - `if (($event['is_gift'] ?? false) !== true) return false;`; same at @HEAD |
| C15 | CONFIRMED | `TwitchEventSubController.php:624-629 @8174133` - the only `expect(` call in `app/` sits under `if ($mapping && $mapping->template)` and `if ($eventType === 'channel.subscription.gift')`; same at @HEAD |
| C16 | CONFIRMED | `GiftSubAbsorber.php:31,42,60,82 @8174133` - `WINDOW_SECONDS = 120`; key `'gift_absorb:'.$user->id`; `Cache::put(..., self::WINDOW_SECONDS)` on every `expect()` with `total > 0` (:36 returns earlier for `<= 0`) and on every accepted `absorb()` (a rejected absorb writes nothing); same at @HEAD |
| C17 | CONFIRMED | `tests/Feature/GiftSubAbsorbTest.php:164-178 @8174133` - alerts equal `['channel.subscription.gift']`, three `channel.subscribe` rows, each outcome `GiftAbsorbed`; `--filter=GiftSubAbsorbTest`: 6 passed |
| C18 | CONFIRMED | `GiftSubAbsorbTest.php @8174133` - non-gifted during countdown :192-200, no gift mapping :202-211, past the total :180-190, after the window :213-222; all pass |
| C19 | UNVERIFIABLE | tagged [unverified] (prod observation) |

### Surface
Complete.

### Findings
- **F1** test narrower than claim - C2 says `alertQueue.test.ts` "plays back-to-back alerts..." asserts each alert follows the previous one's duration plus the gap, but `resources/js/utils/alertQueue.test.ts:43-46 @8174133` checks only the order of `c` and an upper bound on when it shows, so a short `b` duration or a missing gap before `c` would still pass; either assert `c`'s timing like `b`'s (:40-42) or record the narrower statement.
- **F2** reverses an earlier claim without citing it - OL-2611-005 C14 records "`OverlayRenderer.vue` drops a fetched source if a newer alert fired while the fetch was in flight (`alertSequence`)"; this commit deletes `alertSequence` (`OverlayRenderer.vue:135,1764,1788 @8174133~1`, absent @8174133) and a fetched alert now keeps its place in line (C4), but neither the claim nor its Surface line cites OL-2611-005 C14; a follow-up claim should record that it supersedes that line.
- **F3** scope - `OverlayRenderer.vue:1918 @8174133` raises `PENDING_TTS_MAX_AGE_MS` from `60_000` (`:1879 @8174133~1`) to `300_000`, so a pending TTS entry now lives five minutes instead of one; no claim, Surface line or Unchanged line mentions it; record it in a follow-up claim.

### Notes
- Tests run: `npx vitest run resources/js/utils/alertQueue.test.ts resources/js/utils/deliveryOutcome.test.ts` (15 passed); `php artisan test --filter=` for AlertEventTypeBroadcastTest (3), GiftSubAbsorbTest (6), DeliveryLedgerTest (15), StreamSessionDeliveryTest (6), all passed.
- Running those four Pest files by path instead of `--filter` fails 8 cases, e.g. DeliveryLedgerTest:173 "Call to undefined function makeKofiIntegration()": helpers live in other test files. Not introduced by this commit.
- Unchanged lines checked: the `useGiftBombDetector` lines (`OverlayRenderer.vue:39,140,1390 @8174133`) are outside every hunk; `ExternalAlertService.php:77`, `ExternalEventController.php:81` and `EventTemplateMapping` are not in the diff.
- `docs/design/event-delivery-ledger-2026-08.md` still lists four stored `no_target` reasons without `gift_absorbed`; it is a dated design doc, so not a finding.
