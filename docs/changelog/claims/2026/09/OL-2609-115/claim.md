## OL-2609-115 - feat(machines): serve the recipe manifest schema at the URL it claims, and put products in llms.txt

**Shipped:** 2026-09-20
**Commit:** `git log --grep=OL-2609-115`

### Surface
- `routes/web.php` - new `schemas.recipe-manifest` route serving the manifest schema
- `app/Http/Controllers/SitemapController.php` - `/schemas/recipe-manifest/v1.json` added to `STATIC_PATHS`
- `public/llms.txt` - new section 12 (Products), old section 12 (Links) renumbered to 13, schema named in section 10 and in the closing link list
- `resources/help/pages/llms-txt.md` - "What is in it" gains the products bullet, Related gains the schema URL
- `tests/Feature/RecipeManifestSchemaTest.php` - new file
- `tests/Feature/LlmsTxtProductsTest.php` - new file

### Claims
- **C1** [code] `resources/recipes/recipe-manifest.schema.json` declares `"$id": "https://overlabels.com/schemas/recipe-manifest/v1.json"`, and that `$id` predates this change. The URL answered 404 until this commit.
- **C2** [code] The route registered at `/schemas/recipe-manifest/v1.json` is named `schemas.recipe-manifest` and responds 200.
- **C3** [code] It responds with `Content-Type: application/schema+json`.
- **C4** [code] Its body is `file_get_contents()` of `resources/recipes/recipe-manifest.schema.json` with no transformation, so the served document and the one `RecipeManifestValidator` reads are the same bytes. No copy is written into `public/`.
- **C5** [code] It sends `Access-Control-Allow-Origin: *`, so a browser-based schema tool can fetch it.
- **C6** [code] `SitemapController::STATIC_PATHS` contains `/schemas/recipe-manifest/v1.json`.
- **C7** [code] `public/llms.txt` has a section `## 12. Products - the ready-made overlays` naming all nine listed products with their current slugs, and the former section 12 is now `## 13. Links`.
- **C8** [code] No `§12` cross-reference inside `llms.txt` pointed at the Links section before the renumber; the only pre-existing numeric cross-references are to sections 4.5, 5.2, 5.6, 6, 8, 8.1 and 11, none of which moved.
- **C9** [code] `llms.txt` states that product pages are application-rendered and have no `.md` twin.
- **C10** [code] `llms.txt` names `https://overlabels.com/schemas/recipe-manifest/v1.json` in section 10, in section 12 and in the closing link list.
- **C11** [test] `RecipeManifestSchemaTest` asserts the schema's `$id` path equals the route's URI, that the response body equals the file on disk, that every key any shipped manifest uses is declared in the schema (which matters because the schema sets `additionalProperties: false`), and that all eleven shipped manifests validate against it.
- **C12** [test] `LlmsTxtProductsTest` asserts every listed product slug appears in `llms.txt`, that `llms.txt` names no `/products/` slug that is not a current listed one, and that it contains the schema's own `$id`.
- **C13** [test] `LlmsTxtProductsTest` reads `public/llms.txt` from disk rather than requesting `/llms.txt`. The file is served by the web server without reaching Laravel, so there is no route and a request 404s under test.
- **C14** [unverified] Both guards were run against a deliberately broken tree - one product slug in `llms.txt` changed back to its pre-rename form, and the route moved to `v2.json` - and five of the eight assertions failed.
- **C15** [unverified] Full gate green at this commit: `pint --test`, `format:check`, `lint:check`, `npm test`, `typecheck`, `build`, `php artisan test`.

### Unchanged
- `resources/recipes/recipe-manifest.schema.json` itself is not in the diff. It already carried the `$id`, the draft 2020-12 declaration and (from OL-2609-114) the `url_aliases` property; this change serves it rather than editing it.
- `RecipeManifestValidator::resolveSchemaPath()` still resolves the same `base_path()` file and is not in the diff. The published URL is a second reader of one file, not a second source.
- `resources/recipes/overlabels_recipe_manifest.schema` is a local file ignored at `.gitignore:66`. It is a stale copy carrying the same `$id` as the real schema, it is referenced by nothing in the tree, and it is deliberately not touched here: it cannot reach anyone, and deleting an untracked file is the author's call.
- `public/robots.txt` names `/llms.txt` in a comment and is not in the diff; the schema is discoverable through `sitemap.xml`, which robots.txt already points at.

### Risk
None user-facing. One new public URL that previously 404'd, and one hand-written section in `llms.txt`
that now has to keep pace with the product catalogue - which is what `LlmsTxtProductsTest` enforces,
failing the build rather than going quietly stale.
