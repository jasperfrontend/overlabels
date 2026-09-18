## Audit of OL-2609-097 - fix(privacy): platform-wide sweep - encrypt user tokens, scrub Twitch payloads, finish account erasure, put a clock on viewer data

**Audited:** 2026-09-18
**Commit:** `8ad02ed3`, `693d8538` (two commits carry the `Changelog: OL-2609-097` trailer; audited as one diff)
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Models/User.php:226-227 @8ad02ed3` - `'access_token' => EncryptedOrNull::class`, `'refresh_token' => EncryptedOrNull::class`; file unchanged @HEAD |
| C2 | CONFIRMED | `app/Casts/EncryptedOrNull.php:37-42 @8ad02ed3` - `try { Crypt::decryptString(...) } catch (DecryptException) { return null; }`; same @HEAD |
| C3 | CONFIRMED | `app/Casts/EncryptedOrNull.php:50-52 @8ad02ed3` - `if ($value === null \|\| $value === '') return [$key => null];`; same @HEAD |
| C4 | CONFIRMED | `database/migrations/2026_09_18_160000_encrypt_user_twitch_tokens.php:39-40 @8ad02ed3` - both `ALTER ... TYPE text` statements precede the `DB::table('users')` chunk loop at :46 |
| C5 | UNVERIFIABLE | tagged [unverified]; production column types, not in the repo |
| C6 | CONFIRMED | same migration `:80-93 @8ad02ed3` - `encryptOnce()` returns null when `Crypt::decryptString()` succeeds |
| C7 | CONFIRMED | same migration `:39-65 @8ad02ed3` - only `DB::statement` / `DB::table('users')`; no `App\Models` import or reference |
| C8 | CONFIRMED | `tests/Feature/UserTokenEncryptionTest.php:17-86 @8ad02ed3` covers all three named assertions (raw differs + decrypts back :22-24, legacy plaintext reads null :52-64, `character_maximum_length` null :78-86). `php artisan test --filter=UserTokenEncryptionTest` @HEAD: 6 passed, 12 assertions (pgsql, so the column-length case ran) |
| C9 | CONFIRMED | `app/Services/TwitchPayloadScrubber.php:34-36, 41-46, 56-62, 71-85 @8ad02ed3` - `DENIED_KEYS = ['top_predictors']` stripped recursively at any depth; four identity fields nulled on truthy `is_anonymous`; no `top_contributions` entry |
| C10 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php:524 @8ad02ed3` (`scrub`) precedes `:535` (`enrichEventWithUserAvatars`) |
| C11 | CONFIRMED | `tests/Unit/TwitchPayloadScrubberTest.php:9,35,56,70,84 @8ad02ed3` - one case per listed assertion. `php artisan test --filter=TwitchPayloadScrubberTest` @HEAD: 5 passed, 17 assertions |
| C12 | CONFIRMED | `resources/js/composables/useNormalizeEvent.ts:72 @8ad02ed3` - `if (e?.is_anonymous) {` ahead of every field read; the quoted prior expression matches the removed line in the diff |
| C13 | CONFIRMED | `app/Services/UserDeletionService.php:105,110 @8ad02ed3`, both inside the `DB::transaction` opened at :75. `database/migrations/2026_02_16_231526_add_user_id_to_twitch_events_table.php:16` is `->constrained()->nullOnDelete()`; `database/migrations/0001_01_01_000000_create_users_table.php:33` declares `sessions.user_id` as `foreignId(...)->nullable()->index()` with no `constrained()` |
| C14 | CONFIRMED | `app/Services/UserDeletionService.php:73,120-122 @8ad02ed3` - four `attempt()` calls; `attempt()` at :238-247 catches `Throwable`, `Log::warning`s, does not re-raise |
| C15 | CONFIRMED | `app/Services/TwitchTokenService.php:20,58-68 @8ad02ed3` - `Http::asForm()->post('https://id.twitch.tv/oauth2/revoke', ['client_id', 'token'])`, `if ($response->successful() \|\| $response->status() === 400) return true;` |
| C16 | CONTRADICTED (first half) / CONFIRMED (second half) | Not all external steps run after the commit: `app/Services/UserDeletionService.php:73 @8ad02ed3` runs `attempt('twitch eventsub subscriptions', ... removeUserSubscriptions($user))` BEFORE `DB::transaction(...)` at :75, and the file's own docblock (:50-52) calls that phase 1 "while the rows still exist". The other three external steps are at :120-122, after the commit. Second half holds: `$imageUrls`, `$fourthwallWebhook`, `$twitchAccessToken` are read at :67-69. Same @HEAD |
| C17 | CONFIRMED | `app/Services/UserDeletionService.php:65 @8ad02ed3` - `bool $redactAuditTrail = false`; `app/Http/Controllers/Settings/AccountController.php:47` passes `redactAuditTrail: true`; `app/Http/Controllers/Admin/AdminUserController.php:191` calls `eraseAccount($user)` with no flag |
| C18 | CONFIRMED | `tests/Feature/AccountErasureCompletenessTest.php:32,41,61,78,97,121,143 @8ad02ed3` - one case per listed assertion, including the revoke body carrying `a-live-token` (:37-38) and the 500-everything case (:143-153). `php artisan test --filter=AccountErasureCompletenessTest` @HEAD: 7 passed, 11 assertions |
| C19 | CONFIRMED | `database/migrations/2026_09_18_170000_drop_supporter_email_columns_from_external_events.php:28-36 @8ad02ed3` drops both; `app/Models/ExternalEvent.php` has neither in `$fillable`/`$casts` (grep returns nothing @8ad02ed3); `app/Services/External/NormalizedExternalEvent.php` has no `supporterEmail`/`supporterEmailHash`/`privateMetadata` (grep returns nothing @8ad02ed3). Same @HEAD |
| C20 | CONFIRMED | `app/Http/Controllers/Admin/AdminTwitchEventController.php:42-50` (external) and `:85-91` (twitch) `@8ad02ed3` project exactly the field sets declared at `resources/js/pages/admin/events/index.vue:9-25 @8ad02ed3`. `raw_payload`/`event_data` are absent; the `user` relation serialises through `User::$hidden` (`app/Models/User.php:203-212 @8ad02ed3`), which lists `access_token` and `refresh_token`. Note the projection passes the whole `user` model where the page declares three of its fields |
| C21 | CONFIRMED | `tests/Feature/BMACWebhookTest.php:126-134 @8ad02ed3` - `not()->toHaveKey('private_metadata')`, `not()->toHaveKey('supporter_email_hash')`, `not()->toContain('john@example.com')`, `not()->toContain(hash('sha256', ...))`. `php artisan test --filter=BMACWebhookTest` @HEAD: 10 passed, 38 assertions |
| C22 | CONFIRMED | @8ad02ed3: `routes/admin.php` has no `ip-lookup` line; `AdminSessionController` has no `ipLookup` and no `Location` import; `git ls-tree -r 8ad02ed3` under `app/Services/Location/` returns `GeoMath.php` only; `composer.json` has no `stevebauman`; `config/ban.php:75` is `'block_by_country' => false`. Same @HEAD |
| C23 | CONFIRMED | `routes/console.php:207-226 @8ad02ed3` - `Checkin` `checked_in_at < subDays(90)`, `ListAppendHistory` `fired_at < subDays(90)`, `AdminAuditLog` `created_at < subDays(730)`, all `->daily()`. `git show 8ad02ed3^:routes/console.php` matches none of those three models |
| C24 | CONFIRMED | `app/Http/Controllers/TwitchEventSubController.php @8ad02ed3` - grep for `last_webhook_activity` / `webhookLog` returns only the explanatory comment at :268; `:255` logs `'bytes' => strlen($body)` |
| C25 | CONFIRMED | `app/Http/Controllers/Settings/StreamLabsIntegrationController.php:61 @8ad02ed3` - `'scope' => 'socket.token donations.read'`; `git grep donations.create -- app/` @8ad02ed3 returns one comment line only |
| C26 | CONFIRMED | `app/Services/Tts/TtsService.php:150-154 @693d8538` - `hash_hmac('sha256', $text.'\|'.$voiceId.'\|'.$modelId.'\|'.$scope, config('app.key'))`; `app/Jobs/SynthesizeAlertTts.php:46 @693d8538` passes `$this->broadcasterId`. Same @HEAD |
| C27 | CONFIRMED | `hash_hmac` is deterministic and `SynthesizeAlertTts` is the only caller of `synthesize()` (`git grep -F 'synthesize(' -- app/ routes/` @693d8538); `SpeakableText::prepare()` still precedes the key at `TtsService.php:42` |
| C28 | UNVERIFIABLE | tagged [unverified]; production row inspection |

