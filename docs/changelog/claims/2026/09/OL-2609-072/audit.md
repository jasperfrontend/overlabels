## Audit of OL-2609-072 - feat(recipes): a recipe can wire an alert, and a product says what is actually left

**Audited:** 2026-09-17
**Commit:** 75daa5eb0deb3e73c9c8ed15a047b9f1a8b24ed8
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/recipe-manifest.schema.json` (tree): `installs.alert_triggers.items.required = [overlay, service, event_type]`, properties include `duration_ms`; `installs.alert_targets.items.required = [alert, overlays]`, `overlays.minItems = 1` |
| C2 | CONFIRMED | `app/Services/Recipes/RecipeInstaller.php:436-478` - `installAlertTriggers()`: `EventTemplateMapping::create` under `$trigger['service'] === 'twitch'`, `ExternalEventTemplateMapping::create` otherwise |
| C3 | CONFIRMED | `RecipeInstaller.php:58` `private const int DEFAULT_ALERT_DURATION_MS = 5000;`, applied at `:442` via `?? self::DEFAULT_ALERT_DURATION_MS` |
| C4 | CONFIRMED | `RecipeInstaller.php:436-478` - neither `create()` array carries `condition_type` or `condition_value`; `grep condition_type` in the file returns nothing |
| C5 | CONFIRMED | `RecipeInstaller.php:481-492` - `$alert->targetStaticOverlays()->sync($ids)` with `$ids` resolved from `$target['overlays']` |
| C6 | CONFIRMED | `RecipeInstaller.php:504-519` throws on `$template->type !== $type`; called with `'alert'` for triggers (`:439`) and target alerts (`:484`), `'static'` for target overlays (`:488`) |
| C7 | CONFIRMED | `RecipeInstaller.php:129` `assertNoAlertTriggerCollisions()` precedes `DB::transaction` at `:137`; `:534-564` filters `->first(fn ($m) => $m->template !== null)` then throws |
| C8 | CONFIRMED | `RecipeInstaller.php:545,553` - the `first()` predicate skips rows whose `template` is null; pinned by `RecipeAlertWiringTest` "installs over a mapping whose overlay has already been deleted" (passes) |
| C9 | CONFIRMED | `RecipeInstaller.php:685-686` - both `whereIn(...)->delete()` calls precede the `foreach ($overlays ...)` delete loop inside the uninstall transaction |
| C10 | CONFIRMED | `RecipeInstaller.php:625-631` - Twitch line uses `$mapping->event_type_display` (`EventTemplateMapping.php:142-145` returns `EVENT_TYPES[...] ?? event_type`); external line reads `SERVICE_EVENT_TYPES[service][event_type]` |
| C11 | CONFIRMED | `app/Services/Recipes/RecipeManifestValidator.php:257-` - all seven error shapes present: duplicate overlay ref, unknown trigger overlay, unknown service, unknown Twitch / service event type, duplicate `(service, event_type)`, unknown target alert / overlay ref, duplicate target alert |
| C12 | CONTRADICTED | `php artisan test --filter=RecipeAlertWiringTest`: 23 passed. But no test exercises the duplicate `alert_targets[].alert` error (C11's last item) or the schema `minItems`/`duration_ms` optionality (C1). It covers C2-C10 and six of C11's seven checks, not "C1 through C11" |
| C13 | UNVERIFIABLE | tagged [unverified] |
| C14 | CONFIRMED | `app/Contracts/AuthenticatedExternalServiceDriver.php:26` - the only method is `public function requiredCredentials(): array;` |
| C15 | CONFIRMED | `StreamLabsServiceDriver.php:28` `['socket_token', 'listener_secret']`, `KofiServiceDriver.php:27` `['verification_token']`, `BMACServiceDriver.php:27` `['webhook_secret']`, `FourthwallServiceDriver.php:28` `['access_token']` |
| C16 | CONFIRMED | class lines: `CheckinServiceDriver.php:25`, `TowerServiceDriver.php:25`, `GpsServiceDriver.php:13` implement `ExternalServiceDriver, StatefulExternalServiceDriver`; `ThroneServiceDriver.php:12` implements `ExternalServiceDriver` only |
| C17 | CONFIRMED | `app/Models/ExternalIntegration.php:100-122` - returns true when driver is not `instanceof AuthenticatedExternalServiceDriver`, else false on any `empty($credentials[$key])`. (Also returns false for an unregistered service, `:102-104`, which the claim does not mention) |
| C18 | CONFIRMED | `app/Support/WiringFacts.php:399-411` - `$ready` = enabled rows filtered by `isAuthenticated()`, SATISFIED only on `$ready === count($services)` |
| C19 | CONFIRMED | `RecipeInstaller.php:594-606` - `($info['created'] ?? false) && in_array($service, $declared, true)` with `$declared` from `$instance->recipe?->manifest['requires_integrations']` |
| C20 | CONFIRMED | `RecipeInstaller.php:637` (`removals()`) and `:698` (`uninstall()`) both iterate `$this->productOwnedIntegrations($instance)` |
| C21 | CONTRADICTED | `php artisan test --filter=IntegrationReadinessTest`: 20 passed. The file covers C15-C20 and C32 (the banner test asserts `integration-streamlabs` + `#el-integration-streamlabs`). It cannot and does not cover C27, a TypeScript export claim; the reference is misnumbered |
| C22 | UNVERIFIABLE | tagged [unverified] |
| C23 | CONFIRMED | `resources/js/composables/useAddressableTabs.ts:24-35` (`tab-` prefix check, `keys.includes`), `:39-43` (`href.split('#')[0]` keeps the query string) |
| C24 | CONFIRMED | `useAddressableTabs.ts:54-65` - `watch(active, ...)` without `immediate`, body calls `window.history.replaceState` |
| C25 | CONFIRMED | `templates/show.vue:114` + `:324 v-model="mainTab"`, `templates/edit.vue:246` + `:653`, `dashboard/index.vue:47` + `:102 v-model="activeTab"`; `grep obsFirst show.vue` returns nothing |
| C26 | CONFIRMED | Shipped version (`git show 75daa5eb:resources/js/pages/products/show.vue:210`) appends the literal `#tab-obs`. Current tree (`:469`, `:508`) produces the same fragment through `urlWithTab(..., 'obs')` after 4a53ba8f and later commits |
| C27 | CONFIRMED | `grep -rn useProductTarget resources/js app` - no export, no call; the single hit is a doc comment at `useUiMode.ts:141` |
| C28 | CONFIRMED | `useUiMode.ts:194-224` - `setAttribute('data-product-focus', '')` on `[data-product-target="${key}"]`; `scrollIntoView` gated at `:215` on `elementKeyFromHash(window.location.hash) !== key` |
| C29 | CONFIRMED | `useUiMode.ts:154-164` - returns null unless `el-` prefix, then `/^[a-z0-9_-]+$/i.test(key)`; pinned by `useUiMode.test.ts` `elementKeyFromHash` block (passes) |
| C30 | CONFIRMED | `resources/css/app.css:21,45` `[data-product-focus]`; no `.product-target` selector (only the `product-target-glow` keyframe name at `:27,30`) |
| C31 | CONFIRMED | `app/Support/ProductSetup.php:115-128` - `route(WiringCatalog::wire($wire['key'])['route'])`, `'url' => $target === null ? $url : $url.'#el-'.$target` |
| C32 | CONFIRMED | `ProductSetup.php:145-163` - iterates `$instance->recipe?->manifest['installs']['integrations']`, returns `'integration-'.$service` on null, `! enabled`, or `! isAuthenticated()` |
| C33 | CONFIRMED | `php artisan test --filter=ProductSetupFlowTest`: 11 passed, including "sends the next step to the page its control is on" (`settings.integrations.bot.show` + `#el-bot-toggle`) and "omits the fragment for a step with no single control" (`route('lists.index')`, target null) |
| C34 | CONFIRMED | `npm test -- resources/js/composables/useAddressableTabs.test.ts resources/js/composables/useUiMode.test.ts`: 2 files, 22 passed |

