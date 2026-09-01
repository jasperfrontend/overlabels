## OL-2609-015 - fix(events): checkin row copy, replay-column alignment, popover label fallback

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-015`

### Surface
- `resources/js/components/EventsTable.vue` - three fixes to OL-2609-014's row rendering

### Claims
- **C1** [code] `externalEventLabels.checkin.checkin` is `'checkin'`, so a checkin row's kind tag no longer falls through to `eventLabel()` and reads "Chat Checkin Checkin".
- **C2** [code] `who()` for external events falls back from `event.from_name` to `event.user_name` with `||`, the key `CheckinServiceDriver::normalizeEvent()` writes its actor under (drivers cast absent values to `''`, which `??` would pass through).
- **C3** [code] `details()` for external events falls back to `event.place` after amount and tier, so a checkin row shows its place label.
- **C4** [code] The Replay pill renders on every row, with `invisible` instead of `v-if` when `canReplay(event)` is false, so the time column right-aligns identically on replayable and non-replayable rows.
- **C5** [code] The confirm popover text reads `event.label || kind(event)`, so callers that send no server label (`DashboardController::mergeRecentEvents()` for Twitch-only labels, `UnifiedEventFeedService` for none) never render `Replay ""`.

### Unchanged
- OL-2609-014 C5 said the Replay button renders on replayable rows; C4 here widens that to all rows without changing when it is interactive - `openConfirm()` still guards on `canReplay()`, and `canReplay()`, `replay()` and the confirm flow are not in the diff.
- The server side sends the same payloads it did: `DashboardController`, `UnifiedEventFeedService` and `CheckinServiceDriver` are not in the diff - all three fixes are read-side fallbacks in the component.
