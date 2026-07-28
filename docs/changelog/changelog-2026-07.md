# CHANGELOG JULY 2026

## July 28th, 2026 - fix(help): links in help prose look like links

The `/help` index is built almost entirely from `- [**Page name**](/help/page) - description` list items, and not one of them read as a link. Two causes, both in `help-prose.css`, no markdown touched.

- **`[**Bold**](url)` renders as `<a><strong>`**, and the `strong` rule repainted it `text-foreground` - stripping the violet off precisely the links meant to stand out most. `.help-prose a strong` now inherits the link colour.
- **Links carry a permanent underline** instead of one that only appeared on hover. Colour alone is not an affordance: it is invisible to anyone with a colour vision deficiency, and it cannot be discovered without already suspecting there is something there to hover over. Same redundant-encoding rule the events feed follows for provider icons - the underline carries the meaning, colour reinforces it. It sits at 40% opacity so a paragraph with several links still reads as prose rather than as a fence, and firms up on hover.
- The table-of-contents card is deliberately left alone. It is a numbered list under a "Table of contents" heading in its own bordered box, so it already reads as navigation, and underlining every entry would be heavy for no gain.
- Markdown sources are byte-identical, so `/help/*.md` and the crawlable index are unchanged.

## July 28th, 2026 - feat(help): the help beacon

The frontend half of contextual help. A round button in the bottom-right corner of every app page, with a dot when the current route has help behind it, opening a panel with the relevant pages.

- **`HelpBeacon.vue`**, mounted once in `AppLayout` beside the other global singletons, so all 60 app pages get it and no page opts in. Help pages themselves use `HelpLayout` and are untouched.
- **The dot is the point.** It is the difference between a help button you learn to ignore and one that tells you, before you click, that there is something here worth reading. It only appears when the route actually resolved help.
- **The excerpt is the page's own `lead`.** Every help page already opens with one, written to introduce it, running 94 to 275 characters - which is exactly a panel card. No markdown render, no body read, no second summary to keep in sync with the prose. `heading` is used over `title` for the same reason: "Blocks" fits a 375px panel, "Blocks - reusable building pieces for the Builder" does not. A test holds both to a length the panel can show.
- **Links open in a new tab**, because the entire point is helping with the page you are already on - navigating away from it to read about it would undo the feature. The panel stays open behind them.
- **375x650**, anchored to the button and shrinking to fit: `max-w-[calc(100vw-2rem)]` and `max-h-[calc(100dvh-7rem)]`, so it is a usable sheet on a phone rather than a clipped desktop panel. Escape and click-outside close it, focus moves in on open and returns to the button on close, and an Inertia navigation closes it since the answers it is showing belong to the previous route.
- **Empty state is honest**: it says no page covers this screen yet and points at the help index, rather than dressing up nothing as something.
- **Opening lands focus on the first article**, from the button and from the shortcut alike, so the panel is open-Enter to read the most relevant page, or open-Tab-Tab-Enter to reach a lower one. The empty state falls back to the panel itself, which has nothing to land on but still has to answer Escape. The panel's links had no focus styling at all before this, which did not matter while focus never went in there and matters now: articles, the close button and the footer link all take a violet focus ring. Clicking with a mouse still paints no ring, since `:focus-visible` tracks the input modality.
- **`Alt+H` toggles it**, sitting next to `Alt+R` for the tags reference: the two Alt shortcuts open a panel to *read* something, where the Ctrl ones do something. It registers through `useKeyboardShortcuts` like every other shortcut, so it lists itself in the `Ctrl+K` dialog with no second place to update. Toggling shut only pulls focus back to the button when focus was inside the panel, so Alt+H to peek and Alt+H to dismiss leaves the caret in the field you were typing in.

## July 28th, 2026 - feat(help): help pages declare where they are relevant

Help only helps if it turns up where the confusion is. The association between a place in the app and the page that explains it now lives in the help page's own frontmatter, and resolves server-side. This is the association layer only - nothing renders yet.

- **`context:` frontmatter** - a flat, comma-separated list of the route names a page covers, optionally narrowed by query constraints: `context: templates.index?type=block, templates.show?type=block, templates.blocks.library, builder.create`. It lives in the markdown for the same reason the prose does: writing the page wires it up, so there is no second file to keep in step, and reading `blocks.md` tells you where it surfaces. Thirteen pages declare a context; the rest (manifesto, why-overlabels, resources) deliberately declare none.
- **Route names, not URLs.** They are already unique, they survive a URL rewrite, and `$request->route()->getName()` hands one over. Constraints narrow a name down to a *state*, because `/templates` is one route serving four filter states.
- **Undeclared query parameters are ignored**, so `templates.index?type=block` still matches a URL carrying `filter`, `search`, `sort` and `page`. No allowlist of "meaningful" parameters exists anywhere, because none is needed.
- **`HelpContext::add()` for what the URL cannot say.** `/templates/{template}` serves blocks, alerts and static overlays alike - the discriminator is the model, not the query string. The controller injects `['type' => $template->type]` and `context: templates.show?type=alert` then matches through the identical code path as a real query parameter. One matcher; the controller decides what the meaningful discriminator is.
- **Every match is returned, best first**, rather than collapsing to a single winner in the backend - ranked by exact-name-over-wildcard, then constraints pinned down, then how literal the pattern was, then slug for a stable order. There is deliberately no `priority:` key: a page cannot buy rank, it earns it by being more specific.
- **Two guardrails, both verified to actually fail.** One asserts every declared context names a route that exists, which catches the rename that would otherwise kill contextual help silently. The other caps any single context at three pages, because left alone a generic route like `templates.index` accumulates every page that mentions templates until the help affordance is a link farm - now that is a test failure at authoring time, not a discovery two years later.
- **Shared as the `help` Inertia prop** (`HelpLink[]`, often empty). `HelpPage` gained `meta()` - a frontmatter-only read that stops at the closing delimiter, since the index reads every page on every request and slurping whole files to get at their first ten lines would mean ~200KB of I/O per request - plus `url()`, which route registration now calls so URL derivation cannot drift.
- **Tests:** 14 new (`HelpContextTest`), covering the matching rules, both guardrails, and end-to-end resolution of query-string and controller-injected context through real requests. Full suite **1098 passed**; Pint clean.

## July 28th, 2026 - feat(help): the help index is markdown too, so /help.md is a crawlable entry point

The page conversion left the two navigation pages (`/help` and `/help/bot`) as Vue card grids, which meant a machine could read every individual help page but had no way to *discover* them - the index it would land on was still an empty shell. Both are markdown now.

- **A slug ending in `index` maps to its parent path**, so `index.md` serves `/help` and `bot/index.md` serves `/help/bot` - not `/help/index`. Route names `help` and `help.bot` are unchanged, and a test asserts `help.index` never gets registered.
- **`/help.md` is the crawl entry point.** It carries a link to every other page, and a test asserts reachability transitively across all index pages, so a new page that nobody links to fails the build rather than sitting undiscoverable.
- The index grew section headings (Start here / Building overlays / The template language / Live data / Chat / Reference) instead of one undifferentiated run of 20 cards. Descriptions are unchanged, verbatim.
- `HelpCardGrid.vue` had no remaining callers and was removed with the two pages.
- **llms.txt audited against the codebase**, not against what it claimed. Verified: 11 formatters, 6 comparison operators with `=` (not `==`), nesting depth 10, foreach cap 50, 62 static tags, the sanitiser's stripped-element list, and all ten List read tags. All correct. One thing was **wrong**: it named two help URLs as having no markdown twin when there are three - `/help/gamejam` is still a Vue page and was unlisted. Fixed, with the reason given for each.
- llms.txt section 11 now leads with `/help.md` as the entry point rather than a flat link list.

## July 28th, 2026 - refactor(help): help pages are markdown, and machine-readable at last

The `/help/*` pages were hand-written Vue components, which meant their prose lived in the JS bundle and nowhere else: a machine fetching `/help/blocks` got 27 KB of `<head>` and **zero** content. The `llms.txt` links added earlier the same day pointed at exactly those dead ends. Help content is now markdown, rendered server-side, and fetchable as plain markdown.

