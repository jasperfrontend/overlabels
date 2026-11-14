## OL-2611-013 - perf(twitch): answer the webhook first, process on a dedicated twitch-events worker

**Shipped:** 2026-11-14
**Commit:** `git log --grep=OL-2611-013`

### Surface
- `app/Http/Controllers/TwitchEventSubController.php` - `handleTwitchEvent()` keeps the poll winners, the scrub and the duplicate-guarded insert, then dispatches `ProcessTwitchEvent`; token refresh, avatar enrichment and everything after the insert move to the new public `processStoredEvent()`
- `app/Jobs/ProcessTwitchEvent.php` - new: queued on `twitch-events`, `tries` 1, calls `processStoredEvent()`
- `config/deploy.yml` - new `events` role: one `queue:work --queue=twitch-events` process
- `tests/Feature/TwitchWebhookAnswersFirstTest.php` - new: 4 tests
- `tests/Feature/EventSubWebhookErrorLoggingTest.php` - an Error during enrichment now leaves the stored row (count 1, was 0)
- `tests/Feature/LivingTitleTest.php` - `Queue::fake()->except([ProcessTwitchEvent::class])` in place of `Queue::fake()`

### Claims
- **C1** [code] `handleTwitchEvent()` makes no call to `TwitchApiService` and does not call `refreshExpiredToken()`; it ends by dispatching `ProcessTwitchEvent` with the stored row's id and the scrubbed `$data`.
- **C2** [code] `TwitchPayloadScrubber::scrub()` still runs in `handleTwitchEvent()` before `TwitchEvent::create()`, so the stored row never holds unscrubbed data.
- **C3** [code] A `UniqueConstraintViolationException` on insert returns before dispatching, so a redelivered notification queues no job.
- **C4** [code] `processStoredEvent()` writes the enriched event back to the row with `forceFill(['event_data' => $event])->save()` before metering, counters and the alert.
- **C5** [code] `ProcessTwitchEvent::QUEUE` is `twitch-events`, set in the constructor via `onQueue()`, with `$tries = 1`.
- **C6** [code] `config/deploy.yml` contains exactly one `--queue=twitch-events`, on the `events` role, with no `--processes` or `--workers` option.
- **C7** [test] `TwitchWebhookAnswersFirstTest` "the webhook stores the event and queues the work instead of doing it" asserts the row is stored, `enrichEventWithUserAvatars` and `getExtendedUserData` are not called, no `AlertTriggered` fires, and `ProcessTwitchEvent` is pushed on `twitch-events` for that row.
- **C8** [test] `TwitchWebhookAnswersFirstTest` "a redelivered notification queues no second job" asserts one push for two posts with the same message id.
- **C9** [test] `TwitchWebhookAnswersFirstTest` "the queued job does the rest" asserts the job writes the enrichment onto the row and fires one `AlertTriggered`.
- **C10** [unverified] C7 and C8 failed with the controller change stashed.
- **C11** [unverified] On prod (2026-11-14), before this change: a follow webhook took about 1.4 s and a raid about 0.7 s of server time to answer (round trip minus a 0.13 s rejected-request baseline), and 30 follows posted in parallel took 17 s to process, with 14 of the 30 CLI calls receiving no 200.

### Unchanged
- `renderEventAlert()`, `GiftSubAbsorber` and the alert path inside the moved block are moved verbatim, not rewritten.
- The `queue` role's command is untouched, so TTS, broadcasts and the other jobs keep their worker.
- `testCheer()` and replay call `renderEventAlert()` directly and still run synchronously; they are not webhook paths.

### Risk
Every Twitch alert now depends on the `events` container. If it is not running, notifications are
stored and answered but nothing processes them until it is. A notification is now on record even
when its processing later fails. Alerts can trail the webhook by up to the worker's 1 s idle poll.
