## Audit of OL-2609-063 - feat(settings): product integrations get their own spot, the rest sort by connection state then A-Z

**Audited:** 2026-09-25
**Commit:** e7666e8fc13bde5b59a43dc0bb8d5ed617475639
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Http/Controllers/Settings/IntegrationController.php:55-61 @e7666e8` - `usort`, `connected` mismatch returns `$a['connected'] ? 1 : -1`, else `strcasecmp($a['name'], $b['name'])`; registry at `app/Services/External/ExternalServiceRegistry.php:21-30 @e7666e8^` lists `tower` last. Same sort at `:58-63 @HEAD` |
| C2 | CONFIRMED | `IntegrationController.php:44 @e7666e8` - `'product' => $productOf[$service] ?? null`; `:126-137` `productIntegrations()` walks `$catalog->listed()` `requires_integrations`; `resources/recipes/chat_checkin/manifest.json:13` `["checkin"]`, `chat_tower/manifest.json:13` `["tower"]` @e7666e8. @HEAD the values are `chat-checkin` / `chat-tower` (OL-2609-114 renamed the slugs) |
| C3 | CONFIRMED | `resources/js/pages/settings/integrations/index.vue:71-72 @e7666e8` - two `computed` `filter()`s on `product !== null` / `=== null`, order preserved. Removed @HEAD by OL-2609-069 |
| C4 | CONFIRMED | `index.vue:408-451 @e7666e8` - `v-if="productServices.length > 0"`, title "Overlabels products", `md:grid-cols-2`, `ProductBadge` `text-green-500`/`text-violet-400`, `border-green-500/60`/`border-violet-400/60`, `FlaskConical` on `test_mode`, "Last event" line, Link to `/settings/integrations/${service.url_slug ?? service.key}` identical to the external row at `:480`; section precedes "External Integrations" at `:455`. Product grid removed @HEAD by OL-2609-069 |
| C5 | CONFIRMED | `index.vue:435-438 @e7666e8` - "Not connected." + `Link :href="/products/${service.product}"` "Install the product" + "to connect it."; the same text survives at `index.vue:101-102 @HEAD` |
| C6 | CONFIRMED | `index.vue:84 @e7666e8` - `useCollectionFilter` source `() => externalServices.value`; `:457` `CollectionFilter v-if="externalServices.length > 0"`; `:459` "No integrations match" on `filteredServices`. @HEAD the filter runs over all rows (`index.vue:53 @HEAD`, OL-2609-069) |
| C7 | CONFIRMED | `tests/Feature/IntegrationsPageOrderTest.php:30-38 @e7666e8` - asserts names equal `strcasecmp`-sorted copy and `toContain('Chat Tower', 'Ko-fi', 'Throne')`. Test body unchanged @HEAD; `php artisan test --filter=IntegrationsPageOrderTest` @HEAD: 6 passed |
| C8 | CONFIRMED | `IntegrationsPageOrderTest.php:40-56 @e7666e8` - creates `kofi` and `bmac`, `toBe(['Buy Me a Coffee', 'Ko-fi'])`, `toBe([...$disconnected, ...$connected])`, disconnected sorted. Body unchanged @HEAD; passed in the run above |
| C9 | CONFIRMED | `IntegrationsPageOrderTest.php:58-67 @e7666e8` - `chat_checkin` / `chat_tower`, null for kofi, streamlabs, fourthwall, bmac, throne, gps. @HEAD expects `chat-checkin` / `chat-tower` (OL-2609-114); passed in the run above |
| C10 | UNVERIFIABLE | tagged [unverified]; a fail-first run against a stashed tree |
| C11 | UNVERIFIABLE | tagged [unverified]; a local render on `overlabels.test` |

### Surface
Complete.

### Findings
- **F1** Unchanged line asserts something false - "Nothing else reads the registry order for display" is contradicted by `app/Console/Commands/BuildIntegrationControlsReference.php:181,198 @e7666e8`, where `renderIndex()` builds the "Per service" table in `ExternalServiceRegistry::services()` order; that order is on the page at `resources/help/reference/integration-controls/all-integration-controls.md:22-29 @HEAD` (kofi, gps, checkin, ..., tower). A follow-up claim should restate the line truthfully. It could also move it into Claims with a tag, because the guide keeps assertions out of Unchanged.

### Notes
- Tests were run at HEAD (d154839c), not at e7666e8. C7 and C8 bodies are byte-identical between the two revisions. C9 differs only in the slug literals (OL-2609-114).
- OL-2609-069 superseded C3, C4 and C6: the product grid and the `productServices`/`externalServices` split are gone, and one flat row list is filtered. Its Surface discloses this but does not cite OL-2609-063 inline.
- `45c47fb2` (no claim, .vue-only, exempt) added `description-class` to the "Overlabels products" heading. It changed nothing C4 asserts.
- The product card grid is another hand-rolled list beside the page's existing hand-rolled external list. CLAUDE.md's Collection List section says every listing page uses `CollectionList.vue`. That is not phrased as a "do not", so it is recorded here and not as a finding.
