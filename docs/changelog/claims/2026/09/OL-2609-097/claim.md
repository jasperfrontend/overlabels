## OL-2609-097 - fix(privacy): platform-wide sweep - encrypt user tokens, scrub Twitch payloads, finish account erasure, put a clock on viewer data

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-097`

### Surface
- `app/Casts/EncryptedOrNull.php` - new: encrypts a string column, reads an undecryptable value as null instead of throwing
- `app/Models/User.php` - `access_token` / `refresh_token` cast to `EncryptedOrNull`; import added
- `database/migrations/2026_09_18_160000_encrypt_user_twitch_tokens.php` - new: widens both columns to `text`, then encrypts existing values via the query builder
- `app/Services/TwitchTokenService.php` - new `revokeToken()`, new `$revokeUrl`, `ensureValidToken()` null-guards before `validateToken(string)`
- `app/Services/TwitchPayloadScrubber.php` - new: drops `top_predictors`, nulls the user trigram when `is_anonymous`
- `app/Http/Controllers/TwitchEventSubController.php` - scrubber called before avatar enrichment; `last_webhook_activity` / `webhook_challenge_received` / `webhook_error` cache writes and the `$webhookLog` array removed; JSON-parse failure logs `strlen($body)` instead of `$body`; import added
- `app/Services/UserDeletionService.php` - constructor injection; three-phase erasure; deletes `twitch_events` and `sessions` rows; revokes the Twitch grant; removes EventSub subscriptions, the Fourthwall webhook and R2 images best-effort; `redactAuditTrail` parameter
- `app/Http/Controllers/Settings/AccountController.php` - passes `redactAuditTrail: true`
- `app/Models/ExternalEvent.php` - `supporter_email_hash` and `private_metadata` removed from `$fillable` and `$casts`
- `app/Services/External/NormalizedExternalEvent.php` - `supporterEmail`, `supporterEmailHash`, `privateMetadata` properties and getters removed
- `app/Services/External/Drivers/BMACServiceDriver.php` - stops capturing the supporter email; still unsets it from the stored payload
- `app/Http/Controllers/Api/ExternalWebhookController.php` - stops writing the two columns
- `database/migrations/2026_09_18_170000_drop_supporter_email_columns_from_external_events.php` - new: drops both columns
- `app/Http/Controllers/Admin/AdminTwitchEventController.php` - both index branches project with `->through()`
- `app/Http/Controllers/Admin/AdminSessionController.php` - `ipLookup()` and the `Location` / `JsonResponse` imports removed
- `routes/admin.php` - `sessions.ip-lookup` route removed
- `app/Services/Location/ExtendedIpApi.php`, `app/Services/Location/ExtendedPosition.php`, `config/location.php` - deleted
- `composer.json`, `composer.lock`, `bootstrap/cache/packages.php`, `bootstrap/cache/services.php` - `stevebauman/location` removed
- `config/ban.php` - `block_by_country` hardcoded `false`
- `app/Providers/TelescopeServiceProvider.php` - donation/viewer parameter names and internal secret headers hidden
- `app/Http/Controllers/Settings/StreamLabsIntegrationController.php` - `donations.create` dropped from the requested OAuth scope
- `app/Services/Tts/TtsService.php` - `synthesize()` takes a `$scope`; `cacheKey()` is an HMAC over the app key including that scope
- `app/Jobs/SynthesizeAlertTts.php` - passes `broadcasterId` as the scope
- `routes/console.php` - new prunes for `checkins` (90d), `list_append_history` (90d), `admin_audit_logs` (730d); three model imports
- `resources/js/composables/useNormalizeEvent.ts` - `channel.cheer` tests `is_anonymous` first
- `resources/js/pages/admin/sessions/index.vue` - IP lookup dialog, handler, `IpLocation` interface and `Dialog` imports removed
- `resources/js/types/index.d.ts` - stale `access_token: any` removed from `User`
- `resources/js/pages/Privacy.vue` - corrected to match reality; `lastUpdated` bumped
- `resources/help/pages/your-data.md` - updated for encrypted tokens, the new retention rows, the completed erasure and the removed IP lookup
- `docs/deploy/database-backups.md` - `APP_KEY` mismatch note extended to user tokens
- `CLAUDE.md` - new "Privacy and data retention" section
- `tests/Feature/UserTokenEncryptionTest.php`, `tests/Feature/AccountErasureCompletenessTest.php`, `tests/Unit/TwitchPayloadScrubberTest.php` - new files
- `tests/Feature/BMACWebhookTest.php`, `tests/Unit/BMACServiceDriverTest.php` - assertions inverted to pin non-retention

### Claims
- **C1** [code] `User::casts()` maps `access_token` and `refresh_token` to `App\Casts\EncryptedOrNull::class`.
- **C2** [code] `EncryptedOrNull::get()` returns null for a value `Crypt::decryptString()` rejects, rather than propagating `DecryptException`.
- **C3** [code] `EncryptedOrNull::set()` stores null for a null or empty input, so clearing a token does not write ciphertext of an empty string.
- **C4** [code] The migration issues `ALTER TABLE users ALTER COLUMN access_token TYPE text` and the same for `refresh_token` BEFORE encrypting any value.
- **C5** [unverified] Production columns were `character varying(255)` when read on 2026-09-18. An encrypted Twitch token exceeds that, and Postgres rejects rather than truncates, so encrypting without the widen would have failed every write to those columns.
- **C6** [code] The migration's `encryptOnce()` returns null for a value that already decrypts, making the migration re-runnable without double-encrypting.
- **C7** [code] The migration uses `DB::table('users')` throughout and references no Eloquent model, so `UserObserver` does not fire and `updated_at` is not touched.
- **C8** [test] `UserTokenEncryptionTest` asserts the raw column value differs from the plaintext and decrypts back to it, that a legacy plaintext value reads as null, and that neither column has a `character_maximum_length`.
- **C9** [code] `TwitchPayloadScrubber::scrub()` removes `top_predictors` at any depth and nulls `user_id`, `user_login`, `user_name`, `user_avatar` when `is_anonymous` is truthy. `top_contributions` is not in `DENIED_KEYS`.
- **C10** [code] `TwitchEventSubController` calls `TwitchPayloadScrubber::scrub($event)` before `enrichEventWithUserAvatars()`, so no avatar lookup is made for a removed viewer.
- **C11** [test] `TwitchPayloadScrubberTest` asserts prediction wagers are dropped, an anonymous cheer keeps its bits but loses every identity field, a named cheer is untouched, a follow payload is returned byte-identical, and hype train contributions survive.
- **C12** [code] `useNormalizeEvent.ts` branches on `e?.is_anonymous` before reading `user_name`. The previous expression `e?.user_name ?? (e?.is_anonymous ? 'Anonymous' : e?.user_name)` could never reach its anonymity branch when a name was present.
- **C13** [code] `UserDeletionService::eraseAccount()` deletes `TwitchEvent::where('user_id', ...)` and `DB::table('sessions')->where('user_id', ...)` inside the transaction. Neither is reached by a database cascade: `twitch_events.user_id` is `nullOnDelete` and `sessions.user_id` has no foreign key.
- **C14** [code] `eraseAccount()` calls `TwitchTokenService::revokeToken()`, `UserEventSubManager::removeUserSubscriptions()`, `FourthwallApiClient::deregisterWebhook()` and `ImageUploadService::deleteByUrl()`, each wrapped in `attempt()`, which catches `Throwable`, logs, and does not re-raise.
- **C15** [code] `TwitchTokenService::revokeToken()` POSTs `client_id` and `token` to `https://id.twitch.tv/oauth2/revoke` and treats HTTP 400 as success, because Twitch returns 400 for a token it does not recognise.
- **C16** [code] The external cleanup steps run AFTER the transaction commits; the values they need are read before it opens.
- **C17** [code] `redactAuditTrail` defaults to false. `AccountController::destroy()` passes true; `AdminUserController` does not.
- **C18** [test] `AccountErasureCompletenessTest` asserts the revoke request is sent with the user's token, that twitch event and session rows are gone, that image rows are gone, that audit metadata is redacted on the self-serve path and intact on the admin path, and that the user row is still deleted when every outbound call returns 500.
- **C19** [code] `external_events` no longer has a `supporter_email_hash` or `private_metadata` column, `ExternalEvent` no longer declares them, and `NormalizedExternalEvent` no longer carries `supporterEmail`, `supporterEmailHash` or `privateMetadata`.
- **C20** [code] `AdminTwitchEventController::index()` projects both branches through `->through()` to the exact fields `admin/events/index.vue` declares, so no `raw_payload`, `event_data` or decrypted column reaches that page's Inertia payload.
- **C21** [test] `BMACWebhookTest` asserts the stored row has no `private_metadata` or `supporter_email_hash` key and that neither the address nor its sha256 appears anywhere in it.
- **C22** [code] No route, controller method, config file, service class or composer package for IP geolocation remains: `sessions.ip-lookup`, `AdminSessionController::ipLookup()`, `config/location.php`, `ExtendedIpApi`, `ExtendedPosition` and `stevebauman/location` are all removed, and `config('ban.block_by_country')` is the literal `false`.
- **C23** [code] `routes/console.php` schedules daily prunes deleting `Checkin` rows with `checked_in_at` older than 90 days, `ListAppendHistory` rows with `fired_at` older than 90 days, and `AdminAuditLog` rows with `created_at` older than 730 days. None of these three tables had any sweep before.
- **C24** [code] `TwitchEventSubController` contains no `Cache::put('last_webhook_activity', ...)` call and no `$webhookLog` variable, and the JSON-parse failure log carries `bytes`, not `body`.
- **C25** [code] The Streamlabs authorize URL requests `socket.token donations.read`. No code in `app/` calls a Streamlabs donation-creation endpoint.
- **C26** [code] `TtsService::cacheKey()` is `hash_hmac('sha256', text|voice|model|scope, config('app.key'))`, and `SynthesizeAlertTts` passes the broadcaster id as the scope. The mp3 path is therefore no longer derivable from the alert sentence, which it was when voice and model were app-wide constants and the hash was a bare sha256.
- **C27** [code] The TTS path is still deterministic for the same broadcaster and sentence, so the existing dedup that avoids paying ElevenLabs twice is unchanged.
- **C28** [unverified] Production `external_events` were inspected by key name only on 2026-09-18: all 21 Ko-fi rows carried a non-empty `email` and `verification_token`, 6 carried `discord_username`, and `shipping` was present on all 21 and JSON-null on all 21.

