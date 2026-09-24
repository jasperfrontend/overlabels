## Audit of OL-2609-015 - fix(events): checkin row copy, replay-column alignment, popover label fallback

**Audited:** 2026-09-24
**Commit:** fd914d07ee841c2e40a968b1b158a1185c91a03b
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/EventsTable.vue:303-306 @fd914d07` - `externalEventLabels.checkin.checkin = 'checkin'`, read first by `kind()` at `:342`; the fallback it avoids, `eventLabel()` at `resources/js/composables/useEventColors.ts:97-106 @fd914d07`, builds `serviceLabel('checkin') + ' Checkin'` with `SERVICE_LABELS.checkin = 'Chat Checkin'` (`resources/js/utils/services.ts:17 @fd914d07`) = "Chat Checkin Checkin". Same at `EventsTable.vue:307-309 @HEAD` |
| C2 | CONFIRMED | `EventsTable.vue:367 @fd914d07` - `(p?.['event.from_name']) \|\| (p?.['event.user_name']) \|\| null`; `app/Services/External/Drivers/CheckinServiceDriver.php:74 @fd914d07` writes `'event.user_name' => (string) ($payload['chatter_display_name'] ?? '')`; the five donation drivers write `event.from_name` as `(string) (... ?? '')` (Kofi :71, StreamLabs :69, Fourthwall :78, BMAC :97, Throne :122). Same at `EventsTable.vue:374 @HEAD` |
| C3 | CONFIRMED | `EventsTable.vue:383-385 @fd914d07` - after `amount` and `if (tier) return tier;`, returns `p['event.place'] \|\| null`. @HEAD `:391-395` an `event.height` check sits between tier and place (OL-2609-051) |
| C4 | CONFIRMED | `EventsTable.vue:509-514 @fd914d07` - the Replay `<button>` has no `v-if`; `:class` carries `canReplay(event) ? '' : 'invisible'`; `openConfirm()` at `:201-202` still returns early on `!canReplay(event)`. Same at `:523-527 @HEAD` |
| C5 | CONTRADICTED (compound) | Code half CONFIRMED: `EventsTable.vue:528 @fd914d07` reads `event.label \|\| kind(event)` (same at `:540 @HEAD`); `DashboardController::mergeRecentEvents()` at `app/Http/Controllers/DashboardController.php:173-194 @fd914d07` sets `label` on Twitch rows only. Rationale half false: `UnifiedEventFeedService::paginate()` at `app/Services/UnifiedEventFeedService.php:81-83 @fd914d07` sends a `label` on every row (Twitch: mapped label; external: `$row->event_type`), present since `36842812` (2026-07-03); same at `:81 @HEAD` |

### Surface
Complete.

### Findings
- **F1** Claim contradicted - OL-2609-015 C5 says `UnifiedEventFeedService` sends no label, but `app/Services/UnifiedEventFeedService.php:81-83 @fd914d07` (and @HEAD) sets `label` to the raw `event_type` for external rows, so on `/dashboard/events`, `/dashboard/recents` and the token-authed feed (all fed by `paginate()`) the popover shows e.g. `Replay "checkin"` / `Replay "donation"` and `kind(event)` is never reached; the fallback only fires for external rows from `mergeRecentEvents()` (dashboard card). A later claim should restate which callers actually reach the fallback, or the popover should prefer `kind(event)` for external rows if that was the intent.
- **F2** Scope - the diff adds a code comment asserting the same falsehood, `resources/js/components/EventsTable.vue:526-527 @fd914d07` ("The dashboard and unified feed send external events without a server label"), still at `:538-539 @HEAD`; correct or remove it alongside F1.

### Notes
- No `[test]` claims; no tests run.
- Unchanged line 1 says "the confirm flow" is not in the diff; the popover's `Replay` span inside it is in the diff, but that change is C5 of this same claim, so not raised as a finding.
- `EventsTable.vue` was later changed by OL-2609-023 and OL-2609-051 (`6abb44b8`, adds `tower` labels and the `event.height` detail); none of the C1-C5 lines other than C3's ordering moved. `DashboardController.php` later changed by OL-2609-071, outside `mergeRecentEvents()` label lines.
