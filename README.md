# Overlabels

Build custom Twitch overlays and alerts in HTML and CSS, powered by live data tags, real-time controls,
and external integrations.

There is no drag-and-drop editor. There is no proprietary file format. There is no runtime you do not control.
Your overlay is a webpage, and Overlabels is the engine that keeps it alive.

**[overlabels.com](https://overlabels.com)** - free forever, no paywalls, no tiers.

---

## The syntax

Overlabels uses one deliberate, collision-resistant syntax: triple square brackets. It never clashes with
HTML, CSS, JavaScript, or any template engine you might meet in the wild.

```html
<span class="followers">[[[followers_total]]]</span>
```

Drop a tag anywhere in your HTML or CSS and Overlabels replaces it with live data when the overlay renders.
The whole system - Twitch data, live controls, conditionals, formatting, math - runs through that one form.

---

## Documentation

Full documentation lives at **[overlabels.com/help](https://overlabels.com/help)**. It is the canonical
source and it stays in sync with what is actually deployed.

**Start here**

| Page                                                              | What it covers                                      |
|-------------------------------------------------------------------|-----------------------------------------------------|
| [Why Overlabels](https://overlabels.com/help/why-overlabels)       | The pitch, for people who write code                |
| [For Creators](https://overlabels.com/help/for-creators)           | What it actually is beneath the HTML/CSS surface    |
| [For Designers](https://overlabels.com/help/for-designers)         | Handoff guide: what to deliver, what to avoid       |
| [Manifesto](https://overlabels.com/help/manifesto)                 | Why it exists and the principles behind it          |

**Building overlays**

| Page                                                                    | What it covers                                    |
|-------------------------------------------------------------------------|---------------------------------------------------|
| [Overlays vs Alerts](https://overlabels.com/help/overlays-vs-alerts)     | The two surfaces and how they fit together        |
| [The Builder](https://overlabels.com/help/builder)                       | Compose an overlay on a grid, no code required    |
| [Blocks](https://overlabels.com/help/blocks)                             | Reusable pieces: authoring, CSS scoping, controls |

**The template language**

| Page                                                            | What it covers                                          |
|-----------------------------------------------------------------|---------------------------------------------------------|
| [Conditional and event tags](https://overlabels.com/help/conditionals) | if/elseif/else, comparisons, event payload tags   |
| [Formatting pipes](https://overlabels.com/help/formatting)       | Numbers, durations, currencies, dates. Locale-aware     |
| [Math engine](https://overlabels.com/help/math)                  | Waves, modulo wheels, timestamp racing                  |

**Live data**

| Page                                                                        | What it covers                                       |
|-----------------------------------------------------------------------------|------------------------------------------------------|
| [Controls](https://overlabels.com/help/controls)                             | Text, numbers, counters, timers, toggles             |
| [Expression controls](https://overlabels.com/help/expressions)               | Client-side formulas over any other control          |
| [Integration presets](https://overlabels.com/help/integration-presets)       | Every auto-managed control, searchable               |
| [Lists](https://overlabels.com/help/lists)                                   | Raffles, queues, quote walls, leaderboards           |
| [Lists in realtime](https://overlabels.com/help/lists-realtime)              | Read a list as JSON and subscribe over WebSocket     |

**Reference**

| Page                                                        | What it covers                                                |
|-------------------------------------------------------------|---------------------------------------------------------------|
| [Reference](https://overlabels.com/help/reference)           | Every template tag, EventSub event and foreach field          |
| [Twitch chat bot](https://overlabels.com/help/bot)           | Letting viewers and mods change controls from chat            |
| [Free resources](https://overlabels.com/help/resources)      | Colors, fonts, animations and other tools                     |

> [!TIP]
> **Reading this as a machine?** Every help page is also plain markdown: append `.md` to any URL
> (`/help/conditionals.md`). For a single self-contained primer, start at
> [overlabels.com/llms.txt](https://overlabels.com/llms.txt).

---

## Integrations

Twitch EventSub drives followers, subs, gift subs, resubs, cheers, raids, channel point redemptions and
stream online/offline. Five donation services sit alongside it - Ko-fi, StreamLabs, Fourthwall,
Buy Me a Coffee and Throne - and every one of them exposes the same six controls, so a template written
against one ports to another by swapping the namespace.

```html
<span>[[[c:kofi:latest_donor_name]]]</span>
<span>[[[c:throne:latest_donor_name]]]</span>
```

Connect a service and its controls appear automatically. See
[integration presets](https://overlabels.com/help/integration-presets) for the full list.

---

## Limits

- Up to 1000 overlays per account, 50 controls per overlay.
- No asset hosting.
- `<script>`, `<iframe>`, `<embed>` and similar tags are stripped from template content before storage.
  External stylesheets, fonts, icon libraries and CDN-hosted CSS are all fine. Inline scripts are not.
- Overlay access uses 64-character hex tokens passed in the URL fragment, so they are never sent to the
  server. Tokens are hashed on storage, revocable, and can expire or be bound to an IP range.

---

## Tech stack

| Layer             | Technology                             |
|-------------------|----------------------------------------|
| Backend           | Laravel 12, PHP 8.4                    |
| Frontend          | Vue 3 (Composition API), TypeScript    |
| Styling           | TailwindCSS v4                         |
| UI components     | RekaUI/Shadcn/Vue                      |
| Full-stack bridge | Inertia.js                             |
| Real-time         | Laravel Reverb (self-hosted WebSocket) |
| Code editor       | CodeMirror                             |
| Database          | PostgreSQL                             |
| Queue             | Redis                                  |
| Build             | Vite                                   |

---

## Self-hosting

**No support is provided when you self-host Overlabels.** The following is for development and testing.

```bash
git clone https://github.com/jasperfrontend/overlabels
cd overlabels
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
composer run dev
```

Required environment variables:

```env
TWITCH_CLIENT_ID=
TWITCH_CLIENT_SECRET=
APP_URL=https://your-public-url.com
APP_STARTER_KIT_ID=
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
DB_CONNECTION=pgsql
BROADCAST_CONNECTION=reverb
```

Optional, for the OAuth-based integrations only. Ko-fi, Buy Me a Coffee and Throne authenticate per-user
with a token or a signature, so they need no environment configuration at all.

```env
# StreamLabs (OAuth + Socket.IO listener)
STREAMLABS_CLIENT_ID=
STREAMLABS_CLIENT_SECRET=
STREAMLABS_LISTENER_SECRET=

# Fourthwall (OAuth)
FW_CLIENT_ID=
FW_CLIENT_SECRET=
FW_AUTH_URL=
FW_REDIRECT_URL=
FW_HMAC=
```

Your `APP_URL` must be publicly reachable for Twitch EventSub webhooks to deliver. For local development,
use [ngrok](https://ngrok.com) or a similar tunnel.

---

## Sustainability

Overlabels is free forever, for anyone. Its footprint is small: the whole backend runs on a few instances
and hosting costs less than a PhpStorm licence. There are plans beyond that, but nothing that turns the
overlays into a paywall.

If you like what this is, a [Ko-fi](https://ko-fi.com/jasperfromoverlabels) tip is always welcome. Mention
this README in your tip so I can link your support back to Overlabels.

---

## License

Overlabels is licensed under the **GNU Affero General Public License, version 3 or later**
(`AGPL-3.0-or-later`), as of August 9th, 2026. The full text is in [LICENSE](LICENSE).

Before that date this repository carried no licence at all. The `"license": "MIT"` line in `composer.json`
arrived untouched in the initial Laravel scaffold commit (`783b81fc`) and was never a deliberate choice; no
`LICENSE` file granting MIT terms was ever published here. The relicense is forward-only and no history has
been rewritten.

In practice: you can read, run, modify and share the source freely. If you self-host a **modified** version
that other people can reach over a network, AGPL section 13 requires you to offer those users the source of
your modified version. Running an unmodified copy for yourself carries no such obligation. This governs the
Overlabels source code, not the overlay templates, kits and controls you create with it - those are yours.

---

## Contributing

Questions, ideas and improvements are welcome. Open an issue or a pull request.

[CONTRIBUTING.md](CONTRIBUTING.md) covers the workflow, the house rules for user-facing copy, and how to
sign off your commits. Participation is covered by the [Code of Conduct](CODE_OF_CONDUCT.md). Security
issues go through [SECURITY.md](SECURITY.md).

Contributions are accepted under the same AGPL-3.0-or-later terms as the rest of the project.

~ JasperDiscovers
