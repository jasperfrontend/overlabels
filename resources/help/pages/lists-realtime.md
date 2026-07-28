---
title: Lists in realtime
description: "Build a live data page off an Overlabels List: get a token, read the list as JSON, render it, subscribe to live updates over WebSocket, and add it to OBS. A step-by-step guide."
heading: Lists in realtime
lead: Build a page that reads one of your Lists and updates live - a wheel, a leaderboard, a ticker - and drop it into OBS. Step by step.
canonical: https://overlabels.com/help/lists-realtime
context: lists.index, tokens.index
---

> [!NOTE]
> This is the **builder's guide**. If you just want to create a List and use it from the dashboard or
> chat, start at [the Lists page](/help/lists). This guide picks up where that leaves off: you have a
> List, and you want to render its data, live, in your own page.

## The mental model (read this first)

The single most important thing to understand: **Overlabels overlays run no JavaScript.** When you save
an overlay template, we strip `<script>`, event handlers, and iframes. So you *cannot* build a wheel
inside an overlay template - there's no way to run the code that would draw it.

Instead, your live data page lives **outside** Overlabels: a normal HTML + JS page you host anywhere,
added to OBS as **its own** Browser Source. It talks to your List over two rails:

```
  your page (hosted anywhere, JS allowed)
        |
        |  1. READ once     GET /api/lists/<slug>?token=...     -> current items + how to subscribe
        |  2. SUBSCRIBE      WebSocket: lists.<twitch>.<slug>     -> pushed on every change
        v
   Overlabels  ---- broadcasts on every append / draw / edit / age-out --->
```

One token unlocks both rails. You read the current state once to draw the first frame, then subscribe so
the page redraws itself whenever the List changes - no polling, no refresh. The rest of this guide is
just wiring those two calls.

## Step 1 - Get a token

You authenticate with an **Overlay Access Token** - the same 64-character token your overlay URLs use.
Generate one from your dashboard's [Overlay Access Tokens](/tokens) page and copy it.

> [!WARNING]
> **Treat the token like sharing your overlay URL.** The token identifies *you*, so it can only ever read
> *your* Lists - never anyone else's. But anyone who has it can read your Lists, and if you embed it in a
> public page's source, it's visible there. That's the same trust model as handing someone your overlay
> link. Don't paste it anywhere you wouldn't paste that.

## Step 2 - Read the list

One GET request returns the List as JSON. Try it in a terminal first to see the shape:

```bash
curl "https://overlabels.com/api/lists/wheel?token=YOUR_TOKEN"
```

Response (for a List with slug `wheel`):

```json
{
  "slug": "wheel",
  "label": null,
  "count": 2,
  "items": [
    { "id": 1, "value": "Pizza", "added_at": 1730000000, "label": null, "weight": 1, "color": null },
    { "id": 2, "value": "Tacos", "added_at": 1730000060, "label": null, "weight": 1, "color": null }
  ],
  "disabled_at": null, "expires_at": null, "entry_ttl_seconds": null,
  "updated_at": 1730000060, "ts": 1730000061,
  "realtime": {
    "channel": "lists.<your twitch id>.wheel",
    "event": "list.updated",
    "auth_endpoint": "https://overlabels.com/api/overlay/broadcasting/auth",
    "key": "...", "host": "...", "port": 443, "scheme": "https"
  }
}
```

Two halves matter:

- `items` - your data, as full objects. `id` is a stable, never-reused key (two items with the same value
  still differ); `value` is the content; `added_at` is Unix seconds.
- `realtime` - everything you need for Step 4. You don't hardcode any of it; you read it from here.

## Step 3 - Render it

A minimal page that fetches once and draws the items. Key your elements by `item.id` - it's the whole
point of the objects, and it makes the live step painless.

```html
<div id="wheel"></div>
<script>
  const TOKEN = 'YOUR_TOKEN';
  const SLUG = 'wheel';
  const BASE = 'https://overlabels.com';

  function render(items) {
    document.getElementById('wheel').replaceChildren(...items.map(it => {
      const el = document.createElement('div');
      el.dataset.id = it.id;        // stable key
      el.textContent = it.value;
      return el;
    }));
  }

  fetch(`${BASE}/api/lists/${SLUG}?token=${TOKEN}`)
    .then(r => r.json())
    .then(data => render(data.items));
</script>
```

That's a working (static) page already. Open it in a browser and you'll see your items. Now make it live.

## Step 4 - Go live

