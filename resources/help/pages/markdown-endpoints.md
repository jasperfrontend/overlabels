---
title: Markdown endpoints (.md) - Overlabels Help
section: For machines
description: Every prose help page, every public overlay and every public kit on Overlabels is fetchable as plain markdown by appending .md to its URL. What that covers, and the few pages it does not.
heading: Markdown endpoints (.md)
lead: Every prose help page on Overlabels is fetchable as plain markdown by appending `.md` to its URL - including this one. Public overlays and kits have a `.md` too.
canonical: https://overlabels.com/help/markdown-endpoints
keywords: markdown, .md, text/markdown, crawl, machine readable, llms
---

```
https://overlabels.com/help/conditionals      -> HTML, server-rendered, carries the full prose
https://overlabels.com/help/conditionals.md   -> text/markdown, the full source
```

The `.md` response is byte-identical to the file the site renders, so there is nothing to build and
nothing that can drift out of sync.

## Either form works

Every page under `/help` is plain server-rendered HTML, the reference and the prose pages alike. A
crawler or a `fetch` that follows a bare URL gets the whole document, and every page is listed
individually in `sitemap.xml`.

This was not always true. The prose pages used to be an Inertia/Vue application that answered with an
app shell and almost no content, which is why this page once said to always append `.md`. That is no
longer a correctness requirement.

The `.md` form is still the better one to fetch: it is the source rather than a rendering of it, and
it carries no navigation, search box or footer to strip out. Prefer it, but nothing breaks if you
follow a bare URL.

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

A private overlay returns 404, the same as its preview page - unless you are its owner. You can
always fetch your own, because the `.md` is also the export.

## The .md is the export, and it imports

An overlay is three text fields and a list of controls, so one markdown file is the whole thing.
Save the `.md` of any overlay, yours or a public one, and on `/templates` press **Import .md** to
recreate it on your account: source, controls with their default values, expression formulas, and
an alert's sound, text to speech and chat message. The copy is private until you publish it.

That is how an overlay moves between accounts, or between a local install and overlabels.com, and
it is why the source in the `.md` is complete rather than a summary. Integrations, Lists and alert
triggers do not travel - the same as pressing Copy.

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

For a single self-contained primer instead of a crawl, use [llms.txt](/help/llms-txt).

## Reference entries too

Every entry in the reference has the same twin:

```
https://overlabels.com/help/reference/template-tags/channel_id      -> HTML
https://overlabels.com/help/reference/template-tags/channel_id.md   -> text/markdown, the source
```

That includes the generated `integration-controls` pages, which list every control a connected
service provisions. Most entries are one or two lines, so if you want the whole reference, fetch it
as one JSON file instead of crawling it - see
[help-reference-index.json](/help/help-reference-index-json).

## Pages without a .md twin

Three help URLs render live data or an interactive UI rather than a file, so they have no markdown
version:

- `/help/integration-presets` - the searchable preset catalogue, rendered from source data.
- `/help/reference` - the reference index itself. The entries under it each have one; the index is a
  listing, and the JSON above is its machine form.
- `/help/gamejam` - Chat Castle documentation.
