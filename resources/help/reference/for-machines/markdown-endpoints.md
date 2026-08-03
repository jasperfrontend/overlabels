# Markdown endpoints (.md)

Every prose help page on Overlabels is fetchable as plain markdown by appending `.md` to its URL.

```
https://overlabels.com/help/conditionals      -> HTML (a Vue app shell)
https://overlabels.com/help/conditionals.md   -> text/markdown, the full source
```

The `.md` response is byte-identical to the file the site renders, so there is nothing to build and
nothing that can drift out of sync.

## Use the .md form

The bare help URLs render an Inertia/Vue application. A crawler or a `fetch` that follows one gets an
app shell and almost no prose. Always append `.md`.

The exceptions are the pages under `/help/reference` - the page you are reading now. Those are plain
server-rendered HTML on purpose, so they can be read directly with no suffix, and they are the pages
listed individually in `sitemap.xml`.

## Start at the index

```
https://overlabels.com/help.md
```

That is the help index as markdown, and it links to every other page. One URL is enough to crawl the
whole documentation set.

For a single self-contained primer instead of a crawl, use [[llms-txt]].

## Pages without a .md twin

Three help URLs render live data or an interactive UI rather than prose, so they have no markdown
version:

- `/help/integration-presets` - the searchable preset catalogue, rendered from source data.
- `/help/reference` - this reference. Machine-readable as JSON instead, see
  [[help-reference-index-json]].
- `/help/gamejam` - Chat Castle documentation.