### Unchanged
- `BMACServiceDriver`'s explicit `unset()` of `supporter_email`, `shipping_address`, `total_amount_charged` and `commission.shipping_address` stays as it was. It predates `PayloadScrubber`, is test-pinned, and removing it in favour of the shared scrubber would be a refactor of working code inside a security change.
- `GpsServiceDriver`, `CheckinServiceDriver` and `TowerServiceDriver` still pass their payload to `raw:` unscrubbed. GPS rows are read back out of `raw_payload` by `GpsSessionAggregator` and friends, and the other two build their payloads first-party from bot input that carries no contact or postal fields.
- Check-in pins still survive an integration disconnect. `CheckinIntegrationController` documents that as deliberate so reconnecting does not wipe a globe; the new 90-day prune bounds it instead. Not in the diff.
- `twitch_events.event_data` still stores the rest of the Twitch payload verbatim, including resub message text and channel-point `user_input`. Those are what alerts render, and they are already public in chat; only the two categories in C9 were removed.
- `App\Services\Location\GeoMath` is first-party haversine maths used by GPS and Checkin. It shares a namespace with the deleted ip-api drivers and is not in the diff.
- `overlay_access_logs` still records IP and user agent, on its existing 90-day prune. It is the record that answers "what is using this overlay token", which is a feature the tokens page exposes to the user.
- `users.twitch_data` still holds the login-time snapshot of followers, subscribers and goals. It is the streamer's own channel data, refreshed on each login and deleted with the account.

### Risk
Existing cached TTS mp3s become unreachable and are regenerated on next use, then swept by the
existing 7-day job. No alert breaks; the first repeat of each sentence costs one API call again.

Two migrations rewrite data and neither is reversible. Existing users' tokens are encrypted in place;
the columns are widened first, and the cast is lenient, so the worst case for a row written as
plaintext during the rolling deploy is that the user re-authorizes. `external_events` loses two
columns and their contents permanently.

Deleting an account now performs outbound calls to Twitch and Fourthwall. All are best-effort and
cannot fail the deletion. A user who deletes their account will find Overlabels already gone from
twitch.tv/settings/connections rather than needing to remove it themselves.

Streamlabs users who reconnect will be asked for one scope fewer. Existing grants are unaffected.
