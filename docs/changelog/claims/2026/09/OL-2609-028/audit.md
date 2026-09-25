## Audit of OL-2609-028 - fix(twitch): the living title's pause is live in the header and the settings page, and a save after a pause writes again

**Audited:** 2026-09-24
**Commit:** 4ea48af60cd4df648c220bd058b126d9e91846f0
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/LivingTitleService.php:345 @4ea48af` - the only `setPreference()` call in the file is inside `apply()` (`:342`); `save()` at `:348`, then `if ($user->twitch_id)` at `:350`, `unset($settings['template'])` at `:352`, `broadcast(new LivingTitleChanged(...))` at `:353`; `$settings` is `User::livingTitle()`, which carries a `template` key (`app/Models/User.php:355 @HEAD`). File unchanged @4ea48af..HEAD |
| C2 | CONFIRMED | `app/Http/Controllers/Settings/LivingTitleController.php:70-76 @4ea48af` passes `'last_written' => null` to `apply()` unconditionally; `LivingTitleService.php:305 @4ea48af` `resume()` passes `'last_written' => null`; `app/Services/Bot/BotChatAdminService.php:105-111 @4ea48af` `titleSet()` passes `'last_written' => null`. Cites OL-2609-026 C14 inline. All three unchanged @HEAD |
| C3 | CONFIRMED | `app/Http/Middleware/HandleInertiaRequests.php:105-108 @4ea48af` shares `livingTitle` as `LivingTitleService::sharedState($user)` (null when logged out); `LivingTitleService.php:364-372 @4ea48af` returns exactly `enabled`, `paused`, `paused_title`. The closure is unchanged @HEAD (the file was later edited by OL-2609-042 and OL-2609-087 elsewhere) |
| C4 | CONFIRMED | `resources/js/composables/useLivingTitle.ts:17 @4ea48af` module-level `let listening = false`; `:49-52` registers `.living-title.updated` only when `!listening`, then sets it true; no `Echo.leave(` call in the file (the only occurrence is the comment at `:25`). Unchanged @HEAD |
| C5 | CONFIRMED | `useLivingTitle.ts:37-38 @4ea48af` - `watch(serverState, () => { wsState.value = null; })`, where `serverState` is `computed(() => page.props.livingTitle ?? null)`. Unchanged @HEAD |
| C6 | CONFIRMED | `resources/js/components/UserInfo.vue:15 @4ea48af` binds `paused` from `useLivingTitle()` as `titlePaused`; `:47-55` renders `<Link v-if="titlePaused" href="/settings/title">` with text `Title paused`. Unchanged @HEAD |
| C7 | CONFIRMED | `tests/Feature/LivingTitleTest.php:358 @4ea48af` seeds `last_written` `1234 followers`, `paused` true, PATCHes the same template, asserts `$patches` is exactly `[['title' => '1234 followers']]`. Passed (`php artisan test --filter=LivingTitleTest`, 35 passed, 129 assertions, test file identical @HEAD). Fail-first half checked by reading: the parent `9b046839` (OL-2609-027) `LivingTitleController::update()` cleared `last_written` only when `! $enabled`, so `sync()`'s equality skip (`LivingTitleService.php:239`) would leave `$patches` empty |
| C8 | CONFIRMED | `LivingTitleTest.php:323 @4ea48af` - `Event::assertDispatched(LivingTitleChanged)` with `broadcasterId` `73327367`, `paused` true, `paused_title` `Typed on Twitch`, no `template` key after a foreign `channel.update`; then `paused` false after `resume()`. Passed in the same run |
| C9 | CONFIRMED | `LivingTitleTest.php:342 @4ea48af` - GET `/settings/chat`, asserts `livingTitle.enabled` true, `livingTitle.paused` true, `livingTitle.paused_title`, and `missing('livingTitle.template')`. Passed in the same run |
| C10 | UNVERIFIABLE | tagged [unverified]; a production observation |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged lines 1 and 3: `handleChannelUpdate()` and `sync()` both appear in the diff (`LivingTitleService.php:292` and `:224,230,244,256,265 @4ea48af`, `remember()` renamed to `apply()`), but only at their write calls, which Surface discloses. The pause condition (`:282`, `:288`) and the equality skip (`:239`) are not in any hunk.
- `resources/js/pages/settings/Title.vue` was edited later by OL-2609-030 (df0b433b), which touches the "On Twitch right now" markup and not the pause/error banners this claim describes.
- C5's `watch` is set up once per calling component and is not `immediate`. That matches the claim as written. Whether a new page load always resets state when the calling components remount was not checked at runtime.
