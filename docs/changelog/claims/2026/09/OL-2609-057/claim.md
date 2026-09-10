## OL-2609-057 - fix(controls): one query, one roll - a random-mode control resolves once per list append and once per alert

**Shipped:** 2026-09-10
**Commit:** `git log --grep=OL-2609-057`

### Surface
- `app/Support/ControlSnapshot.php` - new file; one user's controls resolved once, `values` and `rolls`
- `app/Services/Messages/AlertMessageRenderer.php` - `renderAlert()` added, `resolve()` split into `resolve()` + `resolveWith()`, `loadControls()` removed
- `app/Services/Bot/BotCommandResolver.php` - `resolve()` gains an optional `ControlSnapshot`, `loadControls()` removed
- `app/Services/Lists/ListAppendService.php` - `fire()` builds one snapshot for the value template and the success reply
- `app/Services/External/ExternalAlertService.php` - `dispatch()` calls `renderAlert()` in place of `render()` + `renderMessage()`
- `app/Http/Controllers/ExternalEventController.php` - `replay()` calls `renderAlert()` in place of `render()` + `renderMessage()`
- `app/Http/Controllers/TwitchEventSubController.php` - `renderEventAlert()` calls `renderAlert()` in place of `render()` + `renderMessage()`
- `tests/Feature/AlertRandomSnapshotTest.php` - new file
- `tests/Feature/ListAppendRandomSnapshotTest.php` - new file

### Claims
- **C1** [code] `ControlSnapshot::for()` calls `OverlayControl::resolveDisplayValue()` exactly once per control row of the user, keying `values` by `broadcastKey()` for `source_managed` rows and by `key` otherwise.
- **C2** [code] `ControlSnapshot::$rolls` holds `c:<identifier>` => value for exactly the rows where `OverlayControl::isRandom()` is true.
- **C3** [code] `AlertMessageRenderer::renderAlert()` builds one `ControlSnapshot` and resolves both the TTS message and the chat message against that snapshot's `values`.
- **C4** [code] `renderAlert()` returns `data` equal to `$templateData` merged with the snapshot's `rolls`, rolls winning on a key collision.
- **C5** [code] `renderAlert()` applies `isGatedOff()` to `tts` only; `chat` is returned regardless of the `tts` gate control.
- **C6** [code] `TwitchEventSubController::renderEventAlert()`, `ExternalAlertService::dispatch()` and `ExternalEventController::replay()` each call `renderAlert()` once, pass its `data` to `AlertTriggered`, and no longer call `render()` or `renderMessage()`.
- **C7** [code] No caller of `AlertMessageRenderer::render()` or `renderMessage()` remains under `app/`.
- **C8** [code] `BotCommandResolver::resolve()` takes an optional named `snapshot`; when null it builds its own `ControlSnapshot`, and in both cases `loadLists()` and `loadTwitchTags()` still run per call.
- **C9** [code] `ListAppendService::fire()` builds one `ControlSnapshot` before resolving `value_template` and passes the same instance to the `success_reply` resolve, which still happens after the append.
- **C10** [code] `OverlayRenderer.vue` `handleAlertTriggered()` merges `{ ...data.value, ...alertData.data }`, so a `c:` key carried in the alert payload overrides the client's locally ticked random value for that alert.
- **C11** [test] `AlertRandomSnapshotTest` has three tests, one per site in C6, each asserting that the `SynthesizeAlertTts` job text, the `BotChatOutbox` message and `AlertTriggered::$data['c:random1000']` carry the same number.
- **C12** [test] `ListAppendRandomSnapshotTest` asserts, across five fires, that the number in the appended value equals the number in the success reply, and that two occurrences of the tag within one reply agree.
- **C13** [test] `BotListTagsTest` "it reports the post-append count in the success reply" passes, pinning that a success reply reads Lists after the append.
- **C14** [unverified] Against the pre-fix tree, `ListAppendRandomSnapshotTest` reported 5 of 5 fires mismatched and `AlertRandomSnapshotTest` failed at all three sites on the TTS-versus-chat assertion.

### Unchanged
- `rand:<min>-<max>` is resolved in `BotCommandResolver::lookup()` before the control map is consulted and rolls per occurrence; that branch is not in the diff.
- `BotCommandResolver::loadLists()` picks `c:list:<slug>:random` with `array_rand()` on every call, and C8 keeps it per call, so a list appender's value and reply can still name different random items; `loadLists()` is not in the diff.
- `OverlayControl::resolveDisplayValue()` and `isRandom()` are what the snapshot reads; neither is in the diff.
- `OverlayRenderer.vue` is what C10 relies on; it is not in the diff.
- `AlertMessageRenderer::render()` and `renderMessage()` keep their signatures and still take their own snapshot each, for the tests that call them directly.

### Risk
- Every alert payload now carries one `c:<key>` entry per random-mode control the user owns, whether or not the alert template references it. A few bytes each against Reverb's 10 KB ceiling.
- An alert template's `[[[c:<random key>]]]` now renders the server's roll for that alert instead of the overlay's locally ticked value at fire time.
