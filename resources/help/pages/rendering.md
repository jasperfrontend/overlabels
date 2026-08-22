---
title: How an overlay renders - the pipeline end to end
description: "What happens between an OBS browser source opening your overlay URL and a follower alert animating on screen: boot, tag replacement, conditionals, the WebSocket channel, alert render flow, and why scripts are stripped."
heading: How an overlay renders
lead: From the browser source opening a URL to a follower alert animating on screen - the whole path, in order. Worth reading once, and worth having when something is not appearing and you need to know which step to suspect.
canonical: https://overlabels.com/help/rendering
---

Overlabels is not magic, and knowing the order things happen in turns "my overlay is blank" from a mystery
into a question with about four possible answers.

## Boot: a static overlay

```
  OBS browser source
        |
        v
  /overlay/{slug}#token          1. page loads, token read from the fragment
        |
        v
  Echo / Reverb connection       2. WebSocket opens
        |
        v
  GET /api/overlay/render        3. template + live Twitch data
        |
        v
  tags -> conditionals -> CSS    4. resolve, evaluate, inject
        |
        v
  mounted, subscribed            5. listening on alerts.{twitch_id}
```

In detail:

1. **The page loads and reads the token.** The fragment is pulled off `window.location.hash` and checked
   for a length of 64. If it is missing or the wrong length, you get the "your overlay link is broken"
   screen instead of a silent blank page - that message exists because OBS cannot show you a console.
2. **A WebSocket connection opens** to Laravel Reverb, authenticated against your token. This happens
   early and independently, so a render failure and a connection failure are separately diagnosable.
3. **The renderer fetches your template and your live data** in one call, using the token.
4. **Tag replacement runs.** Every `[[[tag]]]` is resolved in a single pass - Twitch values, control
   values, formatting pipes and all. This happens exactly once per render.
5. **Expression controls are registered** and evaluated client-side, so derived values cost no round trip.
6. **Conditional blocks are evaluated** and branches that did not match are removed from the output
   entirely, rather than hidden with CSS.
7. **Your CSS is injected** into the document head as a single `<style id="overlay-style">` element.
8. **The rendered HTML is mounted**, and the overlay subscribes to `alerts.{your_twitch_id}` for
   everything that happens next.

> [!IMPORTANT]
> Tags are parsed **once**, and resolved values are never re-scanned for tags. This is deliberate: if a
> control value containing `[[[something]]]` were reparsed, anyone who could set a control could inject
> into your overlay. A value that looks like a tag renders as text. The full reasoning lives in
> [Why tags are parsed exactly once](/help/tags-parse-once).

## Staying live

After the first render, three things update the overlay in place. None of them reload the page.

| Mechanism           | What arrives                          | What happens                                                        |
|---------------------|----------------------------------------|---------------------------------------------------------------------|
| **Control updates** | `control.updated`                      | The value updates in reactive state; expressions that read it re-evaluate |
| **Twitch events**   | EventSub webhook, relayed              | Aggregate tags like `followers_total` move                          |
| **Alerts**          | A complete pre-rendered alert payload  | Rendered over the static overlay, then auto-dismissed               |

Only the affected nodes change. The rest of the overlay is untouched, which is why a counter ticking over
does not restart a CSS animation running elsewhere on the page.

## Alert render flow

```
  Twitch EventSub webhook  /  external service webhook
        |
        v  verified (HMAC-SHA256 for Twitch, per-service for the rest)
  stored + deduplicated
        |
        v  which template is mapped to this event type, for this user?
  merged with current overlay data and rendered server-side
        |
        v  broadcast on alerts.{twitch_id}
  overlay checks targeting rules -> renders -> transition -> auto-dismiss
```

The alert arrives at your overlay as **finished HTML**. Your browser source is not fetching anything or
deciding anything; it is being handed a rendered payload and asked to display it for `duration_ms`.

Two consequences worth knowing:

- **An alert needs a static overlay to render into.** There is no DOM otherwise. If your alerts are not
  appearing, confirm a static overlay is actually running in that browser source. See
  [Overlays vs Alerts](/help/overlays-vs-alerts).
- **Targeting is checked at the overlay, not at the server.** An alert with no targeting configured fires
  everywhere; one with targeting is ignored by overlays not on its list.

## Overlay health

An OBS browser source is a hostile place to run a web page. It gets suspended, the machine sleeps, the
network drops mid-stream, and nobody is watching a console. So the overlay defends itself:

- **Reconnects with exponential backoff** when the WebSocket drops
- **Periodic health checks** to notice a connection that is technically open but actually dead
- **Visible error banners**, because a console error in OBS reaches nobody
- **Auto-reload as a last resort** when recovery fails

The banner styles are defined in the page itself rather than in the Vue app, specifically so an error can
be displayed even when the app failed to mount.

## Why scripts are stripped

`<script>`, `<iframe>`, `<embed>` and friends are removed from all template content - HTML, CSS, head and
meta fields alike - before it is stored. This is enforced on the server, not just flagged in the editor.

**What still works:** external stylesheets, web fonts, icon libraries, CDN-hosted CSS, and every animation
CSS can express. That covers the overwhelming majority of overlay design.

**What does not:** inline scripts, third-party embeds, and anything that needs to execute JavaScript
inside the overlay.

Overlays are shared and copied between users by design. A template that could run arbitrary JavaScript
would be running it with your overlay token in scope, on a machine that is live to an audience. Stripping
scripts is what makes "copy this stranger's overlay" a safe thing to do.

If you need real JavaScript - a wheel, a canvas, a custom visualisation - the supported path is to host
your own page and add it to OBS as its own browser source, reading your data over the API.
[Lists in realtime](/help/lists-realtime) walks through exactly that.

## Related

- [Overlays vs Alerts](/help/overlays-vs-alerts) - why alerts belong inside a static overlay
- [Overlay Access Tokens](/help/tokens) - the credential used in step 1
- [Testing your alerts](/help/testing) - firing real events at this pipeline on demand
