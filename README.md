# Overlabels

Overlabels is a declarative, state-driven overlay DSL for Twitch streamers. You write HTML and CSS. You bind live Twitch data using a triple-bracket tag syntax. You define typed, mutable controls that respond to your dashboard in real time. Twitch events trigger alert templates that render, transition, and disappear — automatically.

There is no drag-and-drop editor. There is no proprietary file format. There is no runtime you do not control. Your overlay is a webpage, and Overlabels is the engine that keeps it alive.

---

## The Syntax

Overlabels uses a deliberate, collision-resistant template syntax: triple square brackets. It is distinctive enough to never clash with HTML, CSS, JavaScript, or any template engine you might encounter in the wild, and simple enough to understand without documentation.

```html
<span class="followers">[[[followers_total]]]</span>
```

That is it. Drop a tag anywhere in your HTML or CSS, and Overlabels replaces it with live data when the overlay renders. The entire system — static data, live controls, conditional blocks — runs through this one syntax.

---

## Template Tags

Template tags are resolved from live Twitch API data fetched on overlay load and updated in real time when events fire. Every tag maps deterministically to a value in the Twitch Helix API response.

### User & Channel

| Tag | Description |
|-----|-------------|
| `[[[user_name]]]` | Display name of the broadcaster |
| `[[[user_broadcaster_type]]]` | `affiliate`, `partner`, or empty |
| `[[[user_description]]]` | Channel bio |
| `[[[user_view_count]]]` | All-time view count |
| `[[[user_avatar]]]` | Profile image URL |
| `[[[channel_title]]]` | Current stream title |
| `[[[channel_game]]]` | Current game/category |
| `[[[channel_language]]]` | Broadcast language code |
| `[[[channel_tags_0]]]` — `[[[channel_tags_10]]]` | Individual stream tags by index |

### Followers & Subscriptions

| Tag | Description |
|-----|-------------|
| `[[[followers_total]]]` | Total follower count |
| `[[[followers_latest_user_name]]]` | Most recent follower's display name |
| `[[[followers_latest_date]]]` | Timestamp of most recent follow |
| `[[[subscribers_total]]]` | Total subscriber count |
| `[[[subscribers_points]]]` | Subscriber points total |
| `[[[subscribers_latest_user_name]]]` | Most recent subscriber |
| `[[[subscribers_latest_tier]]]` | Subscription tier (1000, 2000, 3000) |
| `[[[subscribers_latest_is_gift]]]` | Whether the latest sub was gifted |

### Goals

| Tag | Description |
|-----|-------------|
| `[[[goals_latest_type]]]` | Goal type (follower, subscription, etc.) |
| `[[[goals_latest_current]]]` | Current progress value |
| `[[[goals_latest_target]]]` | Target value |
| `[[[goals_latest_description]]]` | Goal description |

Tags can be used **anywhere** in your HTML or CSS. Font sizes, colour values, content attributes, `aria-label` — wherever a value belongs, a tag can go.

```css
.follower-bar {
  width: calc([[[followers_total]]] / [[[goals_latest_target]]] * 100%);
}
```

---

## Controls

Controls are typed, persistent, mutable values attached to a template. They are the state layer of Overlabels. Define a control once, reference it anywhere in your overlay, and update it from your dashboard while the overlay is live. The update propagates instantly over WebSocket — no reload required.

Reference a control in your template with the `c:` prefix:

```html
<span class="username">[[[c:myname]]]</span>
```

### Control Types

| Type | Stores | Use Case |
|------|--------|----------|
| `text` | String (up to 1000 chars, HTML stripped) | Names, messages, labels |
| `number` | Numeric string | Scores, goals, counters with arbitrary values |
| `counter` | Integer string | Kill counters, death counters, incrementable values |
| `timer` | Derived from config state | Countup and countdown timers with live tick |
| `datetime` | ISO 8601 string | Scheduled events, stream start times |
| `boolean` | `"1"` or `"0"` | Feature flags, show/hide blocks, on/off state |

Each control has a `key` (alphanumeric + underscore, used in your template), a `label` (shown in your dashboard), and a `type` that determines how the value is sanitised, resolved, and displayed.

### Timers

The `timer` control type is a first-class citizen. Configure a timer as a countup or countdown, set the base duration, and start it from the control panel. Overlabels computes the current elapsed or remaining value client-side at up to 250ms resolution and broadcasts the state over Pusher so every connected overlay stays in sync.

```html
<div class="timer">[[[c:round_timer]]]</div>
```

Timer config drives everything: `mode` (countup or countdown), `base_seconds`, `offset_seconds`, `running`, and `started_at`. The display value is derived deterministically from these fields — there is no mutable tick state to drift or desync.

### Boolean Controls

Combine boolean controls with the conditional syntax to show or hide entire overlay sections from your dashboard without touching the template:

```html
[[[if:c:show_donations]]]
  <div class="donation-bar">...</div>
[[[endif]]]
```

Flip the control in your dashboard. The overlay responds instantly.

### Control Limits & Broadcasting

