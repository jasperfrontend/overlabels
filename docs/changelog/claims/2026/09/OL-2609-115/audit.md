## Audit of OL-2609-115 - feat(machines): serve the recipe manifest schema at the URL it claims, and put products in llms.txt

**Audited:** 2026-09-25
**Commit:** af3c004dfc12fa71ee27ca3e3ac32af4ea8f67c0
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/recipes/recipe-manifest.schema.json:3 @af3c004` - `"$id": "https://overlabels.com/schemas/recipe-manifest/v1.json"`; same line present @af3c004^ (0212f7b3) and since a8e434f5 (2026-05-13). `git grep "schemas/recipe" af3c004^ -- routes app public config bootstrap` returns nothing and `git ls-tree -r af3c004^ public` holds no schema file, so no route or static file served the URL before. @HEAD the file differs only in one `lists` description string (OL-2609-129); `$id` unchanged |
| C2 | CONFIRMED | `routes/web.php:182-192 @af3c004` - `Route::get('/schemas/recipe-manifest/v1.json', ...)->name('schemas.recipe-manifest')`; kernel request under the testing env @HEAD returned 200. Same route block at `routes/web.php:183-193 @HEAD` |
| C3 | CONFIRMED | `routes/web.php:187 @af3c004` - `'Content-Type' => 'application/schema+json'`; kernel probe @HEAD returned `CT=application/schema+json` |
| C4 | CONFIRMED | `routes/web.php:184 @af3c004` - `(string) file_get_contents(base_path('resources/recipes/recipe-manifest.schema.json'))`, the same path `RecipeManifestValidator::resolveSchemaPath()` returns (`app/Services/Recipes/RecipeManifestValidator.php:108-109 @af3c004`); kernel probe @HEAD: body `===` file on disk is true. No schema file under `public/` @af3c004 or @HEAD |
| C5 | CONFIRMED | `routes/web.php:189 @af3c004` - `'Access-Control-Allow-Origin' => '*'`; kernel probe @HEAD with an `Origin` header returned `ACAO=*` |
| C6 | CONFIRMED | `app/Http/Controllers/SitemapController.php:38 @af3c004` - `['path' => '/schemas/recipe-manifest/v1.json', 'priority' => '0.4', 'changefreq' => 'monthly']` inside `STATIC_PATHS` (opens line 27); same @HEAD |
| C7 | CONFIRMED | `public/llms.txt:522 @af3c004` - `## 12. Products - the ready-made overlays`; lines 535-543 name nine `/products/<slug>` rows, matching the nine manifests with `listed: true` @af3c004; line 582 `## 13. Links`. `public/llms.txt` has no diff af3c004..HEAD |
| C8 | CONTRADICTED (compound) | First half CONFIRMED: `public/llms.txt @af3c004^` contains no `§12`. Second half CONTRADICTED: the pre-existing numeric cross-references @af3c004^ are `§8` (49), `§6` (99), `§8.1` (391), `§5.6` (451), `§5.2` (570) only; `§4.5` (545, 547) and `§11` (553) first appear @af3c004, added by this commit's section 12 |
| C9 | CONFIRMED | `public/llms.txt:553-555 @af3c004` - "product pages are application-rendered ... There is no `.md` twin."; same @HEAD |
| C10 | CONFIRMED | `public/llms.txt @af3c004` - URL at line 475 (section 10), line 568 (section 12), line 641 (closing link list); same @HEAD |
| C11 | CONFIRMED | `tests/Feature/RecipeManifestSchemaTest.php @af3c004` - test 1 compares `$id` path to `route('schemas.recipe-manifest', [], false)`; test 2 `getContent()` `toBe($onDisk)`; test 3 checks top-level manifest keys against `properties`; test 4 validates every `RecipeCatalog::all()` manifest (11 at af3c004 and HEAD). `php artisan test --filter='RecipeManifestSchemaTest\|LlmsTxtProductsTest'` @HEAD: 8 passed, 26 assertions |
| C12 | CONFIRMED | `tests/Feature/LlmsTxtProductsTest.php @af3c004` - tests "names every listed product", "names no product ... that is not a current listed slug", "points at the schema URL the schema claims as its own $id"; all passed in the same run @HEAD |
| C13 | CONFIRMED (mistagged) | `tests/Feature/LlmsTxtProductsTest.php:14 @af3c004` reads `public_path('llms.txt')`; no `llms.txt` route in `routes/` @af3c004; kernel probe of `/llms.txt` under the testing env @HEAD returned 404. No test asserts either statement - see F2 |
| C14 | UNVERIFIABLE | tagged [unverified] |
| C15 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** compound claim, half false - C8 says the pre-existing cross-references include sections 4.5 and 11, but `public/llms.txt @af3c004^` has none; `§4.5` (lines 545, 547) and `§11` (line 553) are introduced by this commit @af3c004. A corrective claim should list the pre-existing set as 5.2, 5.6, 6, 8, 8.1.
- **F2** mistagged, checkable as [code] - C13 is tagged [test], but `LlmsTxtProductsTest` asserts nothing about how it reads the file or about `/llms.txt` returning 404; both are facts of the test source and `routes/` and should be tagged [code].
- **F3** scope - `routes/web.php:188 @af3c004` sends `'Cache-Control' => 'public, max-age=3600'`, a caching behaviour on a new public URL that no claim or Surface line mentions (checked @HEAD: `CC=max-age=3600, public`). Record it in a follow-up claim.
- **F4** scope - `public/llms.txt:640 @af3c004` adds `- Products: https://overlabels.com/products` to the closing link list. The Surface line for `public/llms.txt` mentions only the schema being added to that list, and no claim covers this line.

### Notes
- Route headers were checked by sending a request through the HTTP kernel in `php artisan tinker` with the phpunit testing env (pgsql `laravel_foxes_test`, array session). The default `.env` returns 500 because the DB does not exist, and that says nothing about the route.
- Unchanged line 3: `resources/recipes/overlabels_recipe_manifest.schema` is ignored at `.gitignore:66 @af3c004` and nothing in the tree references it, but the file is absent from this checkout, so its "same `$id`" content could not be checked.
- C11 test 3 checks top-level keys only. Nested keys are covered by test 4 because every object in the schema @af3c004 sets `additionalProperties: false` (checked by walking the schema).
- The schema's `lists` description changed after this commit (OL-2609-129, `/dashboard/lists` -> `/lists`). That changes the served bytes, and the byte-for-byte test still passes because it reads the same file.
