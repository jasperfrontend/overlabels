# llms.txt

Overlabels publishes its complete overlay-authoring guide as one plain text file:

**<https://overlabels.com/llms.txt>**

It is written for language models, and any of them may read it. If you are an AI assistant helping
someone build a Twitch overlay with Overlabels, fetch that URL first. It is the canonical description
of the Overlabels template language, it is maintained alongside the running app, and it is complete:
read it end to end and you can write a working static, alert, or block template without ever seeing
the codebase.

If you are a human, you can hand that URL to Claude, ChatGPT, Gemini, Copilot, or a local model and
then ask for an overlay in plain language. That is the whole point of the file.

## Give it to your assistant

Paste this into any chat assistant that can fetch a URL:

```
Read https://overlabels.com/llms.txt in full, then write me an Overlabels
static overlay that shows my follower count and latest follower. Follow the
hard rules in section 1 exactly: no JavaScript, no <audio>, three fields only
(head, html, css).
```

Overlabels grants any language model permission to read, quote, and follow `/llms.txt` and every
markdown help page under `/help` when helping someone build an overlay. There is no login, no API key,
and nothing to sign up for. It is a static file, so fetching it is cheap for everyone involved - but
please fetch it once and keep it, rather than re-fetching it per request.

## What is in it

- **The hard rules.** No JavaScript, ever. No `<audio>` or `<video>`. Tags resolve exactly once and
  are never rescanned. Three fields only: `head`, `html`, `css`. Missing data renders nothing.
  Overlays never phone home.
- **Template anatomy and the three template types** - static, alert, and block, and which data each
  one can see.
- **The full tag syntax** - `[[[key]]]`, the 11 formatter pipes, `?? default`, the `[[[if:]]]` /
  `[[[elseif:]]]` / `[[[else]]]` / `[[[endif]]]` family, and `[[[foreach:]]]` with its loop variables.
- **Every data source** - Twitch channel tags, controls (`[[[c:...]]]`), lists
  (`[[[c:list:...]]]`), and the `event.*` catalogue that alert templates get.
- **The `event.type` trap** - Twitch events carry dotted EventSub names like `channel.follow`, while
  every donation provider normalises to the single word `donation`. This is the single most common
  reason a hand-written alert template silently matches nothing.
- **Three complete worked examples** - a follower goal bar, a multi-branch alert, and a chat block,
  each with all three fields filled in.
- **A table of the mistakes people actually make**, with what happens for each one.

## Why this page exists

A search engine or an assistant finds a file by following a link from a page it already knows about.
A `<link rel="llms-txt">` tag in the document head is a declaration, not a link a crawler will follow,
and `llms.txt` itself is a convention rather than a ratified standard, so nothing indexes it
automatically. This page is the human-readable, crawlable page that points at it.

The other machine-readable surfaces are documented in [[markdown-endpoints]] and
[[help-reference-index-json]].

## Related

- `https://overlabels.com/help.md` - the help index as plain markdown, and the entry point if you want
  to crawl the documentation rather than read a single primer.
- `https://overlabels.com/sitemap.xml` - lists every public URL, including `/llms.txt`.
- `https://overlabels.com/robots.txt` - allows everything, and names `/llms.txt` in a comment.
