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

## Public overlays have a .md too

The convention is not limited to prose. Any public overlay is fetchable as one self-contained
markdown document:

```
https://overlabels.com/overlay/<slug>/public      -> the preview page (HTML)
https://overlabels.com/overlay/<slug>/public.md   -> text/markdown, the whole overlay
```

Unlike the help pages, this one is generated rather than a file on disk, because an overlay is not a
document - it is a template plus its controls, the integrations it reads from, and its alert wiring.
The `.md` carries all of that, which is what makes a single URL enough to hand an overlay to a
language model.

Two things are deliberately absent: the values of controls managed by a connected service (they hold
live account data - a real donor's name, actual revenue), and anything belonging to the owner's
account beyond the overlay itself. Values for controls the overlay defines are included, because
those are the author's design defaults and are exactly what copying the overlay gives you.

A private overlay returns 404, the same as its preview page.

## Public kits too

```
https://overlabels.com/kits/<id>.md
```

The kit, then every overlay in it described exactly as its own `.md` describes it, plus one
aggregated list of the integrations the kit needs as a whole.

This is the one place where the `.md` is reachable and the HTML page is not: every other kit route
requires a login, and this one deliberately does not, because a URL handed to a language model
cannot sit behind a session. It opens nothing new. A kit and its overlays carry separate visibility
flags, and a private overlay inside a public kit is listed but has its source withheld - so every
line of source in a kit document is already readable at that overlay's own public `.md`.

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