### Surface
Complete. All 30 non-exempt paths in `git show --stat` appear in Surface; no phantom paths.

### Findings
- **F1** test coverage narrower than claimed - C12 says `RecipeAlertWiringTest` covers C1 through C11, but no test in `tests/Feature/RecipeAlertWiringTest.php` exercises the validator's duplicate `alert_targets[].alert` error (`RecipeManifestValidator.php`, the `$targeted` check) or the schema's `overlays.minItems`. Either add the two cases or reword C12 to what the file asserts.
- **F2** misnumbered cross-reference - C21 says `IntegrationReadinessTest` covers "C27"; C27 is a `useUiMode.ts` export claim a Pest file cannot reach. The test "points the banner at the one service still unfinished" asserts C32. Correct the reference.
- **F3** reversal of an earlier claim not cited - `productOwnedIntegrations()` (`RecipeInstaller.php:594-606`) adds a `requires_integrations` condition on top of the `created` rule that OL-2609-039 C4 and C5 recorded as the whole rule ("only when `primitive_map.integrations.<service>.created` is true and the row still exists"). The Risk section discloses the behaviour change but the claim does not reference OL-2609-039 inline as `claims-guide.md` ("Reference other IDs inline when a change corrects or builds on an earlier one") asks. `ProductUninstallTest` (9 tests) still passes because its integration case is `checkin`, which is in `requires_integrations`.

### Notes
Unchanged line "No recipe manifest in `resources/recipes/` declares `alert_triggers` or `alert_targets`" was true at 75daa5eb (`git grep` on that revision finds none) and is no longer true of the tree: `bmac_alert`, `fourthwall_alert`, `kofi_alert`, `streamlabs_alert` and `throne_alert` manifests declare them since 08298f12.
OL-2609-049 C3, OL-2609-063 (Unchanged) and OL-2609-069 C11 describe `useProductTarget()` / `.product-target`, both removed here; C27 and C30 disclose the removal, so this is a supersession, not a finding.
