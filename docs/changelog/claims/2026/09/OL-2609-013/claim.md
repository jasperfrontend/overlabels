## OL-2609-013 - feat(checkin): farthest_checkin_name_this_stream names the record holder

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-013`

### Surface
- `app/Services/External/Drivers/CheckinServiceDriver.php` - new control in `getAutoProvisionedControls()`, `PER_STREAM_CONTROL_KEYS`, name update in `beforeControlUpdates()`
- `database/migrations/2026_09_02_100000_backfill_farthest_checkin_name_control.php` - new file, backfill for existing checkin integrations
- `resources/js/components/controls/controlPresets.ts` - `CHECKIN_PRESETS` entry
- `resources/help/pages/checkin.md` - controls list + "who came furthest" example now shows the pair
- `resources/help/reference/eventsub-tags/all-chat-checkin-events.md` - control list in the tags-vs-controls note
- `resources/help/reference/integration-controls/checkin.md` - regenerated (`help:build-integration-controls`), 10 -> 11 controls
- `resources/help/reference/integration-controls/all-integration-controls.md` - regenerated, same run
- `tests/Feature/BotCheckinTest.php` - name assertions in the record test, two new tests

### Claims
- **C1** [code] `getAutoProvisionedControls()` declares `farthest_checkin_name_this_stream` as a text control with `config: ['reset_value' => '']`, so the go-live reset in `StreamSessionService::resetCheckinControls()` writes `''` into it, never the literal string `"0"` (the `latest_cheerer_name` lesson - `"0"` is falsy in conditionals but renders verbatim from a bare tag).
- **C2** [code] `beforeControlUpdates()` sets the name only when the incoming distance strictly beats the current `farthest_checkin_this_stream` value, so distance and name move together, a tie keeps the original record holder, and a replayed record event changes nothing.
- **C3** [code] The key is on `CheckinServiceDriver::PER_STREAM_CONTROL_KEYS` (now four keys), so the existing reset path resets it at go-live with no change to `StreamSessionService`.
- **C4** [code] The backfill migration inserts the row via `DB::table` literals (never a model) for every user with a checkin `external_integrations` row that lacks it; new connections get it from `provision()` at connect. Without the backfill, `applyUpdates()` would silently drop the name update for already-connected users, and the editor autocomplete (driven by the account's live control rows) would never offer the tag.
- **C5** [test] `BotCheckinTest` pins all three behaviors: the name follows a new record (`a farther checkin takes the record name with it`), stands when a closer checkin arrives (extended `farthest checkin only ever moves up`), and resets to empty - asserted `''`, not `'0'` (`the go-live reset clears the farthest name to empty, never the string zero`).
- **C6** [code] `resources/help/reference/integration-controls/checkin.md` is generator output; the hand edits are `pages/checkin.md` and `all-chat-checkin-events.md` only.

### Unchanged
- The `latest_checkin_*` set still persists across streams; no other key joined or left the reset list.
- No event tag changes - `normalizeEvent()` and the `event.*` tags are untouched; this is running state, not the single event.
- `ExternalControlService`, `StreamSessionService`, and the reset broadcast path are untouched - the new key rides the existing constants.

### Risk
Templates written before this ship render `[[[c:checkin:farthest_checkin_name_this_stream]]]` as empty until a stream's first record-setting checkin, which is the normal empty-renders-as-nothing contract, not a break.
