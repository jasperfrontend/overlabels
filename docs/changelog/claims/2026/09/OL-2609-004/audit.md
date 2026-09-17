## Audit of OL-2609-004 - feat(checkin): Chat Checkin integration - !checkin pipeline, controls, alerts

**Audited:** 2026-09-17
**Commit:** 34f9cfc3d9507133f783bfea34e8a9b317069a7d
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Services/External/ExternalServiceRegistry.php:23,74 @34f9cfc3` - `'checkin' => CheckinServiceDriver::class`, `'checkin' => 'Chat Checkin'`; same at `:24,76 @HEAD` |
| C2 | CONFIRMED | `app/Services/External/Drivers/CheckinServiceDriver.php:56-59 @34f9cfc3` - `verifyRequest()` body is `return false;`; `app/Http/Controllers/Api/ExternalWebhookController.php:113-118 @34f9cfc3` returns 403 on a false verify; same at `CheckinServiceDriver.php:56-59 @HEAD` |
| C3 | CONFIRMED | `CheckinServiceDriver.php:104-115 @34f9cfc3` - ten `'key' =>` entries; `:33-37 @34f9cfc3` - `PER_STREAM_CONTROL_KEYS` is exactly the three named keys. @HEAD the list is eleven controls and four per-stream keys (`farthest_checkin_this_stream`, `farthest_checkin_name_this_stream` replace `farthest_checkin_km_this_stream`) - OL-2609-009, OL-2609-013 |
| C4 | CONFIRMED | `tests/Feature/IntegrationProvisioningTest.php:182-190 @34f9cfc3` - POSTs `/settings/integrations/checkin`, asserts `serviceControlKeys` equals `expectedControlKeys('checkin')`. Ran `php artisan test --filter=IntegrationProvisioningTest` @HEAD: 14 passed |
| C5 | CONFIRMED | `routes/api.php:223-227,246 @34f9cfc3` - `/internal/bot` group has `bot.internal`, nested group `throttle:bot-internal`; `BotCheckinController.php:80 @34f9cfc3` - `Cache::add("checkin:cooldown:{$user->id}:{$data['chatter_id']}", 1, $cooldown)`; `:72` usage reply on `$args === ''`; `:87` miss reply on null resolve; `:141` `Checkin::updateOrCreate(['user_id', 'chatter_twitch_id'])` on the unique pair from `2026_09_01_140000_create_checkins_table.php:39`. @HEAD `store()` additionally refuses while offline before resolving (OL-2609-010) |
| C6 | CONFIRMED | `BotCheckinController.php:225-234 @34f9cfc3` - `$this->alertService->dispatch($normalizedEvent, $user, $storedEvent)`, `controls_updated` / `alert_dispatched` updates, `last_received_at`; matches `ExternalWebhookController.php:196-208 @34f9cfc3` steps 9-11. Same shape @HEAD |
| C7 | CONFIRMED | `BotCheckinController.php:200-212 @34f9cfc3` - `DB::transaction(fn () => ExternalEvent::create([...]))` wrapped in `catch (UniqueConstraintViolationException)`. It is a savepoint only when an outer transaction is open (the test suite's `DatabaseTransactions`); in a plain request it is a top-level transaction, which is not a contradiction of the claim's "cannot abort a surrounding transaction" |
| C8 | CONFIRMED | `tests/Feature/BotCheckinTest.php @34f9cfc3` - 17 `test(` blocks; each listed topic has a test (403 secret, 404 channel, not connected/disabled, usage/miss replies, pin move, same-stream no-increment, cooldown, home distance, farthest up, unique countries, same-second dedup, reset per_stream, reset persistent, country-set forget). Ran `php artisan test --filter=BotCheckinTest` @HEAD: 21 passed (4 added by OL-2609-010 and OL-2609-013) |
| C9 | CONFIRMED | `app/Services/StreamSessionService.php:437-466 @34f9cfc3` - `Cache::forget(CheckinServiceDriver::countrySetCacheKey(...))`, `where('source','checkin')->whereIn('key', PER_STREAM_CONTROL_KEYS)`, `$preservedAt = $control->resetValue(...)` passed as the tenth `ControlValueUpdated::dispatch` argument (`?int $updatedAt`, `app/Events/ControlValueUpdated.php:65 @34f9cfc3`), cleared dispatch gated on `pin_lifetime === 'per_stream'` at `:469-471`. Same at `:435-475 @HEAD` |
| C10 | UNVERIFIABLE | tagged [unverified] |
| C11 | CONFIRMED | `app/Events/CheckinsUpdated.php:41,46-50,54-56 @34f9cfc3` - `PrivateChannel('alerts.'.$this->broadcasterId)`, payload `pin`/`count`/`cleared`, `broadcastAs` `checkins.updated`; `?array $pin` is one pin. Same @HEAD |
| C12 | CONFIRMED | `app/Models/ExternalEventTemplateMapping.php:118-120 @34f9cfc3` - `'checkin' => ['checkin' => 'Chat Checkin']`; `tests/Unit/ExternalTriggerCatalogueTest.php @34f9cfc3` exists (added 83623b28) and checks every driver event type is a key in `SERVICE_EVENT_TYPES`. Ran `php artisan test --filter=ExternalTriggerCatalogueTest` @HEAD: 1 passed |
| C13 | CONFIRMED | `resources/js/utils/providerIcons.ts:27-34,41 @34f9cfc3` - Hamming distance of `0x1687` computed against the seven icons and `FALLBACK_ICON` `0x0660`: 9, 7, 9, 7, 7, 9, 9, 7 (min 7, 7 cells filled). Same values `:34 @HEAD` |
| C14 | CONFIRMED | Driver `'event.*'` literals @34f9cfc3 (`user_name`, `user_login`, `place`, `country`, `country_code`, `lat`, `lng`, `distance_km`) all appear as `[[[event.x]]]` in `resources/help/reference/eventsub-tags/all-chat-checkin-events.md @34f9cfc3`. @HEAD `event.distance_km` became `event.distance` in both files (OL-2609-009). Ran `php artisan test --filter=IntegrationEventTagDocsTest` @HEAD: 10 passed, including the `checkin` case |

### Surface
Complete. 25 non-exempt paths in `git show --stat 34f9cfc3`, all 25 listed.

### Findings
None.

### Notes
- Unchanged lines hold at @34f9cfc3: the twitch block of `resetControls()` is untouched (the diff only appends a call at `StreamSessionService.php:418`), and `ExternalWebhookController`, `DonationIntegrationController` and its subclasses, `StreamControlResetTest` and `OverlayRenderer.vue` are not in the stat.
- All drift from the shipped revision is claimed: OL-2609-009 (distance names), OL-2609-010 (live gate), OL-2609-013 (record-holder name), OL-2609-051 (tower: `RESERVED_KEYS`, `IntegrationProvisioningTest` exemption, `routes/api.php`).
- `CLAUDE.md:447` says `connectIntegration()` is "the single place `provision()` is called". `CheckinIntegrationController::save()` calls `provision()` directly (`:108 @34f9cfc3`), as `GpsIntegrationController::connect()` already did (`:128 @34f9cfc3`); the same paragraph records GPS as deliberately outside that base class, and the claim's Unchanged section discloses the same shape for checkin. Not counted as a contradiction.
- Tests were run against @HEAD (318fa4d2), not the shipped revision.
