## OL-2609-020 - feat(help): every guide, tutorial and deep dive gets its own OG card

**Shipped:** 2026-09-02
**Commit:** `git log --grep=OL-2609-020`

### Surface
- `app/Services/OgImageService.php` - `urlForPage()` added; the post card's context building extracted into `headlineContext()` and shared with `contextForUpdate()`
- `app/Http/Controllers/HelpController.php` - injects `OgImageService`, passes `ogImage` to the view
- `app/Console/Commands/OgGenerate.php` - `generatePages()` warms a card for every `HelpPage::all()` slug on deploy
- `tests/Feature/HelpPageOgImageTest.php` - new file

### Claims
- **C1** [code] `OgImageService::urlForPage()` renders through the `og.update` template with the kind label from `HelpCorpus::KIND_LABELS` as the eyebrow, the page `heading` wrapped over up to three lines, and the page `lead` (run through `bodyExcerpt()`) as the body.
- **C2** [code] `contextForUpdate()` returns the same array it did before the change for any `Update`: the eyebrow, title wrapping, `'Update'` fallback, excerpt wrapping, `bodyTop` and url now come from `headlineContext()` with identical bounds.
- **C3** [code] `HelpController::show()` passes `'ogImage' => $this->og->urlForPage($page, $page['canonical'])`, so `layouts/help.blade.php` resolves it instead of falling back to `asset('ogimage.jpg')`.
- **C4** [code] `OgGenerate::handle()` calls `generatePages()` before `generateUpdates()`, and `generatePages()` renders one card per slug in `HelpPage::all()`; `docker-entrypoint.sh` already runs `og:generate` on boot and is not in the diff.
- **C5** [test] `HelpPageOgImageTest` asserts `/help/tokens` serves an `og:image` matching `/og/<64 hex>.png` or the `/ogimage.png` fallback and never `ogimage.jpg`.
- **C6** [test] `HelpPageOgImageTest` asserts, when resvg is on the machine, that `tokens` and `conditionals` render to two different existing PNGs.
- **C7** [test] `UpdateSeoTest` passes unchanged against the `headlineContext()` extraction.
- **C8** [unverified] Rendered locally with resvg 0.47.0, the card for `/help/integration-test-mode` shows "GUIDE", the heading on one line and the lead over four lines.

### Unchanged
- `resources/views/og/update.blade.php` is not in the diff; help cards use it as-is, so `TEMPLATE_VERSION` did not need bumping and no cached post PNG is invalidated.
- `HelpReferenceController` and the reference card path (`urlFor()`, `contextForEntry()`, `og.help-reference`) are not in the diff.
- `/help/integration-presets` and `/help/gamejam` are Inertia pages served by `app.blade.php` and are not touched; they keep whatever `ogData` that layout resolves.

### Risk
The first deploy renders roughly thirty extra PNGs during boot, one shell-out to resvg each. Later deploys are cache hits unless a page's heading or lead changed.