Each template supports up to **20 controls**. Every control value update is broadcast as a `control.updated` event on the `alerts.{twitch_id}` Pusher channel. The overlay receives the update, replaces the tag value in reactive state, and re-renders the affected nodes. The rest of the overlay is untouched.

---

## Conditional Rendering

Overlabels includes a full conditional rendering engine evaluated client-side in the overlay. Any template tag — whether it references Twitch API data, a control value, or an event payload — can drive a conditional block.

### Syntax

```html
[[[if:variable operator value]]]
  ...
[[[elseif:variable operator value]]]
  ...
[[[else]]]
  ...
[[[endif]]]
```

### Operators

| Operator | Meaning |
|----------|---------|
| `=` | Equal |
| `!=` | Not equal |
| `>` | Greater than |
| `<` | Less than |
| `>=` | Greater than or equal |
| `<=` | Less than or equal |

Numeric comparisons are numeric. String comparisons are lexicographic. Truthiness checks (`[[[if:c:show_alerts]]]`) treat `"0"`, `"false"`, empty string, `null`, and `undefined` as falsy — everything else is truthy.

### Examples

```html
[[[if:channel_language = en]]]
  <p>English stream</p>
[[[elseif:channel_language = es]]]
  <p>Stream en Español</p>
[[[else]]]
  <p>Welcome</p>
[[[endif]]]

[[[if:followers_total >= 1000]]]
  <div class="milestone">1K Followers reached</div>
[[[endif]]]

[[[if:subscribers_latest_is_gift]]]
  <span>Gift sub incoming</span>
[[[endif]]]
```

Conditionals evaluate after tag replacement, so the full resolved value of any tag — including live control values — is available to every comparison. Nesting is supported up to 10 levels deep.

---

## Event Alerts

Event alert templates are separate overlays triggered by Twitch EventSub events. They share the full template tag syntax and support all control references and conditional blocks. When a Twitch event fires, Overlabels renders the assigned alert template with the event payload merged into the tag context, broadcasts it to the overlay via WebSocket, and displays it with a configurable transition and duration.

### Supported Events

| Event | Twitch EventSub Type |
|-------|---------------------|
| New Follower | `channel.follow` |
| New Subscription | `channel.subscribe` |
| Gift Subscriptions | `channel.subscription.gift` |
| Resubscription | `channel.subscription.message` |
| Bits Cheer | `channel.cheer` |
| Raid | `channel.raid` |
| Channel Points Redemption | `channel.channel_points_custom_reward_redemption.add` |
| Stream Online | `stream.online` |
| Stream Offline | `stream.offline` |

### Event-Specific Tags

Each event type exposes its payload as template tags. A few examples:

**Follows**
```html
<span>[[[event.user_name]]] just followed!</span>
```

**Subscriptions**
```html
<span>[[[event.user_name]]] subscribed at Tier [[[event.tier]]]</span>
```

**Gift Subscriptions**
```html
<span>[[[event.user_name]]] gifted [[[event.total]]] subs!</span>
```

**Raids**
```html
<span>[[[event.from_broadcaster_user_name]]] is raiding with [[[event.viewers]]] viewers</span>
```

**Bits**
```html
<span>[[[event.user_name]]] cheered [[[event.bits]]] bits</span>
```

### Assigning Alerts

The **Alerts Builder** maps event types to alert templates. Each mapping stores a display duration (in milliseconds), a transition effect, and an enabled flag. You can disable individual alert types without removing the mapping.

Alerts require a static overlay to act as a container. Without a running static overlay on the same browser source, there is no DOM to render into.

---

## The Rendering Pipeline

Understanding the pipeline removes all mystery from how Overlabels works.

### Static Overlays

1. A browser source in OBS loads `https://overlabels.com/overlay/{slug}#token`
2. The token in the URL fragment is extracted client-side — it is **never sent to the server**
3. The frontend initialises an Echo/Pusher connection and mounts the `OverlayRenderer` Vue component
4. The renderer fetches template data and live Twitch values using the token
5. Tag replacement runs: `[[[tag]]]` patterns are replaced with resolved values
6. Conditional blocks are evaluated and non-matching branches are removed from output
7. CSS is injected into the document head via a `<style id="overlay-style">` tag
8. The rendered HTML is mounted into the overlay DOM
9. Pusher channel `alerts.{twitch_id}` is subscribed for live updates

### Live Updates

After initial render, the overlay stays live through three update mechanisms:

- **Control updates** — `control.updated` Pusher events trigger reactive re-renders of affected tags
- **EventSub events** — `channel.follow`, `channel.subscribe`, etc. cause relevant aggregate tags (e.g., `followers_total`) to update
- **Alert triggers** — Alert templates are broadcast as complete render payloads, displayed over the static overlay, and auto-dismissed after their configured duration

### Alert Render Flow

1. Twitch EventSub fires a webhook to `/api/twitch/webhook`
2. The webhook is HMAC-SHA256 validated using the per-user webhook secret
3. The event is stored and broadcast over the internal queue
4. A mapping lookup finds the assigned template for this event type and user
5. Current overlay data is merged with the event payload
6. The compiled alert is broadcast to `alerts.{twitch_id}` via Pusher
7. The overlay frontend receives the payload, renders it into the alert DOM node
8. The configured transition plays, the alert displays for `duration_ms` milliseconds, then auto-dismisses