### Surface
Undisclosed: `tests/Feature/AccountDeletionForkedKitTest.php` - adds an `Http::fake` / `Storage::fake('images')` `beforeEach`. Undisclosed: `tests/Feature/AccountDeletionRedirectTest.php` - the same `beforeEach`. Every other path in `git show --stat` for both commits appears under `### Surface`; no phantom paths.

### Findings
- **F1** undisclosed path - `tests/Feature/AccountDeletionForkedKitTest.php:6-18 @8ad02ed3` gains an `Http::fake` + `Storage::fake('images')` `beforeEach` that no Surface line lists; a reader checking which tests this change alters will miss it.
- **F2** undisclosed path - `tests/Feature/AccountDeletionRedirectTest.php:6-18 @8ad02ed3` gains the identical `beforeEach`, also unlisted. Both exist because erasure now makes outbound calls, which is a consequence of C14 worth disclosing.
- **F3** claim contradicted - C16 says the external cleanup steps run after the transaction commits; `app/Services/UserDeletionService.php:73 @8ad02ed3` runs the EventSub removal before `DB::transaction` at :75. Read C16 as covering only the three steps at :120-122.
- **F4** contradicts the record - dropping `donations.create` (`StreamLabsIntegrationController.php:61 @8ad02ed3`) narrows what OL-2609-064 C1 recorded as "the unchanged scope string `socket.token donations.read donations.create`", without citing that claim inline, and leaves `CLAUDE.md:467 @HEAD` ("Scopes: `socket.token`, `donations.read`, `donations.create`") false even though the same commit edits `CLAUDE.md`.
- **F5** contradicts the record - OL-2609-096 Unchanged lines 36 and 40 record `private_metadata` as remaining the `encrypted:array` column and `supporter_email_hash` as still written by BMAC; C19 removes both, and no line of this claim cites OL-2609-096.

### Notes
- Nothing backing C1-C27 changed between `693d8538` and `@HEAD` (`6790c532`): `git diff --stat 693d8538 HEAD` touches only viewer-erasure files claimed by OL-2609-098/099. No undisclosed drift.
- `tests/Feature/BMACWebhookTest.php:110 @HEAD` is still named "stores event with PII stripped from raw_payload and email captured into private metadata" while its body now asserts the email is retained nowhere. The name is stale, not the assertion.
- Four test files were run at HEAD, all green: UserTokenEncryptionTest (6), TwitchPayloadScrubberTest (5), AccountErasureCompletenessTest (7), BMACWebhookTest (10).
