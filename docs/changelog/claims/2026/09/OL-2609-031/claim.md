## OL-2609-031 - docs(help): a guide for the living Twitch title

**Shipped:** 2026-09-08
**Commit:** `git log --grep=OL-2609-031`

### Surface
- `resources/help/pages/living-title.md` - new guide, section `Live data`, context `settings.title.show`
- `resources/help/pages/index.md` - linked under `### Live data`

### Claims
- **C1** [code] `living-title.md` declares `section: Live data`, `context: settings.title.show`, a `heading` of at most 40 characters and a `lead` of at most 320.
- **C2** [code] Every `[[[...]]]` tag the page's examples use is either a `TemplateDataMapperService::TAG_CATALOG` key, a `c:<key>` the reader is told to create, or a `c:<source>:<key>` whose key a driver or `StreamSessionService` provisions under that source.
- **C3** [code] Every pipe the examples use (`currency`, `number`, `distance`, `uppercase`) is a formatter named in `resources/dsl/dsl.json`.
- **C4** [code] The page's `!ol title` table lists exactly the four verbs `BotChatAdminService::dispatch()` matches on subject `title`.
- **C5** [test] `php artisan test --filter=Help` passes with the page in place: it is linked from index.md under its section, its context names a live route, and `settings.title.show` resolves to at most 3 pages.

### Unchanged
- No code. `HelpCorpus`, `HelpPage` and the search index build are not in the diff; the page is discovered by directory.
