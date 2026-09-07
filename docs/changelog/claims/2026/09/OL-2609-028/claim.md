## OL-2609-028 - fix(twitch): the living title's pause is live in the header and the settings page, and a save after a pause writes again

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-028`

### Surface
- `app/Events/LivingTitleChanged.php` - new: `ShouldBroadcast` on `alerts.{twitch_id}`, `broadcastAs` `living-title.updated`
- `app/Services/LivingTitleService.php` - private `remember()` becomes public `apply()`, which saves and broadcasts; `sharedState()` added; `resume()` also clears `last_written`; docblock example tag corrected to `channel_game`
- `app/Http/Controllers/Settings/LivingTitleController.php` - `update()` writes through `apply()` and always clears `last_written`
- `app/Services/Bot/BotChatAdminService.php` - `titleSet()` and `titleOff()` write through `apply()`; `titleSet()` clears `last_written`
- `app/Http/Middleware/HandleInertiaRequests.php` - shares `livingTitle` (enabled, paused, paused_title) on every page
- `resources/js/composables/useLivingTitle.ts` - new: app-wide state, shared prop first, then the broadcast
- `resources/js/components/UserInfo.vue` - "Title paused" link under the stream status when paused
- `resources/js/pages/settings/Title.vue` - the pause banner and the error banner read the live state
- `resources/js/types/index.d.ts` - `livingTitle` on `AppPageProps`
- `tests/Feature/LivingTitleTest.php` - three tests added; two assertions narrowed from `assertNothingPushed` to `assertNotPushed(SyncLivingTitle)`

### Claims
- **C1** [code] `LivingTitleService::apply()` is the only method in the service that calls `setPreference()`, and after `save()` it broadcasts `LivingTitleChanged` with `livingTitle()` minus the `template` key when the user has a `twitch_id`.
- **C2** [code] `LivingTitleController::update()`, `LivingTitleService::resume()` and `BotChatAdminService::titleSet()` each set `last_written` to null. Corrects OL-2609-026 C14, where a save kept `last_written`.
- **C3** [code] `HandleInertiaRequests::share()` exposes `livingTitle` as `LivingTitleService::sharedState()`: exactly the keys `enabled`, `paused`, `paused_title`.
- **C4** [code] `useLivingTitle.ts` registers its Echo listener at most once per page lifetime (module-level `listening` flag) and never calls `Echo.leave()`.
- **C5** [code] `useLivingTitle.ts` resets its broadcast-derived state to null whenever the shared prop changes, so a page load's server answer wins over an earlier broadcast.
- **C6** [code] `UserInfo.vue` renders a `Link` to `/settings/title` reading "Title paused" only when `useLivingTitle().paused` is true.
- **C7** [test] `LivingTitleTest` "saving after a pause writes again even when the template renders the same string" saves an unchanged template over a paused state with `last_written` equal to the render and asserts one PATCH with that title. Written against the reported failure; it fails on the OL-2609-027 tree.
- **C8** [test] `LivingTitleTest` "a pause and a resume are both broadcast to the account, state only, never the template" asserts `LivingTitleChanged` is dispatched with `paused` true and no `template` key on a foreign `channel.update`, and with `paused` false on `resume()`.
- **C9** [test] `LivingTitleTest` "every app page carries the paused slice for the header" asserts `/settings/chat` shares `livingTitle.paused` true and no `livingTitle.template`.
- **C10** [unverified] The reported symptom: on production, after a dashboard edit paused the title, re-saving the template from `/settings/title` wrote nothing and `!ol title show` reported `last written` equal to the render.

### Unchanged
- `handleChannelUpdate()`'s pause rule (OL-2609-026 C10) is not in the diff; it reads `last_written` as before and a null there still means no pause.
- `useStreamState.ts` is not in the diff; the new composable shares its channel and is written so as not to disturb it (C4).
- `LivingTitleService::sync()` still compares against `last_written` and skips on equality; the fix is that the states a streamer acts from no longer carry a stale value into that comparison.

### Risk
A resume or a save after a pause now always writes once, even if the render equals what was on Twitch. Twitch answers that with a `channel.update` echoing the same title, which the pause rule recognises as ours.
