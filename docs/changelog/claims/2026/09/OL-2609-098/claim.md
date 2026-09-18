## OL-2609-098 - feat(help): /viewers, a notice page written for the people whose data we hold

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-098`

### Surface
- `resources/help/pages/viewers.md` - new guide, `section: Getting started`, no `context:`
- `resources/help/pages/index.md` - linked under `### Getting started`
- `routes/web.php` - `Route::redirect('/viewers', '/help/viewers', 301)`

### Claims
- **C1** [code] `/viewers` answers 301 to `/help/viewers`. It is a `Route::redirect`, not a controller, and follows the existing `/manifesto` precedent three lines above it.
- **C2** [code] The page is an ordinary help corpus entry, so it gets the route, the `.md` twin, the sitemap entry, the search index entry and the sidebar placement with no new table, controller or component.
- **C3** [code] `viewers.md` declares `section: Getting started`, one of `HelpCorpus::SECTIONS`, and declares no `context:`, so the 40-character heading and 320-character lead caps do not apply to it.
- **C4** [code] The `heading` contains no apostrophe. `HelpPageTest` asserts the raw heading string appears in the response body, and an apostrophe is HTML-escaped on render, so a heading carrying one fails that test. The first draft did, and was changed.
- **C5** [test] `php artisan test --filter=Help` passes, 74 tests, which covers the unlinked-page check, the slug pattern, the section taxonomy and the byte-for-byte body assertion.
- **C6** [code] The removal route the page gives is an email address to `privacy@overlabels.com`. No `!forgetme` command, chat notice or self-service endpoint exists, and the page does not claim one does.
- **C7** [code] Every retention figure on the page matches `routes/console.php` as of OL-2609-097: check-ins 90 days from `checked_in_at`, twitch and external events 90 days, TTS audio 7 days, backups 30 days.
- **C8** [code] The page states that chat messages do not reach the server, with the single `latest_chat_message` control named as the exception. That matches `useTwitchChat.ts` (direct anonymous IRC) and `StreamSessionService::applyChatStats()`.
- **C9** [code] The page states anonymous cheers stay anonymous and prediction wagers are discarded, both of which are `TwitchPayloadScrubber` behaviour shipped in OL-2609-097.

### Unchanged
- `resources/js/pages/Privacy.vue` is the formal policy and is not in this diff. This page is a plain-language notice for a different audience and does not replace it; OL-2609-097 already corrected the policy itself.
- No bot change. The design this page belongs to - the bot replying once with a notice link, plus a `!forgetme` opt-out - is not built. This is the page that design points at, shipped on its own because it is useful the moment a streamer needs somewhere to send someone.
- `/help/your-data` keeps the full inventory and is linked from the bottom of this page rather than being split up.

### Risk
None to running code. The page publishes a commitment to act on emailed removal requests, which is a
process obligation rather than a code one.