- **Markdown is the source of truth.** `resources/help/pages/*.md` with flat `key: value` frontmatter. `App\Support\HelpPage` reads a page, renders it with `Str::markdown()` (`league/commonmark` was already available transitively - no new dependency), and hands it to one generic `help/Page.vue`. Adding a help page is now adding a file: routes are generated from the directory, so there is no route, no controller, and no component to write.
- **Every original route name and URL is preserved** (`help.conditionals`, `help.bot.aliases`, ...), including the 301 from the old `/help/bot/commands`. A test asserts all 18 by name, because these are referenced across the app and a rename would break links silently.
- **`/help/<slug>.md` serves the source verbatim** as `text/markdown; charset=utf-8` - byte-identical to what the HTML page renders, so there is nothing to build and nothing to drift. This replaced a first attempt that copied files into `public/`, which worked but served `application/octet-stream` and needed a build step to stay current.
- **Three things got better than a straight port.** The table of contents is now generated from the `##` headings instead of being a hand-maintained anchor list that could drift from the sections beneath it. Styling moved into one `help-prose.css` instead of `class="mb-4 text-foreground"` repeated on every paragraph across 19 files. Callouts use GitHub's `> [!NOTE]` syntax, so they read correctly as plain text in the raw `.md` *and* render as the same coloured boxes in the app.
- **Heading anchors strip the section number**, so "1. The third template type" anchors to `#the-third-template-type` and the link survives a renumbering.
- **KaTeX still works.** The Math page is full of equations, so TeX is lifted out *before* the CommonMark pass (otherwise `_` becomes emphasis and `\` gets eaten), then reinserted as `.help-math` elements that the page typesets with a lazily-imported KaTeX. Delimiters are `$$...$$` and `\(...\)` - deliberately not bare `$...$`, which would have swallowed the text between the currency amounts on the designers page.
- **Two pages stay Vue on purpose.** `/help/integration-presets` renders live from `controlPresets.ts` with a fuzzy search, and `/help/reference` is a search UI over the reference vault. Freezing either into markdown would fork them from their source - the exact failure the `dsl.json` work eliminated earlier the same day. The Controls page had the same trap in miniature (it read live preset *counts*), so the markdown describes the integrations and links to the catalogue rather than hardcoding numbers that would go stale.
- The conditionals event-tag catalogue - 41 event cards, 290 tags - was extracted programmatically from the component's data literal rather than retyped, so no tag was lost or mistyped in transit.
- 13 new tests covering rendering, frontmatter, generated TOC, anchor ids, callouts, TeX protection, the markdown twin, byte-identity with the source, route-name preservation, and slug traversal. Suite 1082 passed, 3014 assertions; ESLint, production build and Pint clean.
- `llms.txt` section 11 now points at the `.md` URLs with an explicit note that the bare URL returns an app shell, and names the two interactive pages that have no markdown twin.

## July 28th, 2026 - docs(llms): llms.txt at the site root so an LLM can author templates correctly

A complete, self-contained authoring guide served at `https://overlabels.com/llms.txt` (`public/llms.txt`, static, no route needed). The goal is narrow and testable: an LLM that reads only this file should be able to write a working static, alert, or block template without seeing the codebase.

- **Hard rules first**, because they are the ones an LLM will otherwise violate: no JavaScript (the sanitizer strips it), tags resolve exactly once and never reparse, three fields only, values are HTML-encoded, missing data renders nothing.
- **The full tag language**: the five tag shapes, all 11 formatters with their real argument behaviour, the three surprising `?? default` rules, conditionals with the single-`=` operator, and `foreach` with `loop.*` and `[[[raw]]]`.
- **Data reference per template type** - Twitch channel tags, `[[[c:...]]]` controls, the full List read-tag table including the live-ticking `:countdown`, and the `event.*` payload.
- **Three complete worked examples**, one per template type, each with head/html/css.
- **Two traps documented after verifying them against the code.** `event.type` is spelled differently by source: Twitch carries the raw dotted EventSub type (`channel.follow`, `channel.subscription.gift`), while external donation services normalise to bare words (`donation`, `subscription`, `shop_order`, `commission`) - which is exactly what lets one alert template serve every provider. And the right-hand side of a condition is always a literal, so `[[[if:channel_followers >= c:follower_goal]]]` compares against the string `c:follower_goal` rather than the control's value.
- **Block-specific rules** that exist nowhere else: the fill-the-box `:root { width:100%; height:100% }` pattern, `:root`/`html`/`body` being replaced rather than prefixed by the scoper, and the two documented scoping holes (`@keyframes` names are not renamed, so namespace them; write flat CSS because native nesting is opaque to the scoper).
- A gotchas table and a pointer to `resources/dsl/dsl.json` for anyone extending the engine rather than authoring for it.

## July 28th, 2026 - feat(dsl): one shared spec for the template language, eight divergences closed

The DSL vocabulary and lexical shape now live in one file, `resources/dsl/dsl.json`, read by `app/Support/Dsl.php` on the PHP side and `resources/js/utils/dsl.ts` on the TypeScript side. Every tag matcher in the codebase was rewired onto it, and the divergences the spec documented are fixed.

- **The shared spec** holds the lexical fragments, the 11-formatter table, the block keywords, the comparison operators (longest-first so `>=` beats `>`), the namespaces, and the limits. Character classes are written so they are byte-identical valid regex in both PCRE and ECMAScript, which is what lets one file drive two runtimes without a build step. The 62 static tag names deliberately stay out of it - those are per-user rows in `template_tags` generated from the Twitch payload, not code-level vocabulary.
- **Nine matchers rewired**: the overlay renderer, the bot resolver, the alert renderer, tag extraction, conditional extraction, service detection, the legacy `UserTemplate` path, the block tokenizer, and the condition parser. The hardcoded nesting cap of 10 now comes from the spec too. Nothing hand-rolls a tag regex any more.
- **D1** pipe args accept `%`, so `[[[x|number:0.0%]]]` no longer gets allowlisted server-side and then printed to the page as literal text. **D2** a key must start with a word character in both engines. **D5** conditions accept hyphens, which plain tags always did - `[[[if:my-tag = 1]]]` now contributes `my-tag` to the allowlist. **D6** the legacy matcher no longer accepts spaces and operators as part of a tag name. **D7** a condition may contain a lone `]`.
- **D3/D4 - `detectRequiredServices` was quietly wrong.** It ran a bespoke service-shaped regex with no `?? default` branch, so `[[[c:kofi:total ?? 0]]]` reported no dependency at all and the connect-this-service warning never fired on copy. It also only understood exactly two segments. It now runs the canonical pattern and inspects the resulting keys against the driver registry, which additionally stops `c:list:<slug>` being reported as an integration called "list".
- **D8 - found while writing the tests, and the only one users could see.** The pipe class includes a space (needed for `date:dd-MM-yyyy HH:mm`) and is greedy, so `[[[c:kofi:total|currency:EUR ?? none]]]` captured the pipe as `currency:EUR ` with a trailing space. PHP's `ExpressionFormatter::apply()` has always trimmed and shrugged it off; the TypeScript `parsePipe()` did not, so the overlay renderer handed `Intl.NumberFormat` the currency code `"EUR "` and threw. The same tag worked in a bot expression and broke in an overlay. Fixed on both sides: a pipe may no longer end on whitespace, and `parsePipe()` trims and lowercases like its PHP counterpart.
- 33 new tests, one per divergence plus the lexical shape: `tests/Unit/DslTest.php` and `tests/Feature/DetectRequiredServicesTest.php`. Full suite 1069 passed, 2960 assertions; ESLint, production Vite build and Pint all clean.
- The spec doc keeps the D1-D8 sections as the record of what went wrong. They are why the shared file exists.

## July 28th, 2026 - docs(dsl): specify the Overlabels DSL and retire the Recipes milestone

The template language grew by accretion over a year and was never written down. It is now specified at `docs/design/overlabels-dsl-spec.md`, written descriptively first - sections 1-6 document what the language actually does today, derived by reading every implementation, before section 7 proposes anything.

- **The language is small**: 79 closed terms (62 static Twitch tags, 11 formatters, 6 block keywords, 6 comparison operators, 2 operators), plus open namespaces (`c:*`, `c:list:*`, `event.*`, `bot:*`, foreach `alias.*`/`loop.*`). The design insight the spec turns on: closed terms validate by lookup, open namespaces validate by resolution against the user's own controls and lists - which Expression Controls already do.
- **Five independent tag-matching regexes were found, not two.** The three substitution engines (`tagParser.ts`, `BotExpressionResolver`, `AlertExpressionRenderer`) are byte-identical, so the rendered language is consistent across overlays, bot replies and alerts. The outlier is the extraction engine (`OverlayTemplate::extractTemplateTags`), which decides which data is fetched at all - when extraction and substitution disagree, a tag silently resolves to empty because its value was never requested.
- **Seven divergences documented (D1-D7)**, all unintentional. Sharpest is D3: `detectRequiredServices` was never taught about `??`, so `[[[c:kofi:total ?? 0]]]` does not register Ko-fi as a required service and the connect-this-service warning never fires.
- **The language already detects malformation and throws it away** (section 5.6): unmatched `if`/`foreach`, stray `else`/`endif`, and nesting past depth 10 are all caught at render time, then met with a `console.warn` and a silent abort. Detection exists; only reporting is missing.
- Documented drift found along the way: 11 formatters ship, 8 are documented (`distance`, `speed`, `login` and `mention` arrived without the docs following).
- **Milestone 9 (Recipes: Producer Layer) is retired** and replaced by M13 - the Builder: drag & drop, and Blocks that feel real before placement. The Recipes engine stays in the codebase because `OptionSet` and `Picker` are load-bearing for Lists, but the shippable surface is not going to be built: it was aimed at developers, and it is not easy, which is the opposite of the direction. The completed-milestones entry now carries that reasoning so it is not re-proposed.
- Bot Expression follow-ups moved to the Backlog and triaged: per-user cooldowns and the outbox cadence doc stay, `c:` validation and tag autocomplete fold into the DSL validator, `!commands` is rejected on privacy grounds, and the anti-spam cap on `bot_chat_outbox` is rejected by design - the outbox swallows everything and surge protection belongs upstream of it.

## July 27th, 2026 - chore(deps): dependency audit and in-range updates

A security sweep across both dependency trees. `npm audit` and `composer audit` both report zero advisories against 366 npm packages and the full Composer tree, so nothing here was a fix - these are routine currency updates applied while the trees were clean.

- **Composer**: `laravel/framework` v13.21.1 -> v13.23.0 (the only outdated direct dependency; everything else was already current).
- **npm**: 12 packages moved within their existing semver ranges - `@codemirror/view` 6.43.7, `@lucide/vue` 1.27.0, `@vue/devtools-api` 8.2.1 (plus devtools-kit/shared), `eslint` 10.8.0, `concurrently` 10.0.4, `@types/node` 26.1.2, and transitive bumps to `postcss`, `minimatch`, `es-toolkit`, and `@eslint/config-helpers`.
- **Manifests unchanged**: only `composer.lock` and `package-lock.json` moved. No declared ranges were widened, so nothing new entered either tree.
- **Held back**: `typescript` 6.0.3 -> 7.0.2 is a major and stays pinned at `^6.0.3` pending a deliberate upgrade pass (it drags `vue-tsc` and `typescript-eslint` compatibility with it). The two `MISSING` optional deps in `npm outdated` (`@tailwindcss/oxide-linux-x64-gnu`, `lightningcss-linux-x64-gnu`) are Linux-only binaries that correctly skip installation on the Windows dev machine and resolve in the production image.
- Verified with a production Vite build, ESLint, and the full Pest suite: 1036 passed, 2900 assertions.

## July 26th, 2026 - docs(help): The Builder and Blocks help pages

Two new help docs covering the block-based composing story, both in the established help-page format (TOC, numbered sections, callouts, bottom line) and linked from the help index:

- **/help/builder** - for composers: what the Builder is (assembly, not a separate runtime), the grid and picker, drag/keyboard/preview, why saving yields a plain static overlay (with the actual compiled CSS shown), how controls come along (shared keys sync, removal keeps values), refresh from source, and the one-way eject door.
- **/help/blocks** - for block authors: the third template type, creating from scratch or via Copy-as-Block, the fill-the-box CSS pattern (`width/height: 100%` against the definite-size wrapper), compile-time selector scoping with `:root`/`body` mapping (shown before/after), the keyframes/font-face global passthrough caveat, controls traveling with defaults + key-sharing design advice, snapshot semantics as a two-way trust promise, and publishing guidelines.
- Both use HelpLayout (meta/OG boilerplate handled), routes registered as `help.builder` and `help.blocks`, two new cards on the help index (LayoutGrid and Blocks icons).

A block styled with `height: 100%` filled its cell in the rendered overlay but not in the Builder's placement preview - full width, shrunk height. The preview iframe's document reset set no height on `html`/`body`, so a percentage height inside resolved against an indefinite parent and collapsed to auto (width was unaffected because block elements fill width by default). The reset now includes `height: 100%`, which also makes the preview environment faithfully mirror the compiled one, where the block wrapper is a grid item with a definite height. One line, preview-only - compiled output was always correct.

## July 25th, 2026 - fix(builder): renamed blocks update their placement labels

Renaming a block did not change its label on Builder placements: drift detection deliberately compares only head/html/css (drift = rendered output), so a name-only change never qualified for the refresh flow - and never will, because the name is a provenance label, not output. Labels are now treated as live instead of snapshot-frozen: whenever the editor sees a fresh source (the mount/refocus check or a picker fetch), placement names silently follow it - no badge, no refresh ceremony, persisted with the next save. Descriptions were never stored on placements; the picker already shows them fresh per page load.

## July 25th, 2026 - feat(builder): "used by block" pill on the Controls tab

On builder-composed overlays, template controls that a placed block references now carry a pill in the same visual family as the service-managed label - block icon plus the block's name (first name +N when several blocks share the key), tooltip listing all of them. Deliberately "used by", not "came from": it is computed by scanning the placement snapshots in `metadata.builder` for `[[[c:key]]]` and `[[[if:c:key ...]]]` references, so it is always true, even for a hand-made control that a block happens to use. No lock - block controls stay fully editable. Composes with the existing badge: referenced by a placed block = block pill, referenced by none = "Not used by any block". Client-side only, zero backend.

Editing a block after placing it left the placement on the old snapshot, and the only remedy was remove + re-place, losing position and size. Now the Builder notices and offers to sync - explicitly, in the editing session only.

- **Detection**: on editor mount and window refocus (throttled to once per 10s), the current snapshots of all distinct placed blocks are fetched and string-compared against the stored ones. String comparison is ground truth - no timestamps to store, and it works retroactively on every composed overlay saved before this feature existed. Covers the edit-the-block-in-another-tab round trip.
- **UI**: an amber "Source updated" badge on each drifted placement, a "Refresh from source" button in the selected-block panel, and a bar above the canvas - "The source of N placed blocks changed. Sync into this session?" - with a Sync all action.
- **Refresh** re-takes the snapshot in place: same instance_id, position, and size (remove + re-add without losing the layout). The source's controls are registered like a fresh placement, so new control keys ride along at the next save. On the edit page a refresh marks the form dirty.
- **The snapshot invariant is untouched**: nothing propagates to a saved overlay until the owner saves. A deleted or newly-private source is skipped silently - the placement keeps rendering its snapshot, which is the invariant working as intended. The picker's fetch feeds the same source cache, so a just-placed block can never be flagged stale by an older background check or silently downgraded by a refresh.
- Zero backend changes; this is step 1 of the Hot Blocks ladder (docs/design/hot-blocks-idea.md, local).

Copy a static overlay as a Block and the new block's breadcrumb said "My static overlays" forever, even when you clicked it from the My blocks list. Root cause in `useListContext`: the show page freezes the freshest global list context for a template on its first mount - and right after a copy, the freshest list you visited is still the SOURCE's list. The frozen origin then wins over every later navigation by design, so the wrong crumb stuck for the whole tab session.

- **Fix**: a stored context (frozen or global) is only trusted when the template could actually appear in the list it points to - the candidate's `type`/`filter` params are checked against the template's own type and ownership. Impossible contexts are rejected and the frozen origin is re-written, which also self-heals any already-poisoned sessionStorage entries.
- Same fix covers the unreported sibling: creating a block right after browsing the static list would freeze the wrong crumb the same way.
- `captureListContext` now takes the template's raw attributes and derives the fallback itself; the crumb-and-delete-redirect agreement (the reason freezing exists) is unchanged.

The Copy action on a static overlay now asks what the copy should become: a static overlay (the existing behavior) or a Block for the Builder. That turns the entire public library of static overlays into potential Builder material - see a community overlay you like, copy it as a Block, place it on your grid.

- **Choice dialog** on the show and edit pages' Copy action, static templates only. Alerts (and blocks) copy as themselves with the existing confirm, no dialog.
- **Backend**: the fork endpoint accepts an optional `type` (`static`/`block`, anything else 422s) which is only honored when the source is static - alerts can never be converted into, or out of, anything. Copying a Builder-composed overlay as a Block keeps the compiled output but drops the grid editing state (a block is a leaf piece, not a grid of other blocks); copying it as a static overlay keeps the grid fully editable.
- Controls still flow through the existing import wizard regardless of target type - blocks carry controls, so nothing changes there.
- The quick-copy actions on the templates table/cards and the public preview page keep the direct static-to-static behavior (they are full-navigation form posts, no dialog surface).
- **Tests**: 5 new in BlockTemplateTest - static-to-block, default stays static, builder-composed conversion (grid state dropped as block, kept as static), alert ignores the requested type, `type=alert` rejected. 14/14 green.

Removing a block from the canvas keeps the controls it brought along - deliberately, so a counter at 500 survives a layout experiment and re-adding the block picks it right back up. But the leftovers were invisible, and a few removed blocks could quietly pile up a heap of stray controls. Inform, don't gate:

- **Badge**: on builder-composed overlays, the Controls tab flags template controls whose `c:{key}` tag no longer appears anywhere in the compiled output with "Not used by any block" (amber pill, tooltip explains the value is preserved and how to reattach or delete). Computed purely client-side from `template_tags`, which the server already re-extracts on every save - including piped, defaulted, and `[[[if:...]]]` conditional references, so a control used only in a condition is never falsely flagged.
- **Save toast**: a builder-mode save that leaves orphans behind says so - "Overlay saved. N controls are no longer used by any block - review them on the Controls tab."
- No auto-delete, by design: shared keys mean another block may still use the control, expressions and system keys (like `tts`) can reference controls outside the overlay HTML, and silent data loss is worse than a visible leftover. Deletion stays one deliberate click on the Controls tab.
- Zero backend changes.

Placing a block that carries controls, saving, and opening the Controls tab showed... nothing until a hard refresh. Two stacked causes on the edit page: the controls import fires in `onSuccess`, which is AFTER Inertia already delivered the refreshed page props (so `props.controls` predates the import), and the Controls/Values tabs render from `localControls`, which is copied from props once at mount and never re-synced.

- **Fix**: the import endpoint already returns the created control models, so the `.then` now merges `data.created` into `localControls` - no extra request, no server change. The tab contents are `v-if`-mounted per switch, so they pick the merged list up immediately.
- Controls are still created at save time, not at placement - placing a block and abandoning the session must not leave stray controls behind.
- The standalone `/builder` page was never affected (it navigates to the show page with a fresh load after save).

Move/arrow buttons worked but felt like a miss the moment you tried them: you want to grab a block and drag it. Now you can - placed blocks drag cell-to-cell across the grid, live-snapping as you go.

- **Drag semantics**: the block follows the pointer with its grab point preserved (no jump-to-top-left), snapped to grid cells. Every candidate position runs through the same `fits()` occupancy check as the buttons and keyboard, so a drag can never overlap another block - it visibly stops at the last valid cell and drops wherever it sits when you release. Dragging across an occupied region to free space on the other side works.
- **Pointer capture** on the placement is the load-bearing trick: block previews are iframes, which swallow pointer events - capture keeps move/up events flowing for the whole drag, and retargets the trailing click so releasing over an empty cell does not pop the block picker or deselect.
- **Click vs drag**: a 5px slack keeps plain clicks as select. Buttons, arrow keys, and Delete all still work; grab/grabbing cursors signal draggability; `touch-none` makes it behave on touch screens.
- **State**: one new `moveTo(id, x, y)` absolute move in `useBuilderState` (the drag target), wired into both the standalone Builder page and the edit-page BuilderEditor (which marks the form dirty only when a block actually moved).

## July 25th, 2026 - fix(builder): canvas actually scales down to fit the page

First bug from real use: the Builder canvas rendered at a full 1920x1080 with `scale` stuck at 1. Root cause was layout, not the ResizeObserver: `transform: scale()` never affects layout size, so the 1920px-wide grid still occupied 1920px, and the `1fr` column track in the page layout (`grid-cols-[1fr_280px]`) has an `auto` minimum that grows to fit content. The column stretched to 1920px, the wrapper measured 1920px, and `1920 / 1920 = 1`.

- **Fix**: `minmax(0,1fr)` for the canvas column plus `min-w-0` on the column div, in both `builder/create.vue` and `BuilderEditor.vue` (edit page had the same bug). The column now sizes to the free space and the canvas scales to fit it.
- **Checkerboard background**: swapped leftover Tailwind v3 `theme(colors.sidebar.DEFAULT)` syntax for `var(--color-sidebar)` + `bg-size-[32px_32px]` so the empty-canvas checkerboard actually renders in v4.

The assembly gap, closed: users who do not write HTML can now open `/builder`, set up a grid (12x8 default on a 1920x1080 canvas), click a cell, pick a block from the library, and save. The output is a plain static overlay - the entire existing machinery (render pipeline, Add to OBS, tokens, alerts, controls, WebSockets) works on it untouched, because the Builder compiles down to the same three head/html/css strings every template is made of. Zero new engine.

- **Builder page** (`/builder`, also in the command palette and next to Create Overlay on the templates index): grid controls with presets, click-to-place picker with search, click-to-select with a move/resize panel, arrow keys to move, Shift + arrows to resize, Delete to remove. Pixel-true block previews in sandboxed iframes with sample data. Full-overlay preview dialog renders the actual compiled output.
- **Compile step** (`composeBuilderTemplate` + `prefixCss`, both pure and dependency-free): grid container CSS + one wrapper div per placement with `grid-area`, and every block's CSS prefix-scoped to its instance wrapper so two blocks styling `.label` can never collide. `:root`/`html`/`body` selectors map onto the wrapper; `@media` recurses; `@keyframes`/`@font-face` pass through (limits documented for block authors). Composed output then flows through the exact same sanitize -> UnoCSS -> store path as a hand-written overlay.
- **Snapshot semantics**: placing a block copies its code at placement time into `metadata.builder` (version-stamped: grid, canvas, placements with snapshots). Blocks are never dereferenced again - a block author editing or deleting theirs can never break a composed overlay. Strict server validation (max 40 placements, 64KB per snapshot field, grid capped at 24x24) and snapshots are run through HtmlSanitizationService on every save.
- **Controls carryover**: blocks bring their non-service controls along at save via the existing controls import endpoint - missing keys are created, existing keys shared, so blocks using the same control key stay in sync (that is a feature).
- **Re-edit + eject**: a composed overlay's edit page shows the grid editor on the Code tab (state restored from metadata). "Open in code editor" in the actions menu converts it to a hand-edited overlay after a confirmation - one-way door, compiled code stays byte-identical. One-click stays inspectable.
- **Endpoints**: `GET /builder`, `GET /templates/blocks/library`, `GET /templates/blocks/{id}/snapshot` (block-only, public-or-owner, non-service controls). Three slim methods on the existing controller, no new abstractions.
- **Tests**: new `BuilderTemplateTest` (11 tests): metadata round-trip and validation bounds, snapshot script-stripping, eject, snapshot endpoint auth, library visibility, edit-page prop gating, controls import collision. Full suite green (1031 tests).

## July 25th, 2026 - feat(templates): add the block template type (Builder groundwork, part 1)

First slice of the Builder: a third template type, `block`. Blocks are reusable mini-templates (head/html/css, tags and controls all work) that experienced authors publish and that the upcoming Builder will let anyone place on a CSS grid to compose an overlay without writing code.

- **Migration**: `type` gains `'block'` alongside `static`/`alert`. The column is a varchar + CHECK constraint (Laravel `enum()`), not a native PG enum, so this is a transactional constraint swap. The constraint name is discovered at runtime via `pg_constraint` rather than hardcoded.
- **Backend**: `scopeBlock()` and `isBuilderComposed()` on OverlayTemplate; store/update validation accepts `block`; `RefreshTemplateTags` and the public OG label know the third type; factory got an explicit `type` plus `alert()`/`block()` states.
- **Metadata surface**: store/update now accept a `metadata` payload, strictly validated and stripped to known namespaced keys (`block` for now, `builder` coming). Blocks carry `metadata.block.default_span` - the suggested width x height in grid cells when placed in the Builder (editable on create and edit pages).
- **Authoring UX**: third radio card on the create page, Block option in the templates index filter (with a Blocks icon and "My blocks" command palette entry), suggested-size inputs plus author tips (style the block, not `body`; keep CSS flat).
- **Deliberately unchanged**: alert targeting, event triggers, and onboarding all filter positively on `static`/`alert`, so blocks are excluded automatically - locked in with regression tests. Blocks stay renderable via the overlay pipeline for author preview, but the Add to OBS tab is hidden for them (a block is an ingredient, not a standalone overlay).
- **Tests**: new `BlockTemplateTest` (9 tests): type CRUD + validation, metadata stripping/bounds, no-wipe on metadata-less saves, scope, alert-targeting rejection, static-picker exclusion, render pipeline regression. Full suite green (1020 tests).

## July 24th, 2026 - refactor(welcome): rebuild the homepage as a static blade page for SEO

The homepage was an Inertia/Vue page, so crawlers got an empty `@inertia` div plus a JSON blob and all content only existed after JavaScript ran. It is now a server-rendered blade page: the full marketing copy ships as static HTML in the initial response, indexable by every search engine without a JS render pass. Same visual result, massively lighter page.

- **New `resources/views/welcome.blade.php`** with one partial per section under `resources/views/welcome/` (navbar, hero, syntax, controls, conditionals, events, integrations, kits, onboarding, cta, footer, plus shared theme-toggle and login-social partials). Markup ported 1:1 from the Vue components - same Tailwind classes, same copy.
- **Zero Vue on the homepage**: the page loads a new `resources/js/welcome/app.ts` entry that imports the app CSS and wires the only interactive bits in ~100 lines of vanilla JS: theme switcher (light/dark/sepia/system, same localStorage + cookie contract as `useAppearance`), mobile menu, and the two tab groups (syntax examples, integrations). Lucide icons are inlined as SVGs.
- **All tab panels are now in the DOM at load** (previously `v-show`), so the Live CSS / Alerts examples and all six integration descriptions are crawlable text too.
- **Playground section removed** on purpose - it saw no use, and the planned overlay builder will live inside the app, not on the marketing page. `SectionPlayground.vue` is deleted with the rest; git history keeps it. The `/` route no longer needs `TemplateDataMapperService` sample data.
- **Deleted** `pages/Welcome.vue` and all 12 `components/welcome/*.vue` components.
- **Meta tags consolidated**: the page owns its own `<head>` (title, description, canonical, Open Graph, Twitter card) instead of the previous split between `app.blade.php` defaults and Inertia `<Head>` overrides.
- Verified: production build green, ESLint clean, full Pest suite green (1011 tests), and `curl` against the local site returns ~99 KB of static HTML with all sections present and no Inertia payload.

## July 24th, 2026 - refactor(nav): declutter the navigation and user menu

The navigation had grown scattered: app content hid inside the user menu, sensitive developer pages sat one hover away in a dropdown submenu, and the user-menu trigger didn't look clickable. This restructure gives every link one logical home - no new chrome, same sidebar-plus-header layout.

- **User menu slimmed to personal items only**: Account Settings, Integrations, Log out. "Event alerts overview", the duplicate "Learn" link, and the whole "Sensitive Data" submenu are gone from it.
- **"Triggers" moved into the sidebar** under My stuff (next to Alerts, where it belongs - it's app content, not an account setting). The page, breadcrumb, and browser title renamed from "Event alerts overview" to "Triggers", matching the vocabulary of the alert template edit page's Triggers tab.
- **Sensitive pages relocated to Settings > Developer tools**: Token Generator, Tags Generator, Your Twitch Data, and Testing Guide now live in a labeled section of the settings sub-nav, with a "avoid opening these on stream" hint. Reaching them takes a deliberate trip into Settings instead of an accidental hover - they also remain reachable via Ctrl+Space.
- **User-menu trigger is now visibly clickable**: chevron that flips when open, and the avatar no longer renders an invalid nested `<button>` inside the dropdown trigger button.
- **Small sidebar fixes**: Routes got its own MapPin icon (it shared the Radio icon with Streams), and the "Learn" item inside the Learn group is now "Help".
- **Command palette synced**: added My lists, Updates, Bot Expressions, Bot Aliases, Tags Generator, Settings Usage and Controls; "Alerts builder" renamed to "Alert triggers"; the Tools section is now "Developer tools" to match Settings.

## July 23rd, 2026 - chore(deps): update Composer and npm dependencies

Routine dependency refresh across both package managers, plus a fix for a real type regression the update surfaced.

- **Composer**: `laravel/framework` 13.16.1 -> 13.21.1, plus minor/patch bumps to Reverb, Sanctum, Socialite, Telescope, Sail, Pest, and their transitive deps. All patch/minor - no majors pending. Full Pest suite green (1011 tests).
- **npm, in-range**: all packages with `Wanted === Latest` bumped via `npm update` (reka-ui, pusher-js, laravel-echo, vite, tailwindcss, eslint, typescript-eslint, and others).
- **npm, major bumps taken**: `pinia` 3 -> 4 (now requires `@vue/devtools-api` as an explicit peer, added as a direct dependency), `katex` 0.17 -> 0.18 (its 0.18 breaking change renames internal CSS classes, but this app only uses KaTeX's own bundled stylesheet so nothing to update), `@types/node` 25 -> 26. Removed `@types/katex` as a dependency - katex now ships its own types (`types/katex.d.ts`).
- **npm, major bump skipped**: `typescript` 6 -> 7. `typescript-eslint@8.65.0` (current latest) declares a peer range of `>=4.8.4 <6.1.0`, so the lint toolchain doesn't support TS 7 yet. Left pinned at `^6.0.3`.
- **Security**: `concurrently`'s transitive `shell-quote` had a ReDoS advisory (GHSA-395f-4hp3-45gv). Rather than downgrade `concurrently`, pinned `shell-quote` to `^1.10.0` via the existing `overrides` block (same pattern already used for `ws`). `npm audit` now reports 0 vulnerabilities.
- **Fixed real regression**: `reka-ui` 2.10.0 shipped "enhanced type inference for `useForwardPropsEmits`" - which correctly surfaced that our `Tabs.vue` wrapper extended the generic `TabsRootProps` unparameterized, defaulting its `defaultValue`/`modelValue` types to the wide `StringOrNumber` union instead of the `string` our own `emits` and the actual component use. Fixed by extending `TabsRootProps<string>` explicitly. Type-only, `defaultValue` isn't even used anywhere in the app yet, so no runtime behavior changed.
- Verified with `vue-tsc --noEmit`, `npm run lint`, `npm run build`, and `php artisan test` - all clean.

## July 6th, 2026 - fix(templates): stop the templates list crashing when a filter is changed from a bare URL

Visiting `/templates` with no query string and then changing the Ownership (or any) filter threw a 500: `column "function sort() { [native code] }" does not exist`. The ORDER BY was literally receiving the string form of JavaScript's native `Array.prototype.sort`.

- **Root cause**: an empty `$request->only([...])` returns a PHP `[]`, which serializes to a JSON array. On the Vue side `props.filters` then arrived as `[]` instead of `{}`, so `normalizeFilters` read `input?.sort` / `input?.filter` off an array and got the native `Array.prototype.sort` / `.filter` methods (truthy functions) instead of strings. That is also why both selects rendered "unset" - no `<option value>` matches a function. On the next `applyFilter`, `buildQuery` saw a truthy non-`'created_at'` value and serialized the function into the URL, which reached Postgres as an ORDER BY column name.
- **Fix at the source** (`OverlayTemplateController@index`): cast the filters payload to `(object)` so the empty case serializes as `{}`, never `[]`.
- **Defense in depth** (`templates/index.vue` `normalizeFilters`): only accept string values, so a non-string can never again leak into the query string regardless of the payload shape.

## July 5th, 2026 - docs(bot): rename the "Bot Commands" help page to "Bot Expressions"

The bot's user-facing feature is branded "Expressions" - and the dashboard route to author them is `/settings/bot/expressions` - but the help doc still said "Bot Commands" and lived at `/help/bot/commands`. That discrepancy is gone: the page is now "Bot Expressions" throughout, at `/help/bot/expressions`.

- **Route renamed**: `/help/bot/commands` -> `/help/bot/expressions`, route name `help.bot.commands` -> `help.bot.expressions`. The Vue file stays `help/bot/Commands.vue` (only its route and copy changed). Sitemap entry updated. A `Route::redirect(..., 301)` keeps the old `/help/bot/commands` URL working for any bookmarks or search-indexed links.
- **Copy fully purged** of the word "command" in prose: title, hero, breadcrumb, TOC, section headings, warning block, and card descriptions now read "expression" (e.g. "Control expressions are OFF by default", "Your own expressions", "meta-expression"). Left untouched on purpose: literal syntax tokens (`!command`, `!cmd`, `!commands`, `!ol cmd`), the other bots' owned command names, and the simulated bot replies (they mirror what the bot actually says).
- **Callers updated**: the Bot help index nav card, the two links from the Aliases page, and the Settings > Integrations bot blurb all point at the new route and read "Bot Expressions".
- The `localStorage` acknowledgment key (`help.bot.commands.controlsWarningAck`) was intentionally left unchanged so streamers who already dismissed the controls-access warning don't see it again.

## July 4th, 2026 - fix(events): hide the redundant bare sub that Twitch fires next to every resub

A resub emits two EventSub events at the same instant - `channel.subscribe` and `channel.subscription.message` - so the feed always showed a pointless pair: "JP_4468 resub T1" directly above "JP_4468 sub T1". Now the bare sub is folded away and only the resub row remains.

- Display-only, same posture as the gift-sub folding right above it: both events stay recorded, replay and pagination are untouched, nothing changes at the source. Handled in `EventsTable.vue`'s `displayRows` as a second pass that shares the gift pass's `claimed` set.
- Matches a `channel.subscription.message` to the closest unclaimed non-gift `channel.subscribe` from the same `user_id` (and broadcaster) within a 2-minute window, then hides that sub. The window is deliberately short so a user's genuine original subscribe from a previous month - also a `channel.subscribe` - is never mistaken for the duplicate of a current resub if it happens to share the page. Standalone new subs and gift recipients are left alone.

## July 4th, 2026 - feat(events): enable/disable event types in the feed instead of a single-pick dropdown

The feed's "Event type" filter was a single-select dropdown: you could look at exactly one type at a time, which is the opposite of what you want. What you actually want is to permanently hide the noise - `channel.channel_points_custom_reward_redemption.update`, `channel.poll.progress` - and keep everything else. So the dropdown is now a checkbox list: every type you receive, each toggleable, all shown by default.

- **Subtractive by design.** We store the HIDDEN set, not the enabled set, so any event type Twitch adds later shows up by default instead of silently vanishing until you notice. "Show all" / "Hide all" shortcuts, plus a live "(5/7 shown)" count.
- **Sticky per device.** The choice persists in localStorage (`overlabels:hidden-event-types`), so the noise you hid stays hidden across visits - no DB, no per-account migration. Shared util `resources/js/utils/hiddenEventTypes.ts` keeps the dashboard page and the token phone feed reading/writing the same key.
- **Filtered server-side**, not just hidden in the DOM: the hidden set rides along as a `hidden_types` query param and `UnifiedEventFeedService` applies `WHERE event_type NOT IN (...)` to both the Twitch and external subqueries, so pagination counts stay honest. Capped at 100 types so a crafted query string can't balloon the clause. The type checkboxes are driven by `facets` (independent of the filter), so "Hide all" never hides the controls to turn them back on.
- Both feeds updated: `/dashboard/events` (Inertia) and the token `/events/feed` (fetch). On the dashboard, localStorage is invisible to the server-rendered first paint, so the page re-applies once on mount if this device has hidden types the initial query lacked. The phone feed reads the set before its opening fetch, so it is filtered from the first frame.
- Filled out `EVENT_TYPE_LABELS` so poll / hype train / goal / points-updated and the external recurring/extra/membership/wishlist types render friendly names in the checkbox list instead of raw event keys.

Tests: new Pest case covering `hidden_types` exclusion across both sources (single + comma-separated). Feed suites green (30 tests).

## July 3rd, 2026 - feat(events): shape-only provider icons in the activity feed

The feed identified each event's source with a single colored dot. Color alone is a weak channel: it collapses for viewers with a color vision deficiency, and an IRL streamer checking their phone in bright sunlight is effectively colorblind too - washed-out mobile screens flatten the palette. So identity now rides on shape, not color.

- Each source gets a distinct monochrome 4x4 grid icon (Twitch ring, Fourthwall x, StreamElements top/bottom bars, StreamLabs side bars, Buy Me a Coffee checker, Ko-fi solid base, Throne corner block). Encoded as a `uint16` bitmap - a binary literal reads exactly like the grid - and rendered as an SVG that inherits `currentColor`, so it gets maximum contrast in both light and dark mode with zero color logic.
- Shape is the only identity channel: no color, no baked-in text. The event text sits right next to the icon, and each icon carries a `role="img"` + `aria-label` so screen readers still announce the source. Readable at 16px, in sunlight, and under any color vision deficiency.
- The set is built so every pair of icons differs by at least 6 filled cells (current min is 8), which is what keeps them distinct at a glance. `iconDistance()` enforces this when adding a provider later.
- `resources/js/utils/providerIcons.ts` (pure encoding module, mirrors `formatters.ts`) + `ProviderIcon.vue`. Wired into `EventsTable.vue`, replacing the color-coded source/event dot. Gift-sub recipient sub-rows use a small neutral dot (their source is obvious from the gifter's Twitch icon above). `TemplateTable.vue` keeps its colored event-type dot - that is a different question ("which event triggers this template"), not a source identity.

## July 3rd, 2026 - feat(events): collapse gift-sub bombs into one expandable row

A single gift-sub bomb landed in the feed as N+1 loose rows: one "gifted subs" line plus a separate "subscribed" line for every recipient, drowning out everything around it. StreamElements folds these into one tidy row you can expand; now so do we.

- `EventsTable.vue` groups on the client: each `channel.subscription.gift` event claims the next `total` `channel.subscribe` events with `is_gift: true` that share its broadcaster and tier, walking oldest to newest. The gifter keeps its own row; the recipients fold underneath it.
- A small gift pill next to the gifter's name shows the recipient count and toggles the list open. It uses `@click.stop` so it never trips the row's replay-confirm popover, and the recipients render as indented name + tier rows.
- Display-only: no backend, migration, or query changes. `is_gift`, `total`, `tier` and `user_name` were already in `event_data`, so replay routes, the token-auth path and pagination are all untouched. Applies everywhere `EventsTable` renders (token feed and the dashboard event pages).
- Graceful edges: a bomb only splits if a page boundary falls mid-burst (rare, since recipients arrive within ~a second), in which case it degrades to the old individual rows. Two simultaneous same-tier bombs can't be perfectly disambiguated - Twitch never links recipients back to the gifter - so recipients attach to the nearest preceding gift.

## July 3rd, 2026 - feat(events): replay alerts from the token-authed events feed

The feed shipped view-only (mute was the single token write), but replaying an alert from your phone mid-stream is exactly what the feed is for. Replay is now the second write an overlay token can perform.

**Backend**

- New token-authed endpoints: `POST /api/events/{id}/replay` (Twitch) and `POST /api/external-events/{id}/replay` (Ko-fi/StreamLabs/...). Same posture as the mute toggle: `throttle:overlay` + `lockdown`, Sanctum-stateful shed, token in the JSON body, `write` ability required, successful replays land in the token's access log (`events-feed:replay`).
- Foreign event ids return 404 (not 403) so a token cannot probe which ids exist for other users.
- The replay cores were extracted out of the session-bound actions into `replayForUser(User, event)` on `TwitchEventSubController` / `ExternalEventController` - dashboard replay and feed replay run the exact same logic (ownership, mute guard, mapping resolution, broadcast + TTS + bot message). Dashboard behavior unchanged, including flash messages.
- Muted replay still returns the "Alerts are muted" warning - the feed gets the same no-bypass rule as the dashboard.

**Frontend**

- `EventsTable` lost the `readonly` prop and gained an optional `token` prop: without it replay posts via Inertia as before; with it replay posts to the token API via fetch and emits a `replay-result` event (the feed has no Inertia flash messages).
- `EventsFeed` shows the replay outcome in an inline notice (violet success / amber warning / red error, auto-clears) and passes the token through.
- Feed info dialog and the feed-link warning copy updated: the link can now also replay alerts on stream, and the warning says so explicitly.

Tests: 6 new Pest tests covering write-ability replay for both event kinds, read-only 403, foreign-event 404, muted warning, and missing-mapping 422. Full suite 1010 green.

## July 3rd, 2026 - fix(events): readonly guard on the replay confirm popover

Clicking a row on the token-authed events feed crashed with `page$1.get() is undefined`. The `readonly` guard covered the row's own `@click` and `openConfirm()`, but Reka's `PopoverTrigger` toggles open state on its own click, so `@update:open` set `confirmingId` unconditionally and the "Replay?" confirm still opened - and its Yes button calls Inertia's `router.post`, which has no page state on the feed (plain `createApp`, no Inertia).

- `EventsTable.vue`: `@update:open` now checks `canReplay(event)` before opening the confirm, closing the trigger path that bypassed the readonly guard.

## July 3rd, 2026 - feat(events): one-click feed link + QR from the recents page

Closes the "how do I even get the feed URL onto my phone" gap from the feed feature: plaintext tokens are shown once, so no page could reconstruct the link after the fact. Now the recents page mints it for you.

- **`TokenUrlDialog`** - the token-URL machinery extracted from `AddToObsButton` (link warning, `tokens.store` POST, fragment URL assembly, copy-to-clipboard box, QR code) into one shared dialog with `instructions`/`footer` slots. `AddToObsButton` is now a thin wrapper around it with identical behavior and copy.
- **`EventsFeedLinkButton`** - replaces the "Embed view" link on `/dashboard/recents` (which pointed at the session-locked `/dashboard/events`). One click mints a fresh token **scoped to `read,write`** (tighter than the unrestricted default), named "Events feed" so it's recognizable on the Tokens page, and shows `/events/feed#<token>` with the QR code open by default - the phone is the whole point. Copy warns that the link reads your history and can mute your alerts, and points at token revocation if it leaks.
- ESLint + vite build clean; no backend changes.

## July 3rd, 2026 - feat(events): token-authed events feed + one-click global alert mute

Two user-requested pieces that make the events page usable mid-stream from a phone. First: `/dashboard/events` required a full Twitch login, painful on mobile where you're usually not logged in to Twitch. Second: there was no way to silence every alert at once (StreamElements has this; now so do we).

**Token-authed events feed (`/events/feed#<token>`)**

- New standalone page (own Vite entry, `resources/js/events-feed/`) authenticated by the same `OverlayAccessToken` overlays use: token in the URL fragment, read client-side, never sent to the server in a URL. Mirrors the overlay shell + the `/api/lists/{slug}?token=` precedent.
- New stateless endpoints: `GET /api/events` (filters, facets, pagination - same query as the dashboard page, extracted into `UnifiedEventFeedService`) and `POST /api/events/mute`. Both `throttle:overlay` + `lockdown`, Sanctum-stateful shed.
- **Token abilities are now enforced for the first time**: the feed requires `read`, the mute toggle requires `write`. Tokens with no abilities set remain unrestricted (matches `hasAbility()`), so every existing overlay token keeps working. The mute toggle is deliberately the only write an overlay token can perform, and each mute write lands in the token's access log.
- Live updates via the existing overlay broadcasting auth endpoint (it already signs `twitch-events`/`alerts` channels for a token): new events refresh page 1 debounced; the mute state flips live no matter where it was toggled from.
- `EventsTable` gained a `readonly` prop - replay stays a logged-in dashboard action.

**Global alert mute (muted is muted)**

- State lives in ONE place: a service-managed boolean control `alerts:muted` (user-scoped, source_managed, provisioned lazily on first toggle). The same control templates read - `[[[if:c:alerts:muted]]]ALERTS ARE MUTED[[[endif]]]` shows/hides live in overlays with zero engine changes, and `[[[c:alerts:muted_at]]]` ("muted since") comes free from the client-side `_at` companion. Conceptual sibling of the `tts` gate control.
- `AlertMuteService`: `isMuted()` (one indexed exists() query, absent control = not muted) + `setMuted()` (provision, flip `'0'`/`'1'`, broadcast `ControlValueUpdated` with empty overlay_slug = all overlays).
- Guarded at all three alert build-sites BEFORE any output: `TwitchEventSubController::renderEventAlert` (covers live webhooks, replay, test cheer), `ExternalAlertService::dispatch`, `ExternalEventController::replay`. Muting stops the visual broadcast, the alert sound, the ElevenLabs TTS synthesis (no credits burned), and the bot chat message. Events keep recording and controls keep updating - only alert output stops.
- No replay bypass: replaying while muted returns a "Alerts are muted" warning instead; test cheer reports `alerts_muted` in its response.
- Mute/unmute button + amber muted banner on both `/dashboard/events` (session, `POST /dashboard/events/mute`) and the token feed.
- `alerts:muted` is in the Add-Control preset picker (new "Overlabels - Alerts" group, no integration required) so the overlay banner pattern is one click to add. `alerts` reserved as a control key; drive-by: `fourthwall`, `bmac`, `throne` added to `RESERVED_KEYS` too (they were missing, same collision class).

## July 2nd, 2026 - docs(controls): document preset controls on the Controls help page

The Controls help page (`/help/controls`) thoroughly explained user-created controls but never mentioned preset controls - the service-managed values Overlabels feeds in from Twitch, the donation integrations, and the GPS app. New readers had no bridge from that page to the concept or to the exhaustive `/help/integration-presets` reference.

- New **"Preset Controls (from integrations)"** section (plus a Table of Contents entry) covering how they differ from hand-made controls: auto-managed read-only value, the namespaced `[[[c:source:key]]]` tag, user-scoped so one add is shared across every overlay, and only visible once the integration is connected (Twitch excepted).
- Documents the shared six-key donation family (`donations_received`, `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`) that lets cross-service expressions total donations, plus the per-service extras (Throne item/thumbnail/surprise flag, BMAC support type).
- A compact icon grid of all eight integrations with **live preset counts imported from `controlPresets.ts`** (`TWITCH_PRESETS.length`, etc.), so the doc can't drift as presets change, and two links out to the full Integration presets reference. ESLint clean.

## July 1st, 2026 - fix(throne): register Throne in the alert trigger catalogue

Throne shipped (#142) with a working webhook but no alert trigger - the Triggers tab showed no Throne row to attach an alert template to. Root cause: the TriggerManager UI lists external triggers from `ExternalEventTemplateMapping::SERVICE_EVENT_TYPES`, a hand-maintained catalogue separate from the driver, and the Throne entry was never added. The webhook, controls, recents, and replay all worked because those flow off the normalized event; only the trigger picker reads this constant.

- Added `throne => ['donation' => 'Throne Gift or Contribution']` to `SERVICE_EVENT_TYPES` so the trigger appears (no frontend change - TriggerManager renders connected services dynamically).
- Added `throne => ['donation']` to `AMOUNT_EVENT_TYPES` so Throne gifts get the same at-least / exactly variant conditions as every other donation service (a bigger gift can fire a louder alert).
- **New guard test** (`ExternalTriggerCatalogueTest`) asserts every registered driver's `getSupportedEventTypes()` is present in `SERVICE_EVENT_TYPES`, so this drift is a red build for the next integration instead of a UI hunt. Would have caught this on the original PR.

## July 1st, 2026 - polish(throne): clearer "paste into Throne" manual step

Tightened the connected-state copy on the Throne settings page so the one manual step (pasting the webhook URL into Throne) is unmissable. Replaced scattered inline "go there" links with a single prominent "Open Throne webhook settings ->" button directly below the webhook URL input, plus a helper line that reacts to the Copy button (after copying it turns violet and reads "Copied. Now open Throne and paste it into the Webhook URL field.").

## July 1st, 2026 - docs(throne): "All Throne Events" reference page

Added `resources/help/reference/eventsub-tags/all-throne-events.md`, the Throne counterpart to "All Ko-fi Events". The filesystem-driven help reference picks it up automatically (the gitignored search index rebuilds on deploy via the composer hook). Documents every normalized tag across the three Throne event types, including the Throne-unique `event.item_name`, `event.item_thumbnail_url`, and `event.is_surprise_gift`, and notes that `from_name`/`message` are empty on crowdfunded gifts.

## July 1st, 2026 - docs(throne): homepage + integration-presets help page

Surfacing Throne everywhere the other integrations already appear, now that the integration is functionally complete.

- **Homepage (`SectionIntegrations`)** - Throne is the sixth tab in the donations section with its own card and namespace example. The `NEW:` badge moved off Fourthwall and Buy Me a Coffee (no longer new) and onto Throne. The cross-service `latest()` showcase - the feature no donation-platform-owned overlay tool can match - now threads all six donation services plus Twitch bits: "Five donation services" became "Six", and both `latest()` examples gained a `c.throne.*` pipe.
- **Help (`IntegrationPresets`)** - a Throne section documenting all nine controls, including the three Throne-unique ones (item name, product thumbnail, surprise-gift flag), with a note that the thumbnail drops straight into an `<img>`. `serviceLabel('throne')` added to the frontend label source of truth.
- ESLint + vite build clean.

## July 1st, 2026 - feat(throne): control presets in the Add-Control picker

The last functional gap: surface Throne's controls in the "Add control" modal so a streamer can build a Throne overlay without hand-typing tags. Mirrors the existing Ko-fi / Fourthwall / BMAC preset pattern.

- **`THRONE_PRESETS`** in `controlPresets.ts` - the six shared donation-family controls plus the three Throne-unique ones (`latest_item_name`, `latest_item_thumbnail_url`, `latest_is_surprise_gift`), exactly matching the driver's auto-provision definitions. Registered in `getPresetsForSource('throne')`.
- **`ControlFormModal`** - a `Throne` preset group that appears on static templates once the user has connected Throne, with the same already-added filtering and fuzzy search as every other service. No backend threading needed: `connectedServices` is plucked from the user's enabled integrations, so connecting Throne lights up the group automatically.
- Adding a preset still creates one user-scoped, service-managed control shared across all overlays. ESLint + vite build clean.

## July 1st, 2026 - feat(throne): connect / settings flow

The settings page that turns the Throne driver into something a streamer can actually use. Throne is the simplest connect flow of any integration: it signs every webhook with its own global key, so there's no token to paste and no OAuth dance - connecting is one click, then you copy the webhook URL into Throne.

- **`ThroneIntegrationController`** + routes under `settings/integrations/throne` (`show`, `connect`, `test-mode`, `seed-count`, `disconnect`), mirroring the Ko-fi donation-service shape. `connect` is credential-less: it `firstOrCreate`s the integration (the model generates the routing `webhook_token`) and surfaces the URL. Idempotent - reconnecting never duplicates the row or rotates the token.
- **Settings page** (`settings/integrations/throne.vue`) - one-click Connect, copyable webhook URL, a "what to do next" checklist (paste into Throne, map an alert, add controls), test mode (disables dedup so Throne's "Test webhook" button can be fired repeatedly), a one-time starting gift count seed, and a disconnect danger zone. No verification-token field and no event-type picker, since all three Throne types normalize to `donation`. The integrations index already listed Throne via the registry, so it now links straight through.
- **Tests:** 8 new (`ThroneIntegrationSettingsTest`) covering the disconnected render, credential-less connect, connect idempotency, the webhook URL surfacing, test-mode persistence + the not-connected 404, seed-count, and disconnect. Pint + ESLint + vite build clean.

## July 1st, 2026 - feat(throne): webhook driver + Ed25519 verification (backend slice)

Throne was previously written off as un-integrable (no public API, only an unofficial Docker image). It now ships a real signed webhook, which makes it a Ko-fi-class integration with no listener process. This first slice is the backend core: the driver, signature verification, and tests. The connect/settings flow, control presets UI, and help page are follow-ups.

- **`ThroneServiceDriver`** - maps all three Throne event types (`gift_purchased`, `contribution_purchased`, `gift_crowdfunded`) to the normalized `donation` type so `[[[if:event.type = donation]]]` alert templates stay uniform across every donation service. No controller change needed: Throne posts `application/json`, which already flows through the existing JSON path in `ExternalWebhookController::parsePayload()`.
- **Ed25519 verification** - Throne signs each delivery with a detached signature in the `X-Signature-Ed25519` header over the message `{X-Signature-Timestamp}.{rawBody}`, verified against Throne's single global public key. `verifyRequest()` guards the timestamp (numeric) and signature (128 hex / 64 bytes) per Throne's spec, then verifies via libsodium against the **raw** request body (`$request->getContent()`) - never a re-encoded parse, which would reorder keys and fail. The global PEM is pinned in `config('services.throne.public_key')` with a `THRONE_PUBLIC_KEY` env override so a key rotation is a config change, not a deploy.
- **Amount handling** - Throne sends integer minor units (`price: 10000` = 100.00); `contribution_purchased` uses an `amount` field where gifts use `price`. The driver divides by 100 and stays currency-naive (no FX), consistent with the rest of the donation stack.
- **Throne-unique controls** - beyond the six shared donation-family controls, Throne gifts are real products, so the driver provisions three extras: `latest_item_name`, `latest_item_thumbnail_url`, and `latest_is_surprise_gift`. `gift_crowdfunded` carries no gifter or message, so it bumps the counters without blanking the latest donor/message.
- **Tests:** 12 new (in `ExternalWebhookTest`) including a regression test that verifies a **real captured Throne delivery against the pinned production key** through the full controller pipeline, plus wrong-key / tampered-body / bad-timestamp / malformed-signature rejections, dedup on `event_id`, the crowdfunded donor-preservation edge, and the contribution `amount`-vs-`price` field. Full external-webhook suite **25 passed**; Pint clean.
