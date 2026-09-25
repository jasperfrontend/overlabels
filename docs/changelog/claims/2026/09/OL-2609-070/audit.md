## Audit of OL-2609-070 - style(settings): the Twitch event count only spells out a fraction when one is missing

**Audited:** 2026-09-25
**Commit:** 0719faee081396cfc8afe0052e62131fe3da8537
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/utils/integrationRows.ts:71-72 @0719fae` - `listening = active_count > 0`, `complete = listening && active_count >= supported_count`; `:82-86` status is `Listening to ${active_count} events` when `complete`, `Listening to ${active_count} of ${supported_count} events` when `listening` and not complete. Same @HEAD `:72,82` |
| C2 | CONFIRMED | `resources/js/utils/integrationRows.ts:89 @0719fae` - `statusAlert: stalled \|\| (listening && !complete)`; complete implies listening, so `stalled` is false and `statusAlert` false. Same @HEAD `:89` |
| C3 | CONFIRMED | `resources/js/utils/integrationRows.ts:73 @0719fae` - `stalled = eventsub.connected && !listening`, `listening` being `active_count > 0`. Same @HEAD `:73` |
| C4 | CONFIRMED | `resources/js/pages/settings/integrations/index.vue:92 @0719fae` - `:class="row.statusAlert ? 'text-fuchsia-400' : 'text-muted-foreground'"`; `git show 0719fae:...index.vue \| grep pink` returns nothing. @HEAD same at `:97`, no `pink` |
| C5 | CONFIRMED | `resources/js/pages/settings/integrations/twitch.vue:204 @0719fae` - `complete = computed(() => listening.value && inactiveEvents.value.length === 0)`; `:243` `v-if="complete"` `text-muted-foreground`, `:246` `v-else-if="listening"` `text-fuchsia-400`, `:250` `v-else-if="eventsub.connected"` `text-fuchsia-400` (one v-if/v-else-if chain); no `pink` in the file. @HEAD `:246,250` same, no `pink` |
| C6 | CONFIRMED | `resources/js/utils/integrationRows.test.ts:60-67 @0719fae` - `active_count: 29, supported_count: 29` asserts status `Listening to 29 events`, `statusAlert` false. `vitest run resources/js/utils/integrationRows.test.ts`: 14/14 passed @0719fae (temporary worktree) and @HEAD (`npm test -- resources/js/utils/integrationRows.test.ts`) |
| C7 | CONFIRMED | `resources/js/utils/integrationRows.test.ts:69-77 @0719fae` - `active_count: 28, supported_count: 29` asserts status `Listening to 28 of 29 events`, `statusAlert` true, `stalled` false. Passed in the same two runs as C6 |

### Surface
Complete.

### Findings
None.

### Notes
- C1's first half does not hold when both counts are 0 (not `complete`, since `listening` is false). That cannot happen here: `supported_count` is `count($eventsub['supported_events'])` (`app/Http/Controllers/Settings/IntegrationController.php:74 @0719fae`).
- Unchanged line 3 says `product-target` "also paints fuchsia on this page". At 0719fae, `.product-target` is bound only in `settings/integrations/bot.vue:84` and `overlaytokens/index.vue:171`, not in `index.vue` or `twitch.vue`. It is an outline plus box-shadow (`resources/css/app.css:19-26 @0719fae`), not a border. It is not in the diff, so check (c) passes.
- The audited files changed after ship without touching any claimed symbol: `index.vue` gained `data-product-target` in OL-2609-072, and the product slugs in the band-order test fixtures in `integrationRows.test.ts` were hyphenated in OL-2609-114.