### Script Tag Policy

`<script>` tags are stripped from all template content — `head`, `html`, `css`, and meta fields — before storage. This is enforced client-side before form submission. External stylesheets, font libraries, icon libraries, and CDN-hosted CSS are all permitted. Inline scripts are not.

---

## Overlay Kits

An Overlay Kit is a named collection of templates designed to work together as a cohesive system. A kit might include a static overlay, a follower alert, a subscription alert, a raid alert, and a channel points redemption alert — all sharing a visual language, matching CSS variables, and a consistent tag vocabulary.

Kits can be forked in a single action. Forking a kit creates copies of every template it contains, owned by the forking user, ready to customise. The `fork_count` on the original kit is preserved so creators can see reach.

---

## Forking

Any public template or kit can be forked. Forking creates a full independent copy owned by you. The original is untouched. Your fork is yours to modify, extend, or break however you like.

When forking a template that has **Controls**, the Fork Import Wizard walks you through which controls to carry over. Control references in the template are preserved as-is — you pick what state comes with the fork.

Public templates are public by default. Privacy is opt-in. There are no paywalls, no tiers, no licensing restrictions.

---

## Onboarding

When a new account is created, Overlabels runs an automated onboarding pipeline in the background:

1. **Webhook secret** — A per-user 32-byte hex secret is generated for HMAC validation
2. **Starter kit** — The configured starter kit is automatically forked into your account, giving you a working set of templates on day one
3. **Alert assignment** — Forked templates are matched to event types by keyword detection and auto-assigned in the Alerts Builder
4. **Tag generation** — A background job fetches your live Twitch data and generates your full personalised tag set

The onboarding status endpoint tracks each step: `kit_forked`, `tags_status`, `alerts_mapped`, `token_created`, and `has_webhook_secret`. The dashboard reflects this state in real time as each step completes.

---

## Testing

The `/testing` page provides a personalised testing environment for every supported Twitch event. Each command is pre-filled with your Twitch ID, your webhook URL, and your per-user webhook secret — ready to copy and run directly in your terminal via the [Twitch CLI](https://github.com/twitchdev/twitch-cli).

```bash
twitch event trigger channel.follow \
  --transport=webhook \
  -F https://overlabels.com/api/twitch/webhook \
  -s your_webhook_secret \
  --to-user your_twitch_id \
  --from-user 1234567
```

Commands are blurred by default and revealed on hover. No secrets are ever exposed in page source.

---

## Token Security

Overlay access is authenticated via 64-character hexadecimal tokens (256 bits of randomness). Tokens are:

- **Hashed on storage** — only the SHA-256 hash is stored server-side; the plain token is shown once
- **Never transmitted in the URL path** — passed as a URL fragment (`#token`), which browsers do not include in HTTP requests
- **Revocable** — a compromised token can be disabled or deleted without affecting other tokens
- **Optionally expiring** — set an `expires_at` to time-limit overlay access
- **Optionally IP-restricted** — bind a token to a specific IP or CIDR range

Public overlays additionally support hash-based shareable links that require no token at all, intended for public display or embedding without authentication.

---

## Adding to OBS

1. Generate a token in your Overlabels dashboard
2. Copy your static overlay URL: `https://overlabels.com/overlay/{slug}#your_token`
3. Add it as a **Browser Source** in OBS
4. Set the source dimensions to match your canvas (typically 1920×1080)
5. Your overlay is live

When a Twitch event fires — a follow, a sub, a raid — the alert template renders over the static overlay, transitions in, displays for the configured duration, and transitions out. No interaction required.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Vue 3 (Composition API), TypeScript |
| Styling | TailwindCSS v4 |
| UI Components | Shadcn/Vue |
| Full-stack bridge | Inertia.js |
| Real-time | Echo + Pusher (WebSocket) |
| Code editor | CodeMirror |
| Database | PostgreSQL |
| Queue | Redis |
| Build | Vite |

---

## Self-Hosting

```bash
git clone https://github.com/yourhandle/overlabels
cd overlabels
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
composer run dev
```

**Required environment variables:**

```env
TWITCH_CLIENT_ID=
TWITCH_CLIENT_SECRET=
APP_URL=https://your-public-url.com
APP_STARTER_KIT_ID=
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
DB_CONNECTION=pgsql
```

Your `APP_URL` must be publicly reachable for Twitch EventSub webhooks to deliver. For local development, use [ngrok](https://ngrok.com) or a similar tunnel.

---

## Philosophy

Overlabels is built on one assumption: streamers who can write a `<div>` should not be locked out of good overlays by proprietary tools.

The triple-bracket DSL exists because simplicity is not the same as limitation. The Controls system exists because overlays should respond to a streamer's intent, not just Twitch's event stream. The forking system exists because good design compounds — every overlay that exists is a starting point for the next one.

Overlabels overlays are free forever, for anyone, anytime. No paywalls. No tiers. No limits.

---

## Contributing

If you have questions, ideas, or improvements, open an issue or submit a pull request. Overlabels grows best when people build on top of it.