Subscribe to the List's channel and re-render on every push. We use
[pusher-js](https://github.com/pusher/pusher-js) (Reverb speaks the Pusher protocol). Load it from a CDN
- your page can, it's not an overlay:

```html
<script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js"></script>
```

Then, using the `realtime` block from Step 2:

```js
const rt = data.realtime;

const pusher = new Pusher(rt.key, {
  wsHost: rt.host,
  wsPort: rt.port,
  wssPort: rt.port,
  forceTLS: rt.scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  cluster: 'mt1', // unused by self-hosted Reverb, but pusher-js wants a value
  // Authorize the private channel with your token (this is what proves
  // you're allowed to read this list's stream):
  authorizer: (channel) => ({
    authorize: (socketId, cb) => {
      fetch(rt.auth_endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          socket_id: socketId,
          channel_name: channel.name,
          token: TOKEN,
          slug: SLUG,
        }),
      }).then(r => r.json()).then(d => cb(null, d)).catch(e => cb(e, null));
    },
  }),
});

const channel = pusher.subscribe('private-' + rt.channel);
channel.bind(rt.event, (payload) => render(payload.items)); // payload.items = the new objects
```

That's it. A chatter runs `!raffle`, the List grows, the broadcast fires, your `render()` runs with the
new items - instantly, no polling. Because you key by `id`, the survivor of a draw keeps its element and
you can animate the difference.

## Step 5 - Put it in OBS

1. **Host the page over https.** GitHub Pages, tiiny.host, Netlify, your own server - anywhere that
   serves https. (Browser sources and the API both require https; a local `file://` page often can't
   fetch it.)
2. **Add a Browser Source** in OBS pointing at your page's url. Set the width/height to whatever your
   wheel needs.
3. **Keep the token out of the public url if you can** - prefer hardcoding it in the page you control
   over passing it in a shareable link. Either way, it's a read-only key for your own lists.

This source is separate from your Overlabels overlay source. You can run both - your themed overlay from
Overlabels, and your custom wheel from your own page - side by side in the same scene.

## The complete page

A full, copy-paste-ready bootstrap-then-subscribe page lives in the repo at
`docs/examples/list-data-consumer.html`. It reads slug + token from the page url so you can reuse one
file for any List:

```
your-page.html?base=https://overlabels.com&slug=wheel&token=YOUR_TOKEN
```

Start from that, swap the `render()` for your own drawing (an SVG wheel, a canvas, an animated list), and
you have a live, custom overlay driven by chat.

## Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| Fetch fails / blocked by mixed content | Your page is served over `http` (or `file://`). Host it over **https**. The API and the WebSocket both require it. |
| `401` from the read endpoint | Token missing, not 64 chars, or revoked. Regenerate it on the [Overlay Access Tokens](/tokens) page and update your page. |
| `404` from the read endpoint | No List with that slug on your account. Check the slug exactly (lowercase, underscores) on your [Lists page](/dashboard/lists). |
| Loads once, never updates | The subscribe step isn't connecting. Check the browser console: is `pusher.subscribe` firing `pusher:subscription_succeeded`? Confirm you used `key/host/port` from the `realtime` block, and prefixed the channel with `private-`. |
| `403` "Channel not permitted" | The token's account doesn't match the channel's twitch id, or the slug is malformed. Use the exact `realtime.channel` string from the read response, and the same token. |
| Works in a browser, not in OBS | OBS browser sources are stricter: the page must be https, and some setups cache aggressively - right-click the source and Refresh after changes. |

## Limits and honest caveats

### `label`, `weight`, and `color` are reserved

They're in the data shape, but there's no way to *set* them yet - every item is `label: null`,
`weight: 1`, `color: null`. Build against `id`, `value`, and `added_at` today. When per-item editing
ships, your page reads the new fields with no other change.

### Very large lists and the payload cap

A live update carries the whole list's current state. For normal lists that's tiny, but a list with
hundreds of long entries can exceed the broadcast size cap, in which case an update may not arrive. If
you're pushing a List that big, fall back to re-fetching the read endpoint on a timer, or split the data.

### No live updates needed? Just poll

Step 4 is optional. If a few seconds of latency is fine, skip the WebSocket entirely and call the read
endpoint on an interval. The static page from Step 3 plus a `setInterval` is a complete, simpler
solution.

## Quick reference

```
Read (one-shot state + how to subscribe)
  GET https://overlabels.com/api/lists/<slug>?token=<overlay token>

  -> { slug, label, count, items: [ {id,value,added_at,label,weight,color} ],
       disabled_at, expires_at, entry_ttl_seconds, updated_at, ts,
       realtime: { channel, event, auth_endpoint, key, host, port, scheme } }

Subscribe (live)
  pusher-js -> key/host/port from realtime{}
  authorizer POSTs { socket_id, channel_name, token, slug } to realtime.auth_endpoint
  channel = 'private-' + realtime.channel        (e.g. private-lists.<twitch>.<slug>)
  event   = realtime.event                        ('list.updated')
  payload.items = the new array of item objects

Reference consumer
  docs/examples/list-data-consumer.html
```

New to Lists themselves? Start with [the Lists guide](/help/lists). For numeric computation on List data,
see [Expression Controls](/help/expressions).
