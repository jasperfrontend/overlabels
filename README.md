# Overlabels

Build custom Twitch overlays and alerts in HTML and CSS, powered by live data tags, real-time controls,
and external integrations.

There is no drag-and-drop editor. There is no proprietary file format. There is no runtime you do not control.
Your overlay is a webpage, and Overlabels is the engine that keeps it alive.

---

## The Syntax

Overlabels uses a deliberate, collision-resistant template syntax: triple square brackets. It is distinctive enough to
never clash with HTML, CSS, JavaScript, or any template engine you might encounter in the wild,
and simple enough to understand without documentation.

```html
<span class="followers">[[[followers_total]]]</span>
```

That is it. Drop a tag anywhere in your HTML or CSS, and Overlabels replaces it with live data when the overlay renders.
The entire system - static data, live controls, conditional blocks - runs through this one syntax.

---

## Template Tags

Template tags are resolved from live Twitch API data fetched on overlay load and updated in real time when events fire.
Every tag maps deterministically to a value in the Twitch Helix API response.

### User & Channel

| Tag                                              | Description                      |
|--------------------------------------------------|----------------------------------|
| `[[[user_name]]]`                                | Display name of the broadcaster  |
| `[[[user_broadcaster_type]]]`                    | `affiliate`, `partner`, or empty |
| `[[[user_description]]]`                         | Channel bio                      |
| `[[[user_view_count]]]`                          | All-time view count              |
| `[[[user_avatar]]]`                              | Profile image URL                |
| `[[[channel_title]]]`                            | Current stream title             |
| `[[[channel_game]]]`                             | Current game/category            |
| `[[[channel_language]]]`                         | Broadcast language code          |
| `[[[channel_tags_0]]]` - `[[[channel_tags_10]]]` | Individual stream tags by index  |

### Followers & Subscriptions

| Tag                                  | Description                          |
|--------------------------------------|--------------------------------------|
| `[[[followers_total]]]`              | Total follower count                 |
| `[[[followers_latest_user_name]]]`   | Most recent follower's display name  |
| `[[[followers_latest_date]]]`        | Timestamp of most recent follow      |
| `[[[subscribers_total]]]`            | Total subscriber count               |
| `[[[subscribers_points]]]`           | Subscriber points total              |
| `[[[subscribers_latest_user_name]]]` | Most recent subscriber               |
| `[[[subscribers_latest_tier]]]`      | Subscription tier (1000, 2000, 3000) |
| `[[[subscribers_latest_is_gift]]]`   | Whether the latest sub was gifted    |

### Goals

| Tag                              | Description                              |
|----------------------------------|------------------------------------------|
| `[[[goals_latest_type]]]`        | Goal type (follower, subscription, etc.) |
| `[[[goals_latest_current]]]`     | Current progress value                   |
| `[[[goals_latest_target]]]`      | Target value                             |
| `[[[goals_latest_description]]]` | Goal description                         |

Tags can be used **anywhere** in your HTML or CSS. Font sizes, colour values, content attributes, `aria-label` -
wherever a value belongs, a tag can go.

```css
.follower-bar {
  width: calc([[[followers_total]]] / [[[goals_latest_target]]] * 100%);
}
```

---

## Controls

Controls are typed, persistent, mutable values attached to a template. They are the **state layer** of Overlabels.
Define a control once, reference it anywhere in your overlay, and update it from your dashboard while the
overlay is live. The update propagates instantly over WebSocket, no reload required.

Reference a control in your template with the `c:` prefix:

```html
<span class="username">[[[c:myname]]]</span>
```

### Control Types

| Type         | Stores                                   | Use Case                                            |
|--------------|------------------------------------------|-----------------------------------------------------|
| `text`       | String (up to 1000 chars, HTML stripped) | Names, messages, labels                             |
| `number`     | Numeric string                           | Scores, goals, counters with arbitrary values       |
| `counter`    | Integer string                           | Kill counters, death counters, incrementable values |
| `timer`      | Derived from config state                | Countup, countdown, and count-to-datetime timers    |
| `datetime`   | ISO 8601 string                          | Scheduled events, stream start times                |
| `boolean`    | `"1"` or `"0"`                           | Feature flags, show/hide blocks, on/off state       |
| `expression` | Derived from formula                     | Computed values that reference other controls       |

Each control has a `key` (alphanumeric and underscore, used in your template), a `label` (shown in your dashboard),
and a `type` that determines how the value is sanitised, resolved, and displayed.

### Timers

The `timer` control type is a first-class citizen with three modes:

- **Count up**: Starts at zero and counts upward
- **Countdown**: Starts at a configured duration and counts down to zero
- **Count to**: Counts down the remaining time until a target date and time

```html

<div class="timer">[[[c:round_timer]]]</div>
```

Timer config drives everything: `mode`, `base_seconds`, `offset_seconds`, `running`, `started_at`,
and `target_datetime`.
The display value is derived deterministically from these fields.
There is no mutable tick state to drift or desync. Count-to timers always tick and need no start/stop interaction.

You can read timer values in your Controls, so you can use them to perform complex calculations through
Expressions Controls.

### Expression Controls

Expression controls are formulas that reference other controls and evaluate entirely client-side with zero latency.
They enable derived values without any server round-trips.

```
c.streamlabs.latest_donor_at > c.kofi.latest_donor_at
  ? c.streamlabs.latest_donor_name
  : c.kofi.latest_donor_name
```

Use them in your template like any other control:

```html
<span class="latest-donor">[[[c:latest_donor]]]</span>
```

Expressions re-evaluate reactively whenever any referenced control value changes. They support arithmetic, comparisons,
ternary operators, and can reference controls from any source, including external integrations.

### Control Timestamps

Every control has a companion `_at` value containing the Unix timestamp of its last update. These are available as
template tags and in expressions:

```html
<span class="updated">[[[c:death_counter_at]]]</span>
```

This enables cross-service timing comparisons - for example, showing whichever donation arrived most recently across
Ko-fi and StreamLabs.

### Boolean Controls

Combine boolean controls with the conditional syntax to show or hide entire overlay sections from your dashboard
without touching the template:

```html
[[[if:c:show_donations]]]
<div class="donation-bar">...</div>
[[[endif]]]
```

Flip the control in your dashboard. The overlay responds instantly.

### Control Limits & Broadcasting

Each template supports up to **~~20~~ 50 controls**. Every control value update is broadcast as a `control.updated`
event on the `alerts.{twitch_id}` WebSocket channel. The overlay receives the update, replaces the tag value in
reactive state, and re-renders the affected nodes. The rest of the overlay is untouched.

---

## Conditional Rendering

Overlabels includes a full conditional rendering engine evaluated client-side in the overlay. Any template tag -
whether it references Twitch API data, a control value, or an event payload - can drive a conditional block.

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

| Operator | Meaning               |
|----------|-----------------------|
| `=`      | Equal                 |
| `!=`     | Not equal             |
| `>`      | Greater than          |
| `<`      | Less than             |
| `>=`     | Greater than or equal |
| `<=`     | Less than or equal    |

Numeric comparisons are numeric. String comparisons are lexicographic. Truthiness checks (`[[[if:c:show_alerts]]]`)
treat `"0"`, `"false"`, empty string, `null`, and `undefined` as falsy - everything else is truthy.

### Examples

```html
[[[if:channel_language = en]]]
<p>English stream</p>
[[[elseif:channel_language = es]]]
<p>Stream en Espanol</p>
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

Conditionals evaluate after tag replacement, so the full resolved value of any tag - including live control values -
is available to every comparison. Nesting is supported up to 10 levels deep.

---

## Event Alerts

Event alert templates are separate overlays triggered by Twitch EventSub events. They share the full template tag
syntax and support all control references and conditional blocks. When a Twitch event fires,
Overlabels renders the assigned alert template with the event payload merged into the tag context,
broadcasts it to the overlay via WebSocket, and displays it with a configurable transition and duration.

### Supported Twitch Events

| Event                     | Twitch EventSub Type                                  |
|---------------------------|-------------------------------------------------------|
| New Follower              | `channel.follow`                                      |
| New Subscription          | `channel.subscribe`                                   |
| Gift Subscriptions        | `channel.subscription.gift`                           |
| Resubscription            | `channel.subscription.message`                        |
| Bits Cheer                | `channel.cheer`                                       |
| Raid                      | `channel.raid`                                        |
| Channel Points Redemption | `channel.channel_points_custom_reward_redemption.add` |
| Stream Online             | `stream.online`                                       |
| Stream Offline            | `stream.offline`                                      |

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

### Alert Targeting

By default, alerts fire on every connected static overlay. Alert targeting lets you restrict specific alerts to
specific static overlays. This is useful when you have multiple scenes in OBS, for example, showing donation
alerts only on your main gameplay overlay, not on your BRB screen.

If no targeting is configured, the alert fires everywhere (backward-compatible default).

### Assigning Alerts

The **Alerts Builder** maps event types to alert templates. Each mapping stores a display duration (in milliseconds),
a transition effect, and an enabled flag. You can disable individual alert types without removing the mapping.

Alerts require a static overlay to act as a container. Without a running static overlay on the same browser source,
there is no DOM to render into.

---

## External Integrations

Overlabels connects to external services that drive controls and trigger alerts alongside Twitch events.
Each integration normalises incoming data into the same control and alert pipeline, your template syntax stays
the same regardless of the source.

Overlabels does not sell donation ingest, so it has no reason to care which service the money came through.
Every donation integration is a first-class citizen.

### The Shared Donation Schema

All five donation integrations - Ko-fi, StreamLabs, Fourthwall, Buy Me a Coffee, and Throne - expose the
**same six controls**. Only the prefix changes, so a template written against one service ports to another by
swapping the namespace and nothing else.

| Control                     | Type      | Holds                            |
|-----------------------------|-----------|----------------------------------|
| `donations_received`        | `counter` | Number of donations received     |
| `latest_donor_name`         | `text`    | Most recent donor                |
| `latest_donation_amount`    | `number`  | Most recent donation amount      |
| `latest_donation_message`   | `text`    | Most recent donation message     |
| `latest_donation_currency`  | `text`    | Most recent donation currency    |
| `total_received`            | `number`  | Running total for the session    |

```html
<span>[[[c:kofi:latest_donor_name]]]</span>
<span>[[[c:throne:latest_donor_name]]]</span>
```

Two services extend the schema with their own keys; the six above are always available.

**Connect a service and its controls appear.** All of them, automatically, the moment the connection is
made. They are service-managed: read-only to you and to the API, updating only when the service sends new
data. The presets list in the control editor is there for authoring, so you can see which keys exist while
writing a template, not because anything needs adding by hand.

Because every control carries an `_at` companion timestamp, you can compare across services to find the
genuinely most recent supporter regardless of which platform they used:

```
latest(
  c.kofi.latest_donor_name_at,       c.kofi.latest_donor_name,
  c.streamlabs.latest_donor_name_at, c.streamlabs.latest_donor_name,
  c.throne.latest_donor_name_at,     c.throne.latest_donor_name
)
```

### Ko-fi

Ko-fi webhooks deliver donation, subscription, shop order, and commission events.
Each event can trigger alerts and update controls.

Controls use the `kofi:` namespace:

```html
<span>[[[c:kofi:donations_received]]]</span>
<span>[[[c:kofi:latest_donor_name]]]</span>
```

Event types: `donation`, `subscription`, `shop_order`, `commission`

Ko-fi controls are added from presets in the control editor once the integration is connected.

### StreamLabs

StreamLabs connects via OAuth - one click, no token to paste - and delivers donation events through a
Socket.IO bridge rather than webhooks. The six shared controls are provisioned automatically on connect.

Event types: `donation`

```html

<div class="donation">
  [[[c:streamlabs:latest_donor_name]]] donated
  [[[c:streamlabs:latest_donation_amount]]] [[[c:streamlabs:latest_donation_currency]]]
</div>
```

### Fourthwall

Fourthwall connects via OAuth from the integration page - two clicks, no token to paste. The six shared
controls are provisioned automatically on connect.

Event types: `donation`

```html
<span>[[[c:fourthwall:latest_donor_name]]] tipped [[[c:fourthwall:latest_donation_amount]]]</span>
```

### Buy Me a Coffee

Paste your Buy Me a Coffee verification token, set your webhook URL, done. Buy Me a Coffee covers more than
one-off donations, so it provisions **seven** controls: the shared six plus a support type.

| Control              | Type   | Holds                                                                          |
|----------------------|--------|--------------------------------------------------------------------------------|
| `latest_support_type`| `text` | `Supporter`, `Commission`, `Extra`, `Membership`, `Subscription`, or `Wishlist` |

Event types: `donation`, `commission`, `extra`, `membership`, `recurring`, `wishlist`

Combine it with a conditional to render a different alert per support type:

```html
[[[if:c:bmac:latest_support_type = Membership]]]
<span>[[[c:bmac:latest_donor_name]]] joined the membership!</span>
[[[else]]]
<span>[[[c:bmac:latest_donor_name]]] bought a coffee</span>
[[[endif]]]
```

### Throne

Connect Throne and paste your Overlabels webhook URL into Throne. There is no token to copy - Throne signs
every webhook with its own key, which Overlabels verifies. Throne delivers gifts rather than cash, so it
provisions **nine** controls: the shared six plus three gift-specific ones.

| Control                     | Type   | Holds                                       |
|-----------------------------|--------|---------------------------------------------|
| `latest_item_name`          | `text` | Name of the most recent gift                |
| `latest_item_thumbnail_url` | `text` | Product image URL, ready for an `<img src>` |
| `latest_is_surprise_gift`   | `text` | `1` when the gift was a surprise, else `0`  |

Event types: `donation`

`latest_is_surprise_gift` works directly with the truthiness form of a conditional, since `"0"` is falsy:

```html
<img src="[[[c:throne:latest_item_thumbnail_url]]]" alt="[[[c:throne:latest_item_name]]]">

[[[if:c:throne:latest_is_surprise_gift]]]
<span>A surprise gift from [[[c:throne:latest_donor_name]]]!</span>
[[[endif]]]
```

### Integration Pipeline

All external services follow the same pipeline:

1. Incoming webhook or socket event is verified and parsed
2. The event is deduplicated and stored
3. Matching controls are updated and broadcast in real time
4. If an alert template is mapped to the event type, it fires through the standard alert pipeline

Service-managed controls (auto-provisioned by integrations) cannot be manually edited, they update only when the
service sends new data.

---

## The Rendering Pipeline

Understanding the pipeline removes all mystery from how Overlabels works.

### Static Overlays

1. A browser source in OBS loads `https://overlabels.com/overlay/{slug}#token`
2. The token in the URL fragment is extracted client-side - it is **never sent to the server**
3. The frontend initialises a WebSocket connection via Laravel Reverb and mounts the `OverlayRenderer` Vue component
4. The renderer fetches template data and live Twitch values using the token
5. Tag replacement runs: `[[[tag]]]` patterns are replaced with resolved values
6. Expression controls are registered and evaluated client-side
7. Conditional blocks are evaluated and non-matching branches are removed from output
8. CSS is injected into the document head via a `<style id="overlay-style">` tag
9. The rendered HTML is mounted into the overlay DOM
10. The WebSocket channel `alerts.{twitch_id}` is subscribed for live updates

### Live Updates

After initial render, the overlay stays live through three update mechanisms:

- **Control updates**: `control.updated` WebSocket events trigger reactive re-renders of affected tags and
- re-evaluation of expressions that reference the changed control
- **EventSub events**: `channel.follow`, `channel.subscribe`, etc. cause relevant aggregate tags
- (e.g., `followers_total`) to update
- **Alert triggers**: Alert templates are broadcast as complete render payloads, displayed over the static overlay,
- and auto-dismissed after their configured duration

### Overlay Health

The overlay includes built-in resilience for the unpredictable environment of OBS browser sources:

- Automatic reconnection with exponential backoff when the WebSocket drops
- Periodic health checks to detect stale connections
- Visual error banners (since OBS browser sources cannot show console output)
- Auto-reload as a last resort when recovery fails

### Alert Render Flow

1. A Twitch EventSub webhook or external service event arrives
2. The event is validated (HMAC-SHA256 for Twitch, per-service verification for externals)
3. The event is stored and broadcast over the internal queue
4. A mapping lookup finds the assigned template for this event type and user
5. Current overlay data is merged with the event payload
6. The compiled alert is broadcast to `alerts.{twitch_id}` via WebSocket
7. The overlay frontend receives the payload, checks alert targeting rules, and renders into the alert DOM node
8. The configured transition plays, the alert displays for `duration_ms` milliseconds, then auto-dismisses

### Script/Embed/iFrame Tag Policy

`<script>`, `<embed>`, `<iframe>` and any other scary tag are stripped from all template content - `head`, `html`, 
`css`, and meta fields - before storage. This is visualised client-side but also enforced server-side
before form submission. External stylesheets, font libraries, icon libraries,
and CDN-hosted CSS are all permitted. Inline scripts are not. Neither are embeds. This is to prevent
XSS attacks and to prevent accidental embedding of malicious content. If you need to embed a third-party
script, you're out of luck. Simple as that.

---

## Overlay Kits

An Overlay Kit is a named collection of templates designed to work together as a cohesive system.
A kit might include a static overlay, a follower alert, a subscription alert, a raid alert, and a
channel points redemption alert. All sharing a visual language, matching CSS variables,
and a consistent tag vocabulary.

Kits can be copied in a single action. Copying a kit creates duplicates of every template it contains,
owned by you, ready to customise.

**Note:** Kits are user-generated content. They can contain anything you like, or nothing you want. Have a good
look at the Kit's contents before you copy it to your own account.

---

## Copying

Any public template or kit can be copied. Copying creates a full independent duplicate owned by you.
The original is untouched. Your copy is yours to modify, extend, or break however you like.

When copying a template that has **Controls**, the Import Wizard walks you through which controls to carry over.
Control references in the template are preserved as-is - you pick what state comes with the copy.

Public templates are public by default. Privacy is opt-in. There are no paywalls, no tiers, no licensing restrictions.

---

## Onboarding

When a new account is created, Overlabels runs an automated onboarding pipeline in the background:

1. **Webhook secret** - A per-user 32-byte hex secret is generated for HMAC validation
2. **Starter kit**: The configured starter kit is automatically copied into your account,
3. giving you a working set of templates on day one
4. **Alert assignment**: Copied templates are matched to event types by keyword detection and
5. auto-assigned in the Alerts Builder
6. **Tag generation**: A background job fetches your live Twitch data and generates your full personalised tag set

The onboarding status endpoint tracks each step: `kit_forked`, `tags_status`, `alerts_mapped`, `token_created`,
and `has_webhook_secret`. The dashboard reflects this state in real time as each step completes.

---

## Testing

The `/testing` page provides a personalised testing environment for every supported Twitch event.
Each command is pre-filled with your Twitch ID, your webhook URL, and your per-user webhook secret,
ready to copy and run directly in your terminal via the [Twitch CLI](https://github.com/twitchdev/twitch-cli).

```bash
twitch event trigger channel.follow \
  --transport=webhook \
  -F https://overlabels.com/api/twitch/webhook \
  -s your_webhook_secret \
  --to-user your_twitch_id \
  --from-user 1234567
```

Commands are blurred by default and revealed on hover. *Don't leak these commands on stream!*

---

## Token Security

Overlay access is authenticated via 64-character hexadecimal tokens (256 bits of randomness). Tokens are:

- **Hashed on storage**: only the SHA-256 hash is stored server-side; the plain token is shown once
- **Never transmitted in the URL path**: passed as a URL fragment (`#token`), which browsers do not include
- in HTTP requests
- **Revocable**: a compromised token can be disabled or deleted without affecting other tokens
- **Optionally expiring**: set an `expires_at` to time-limit overlay access
- **Optionally IP-restricted**: bind a token to a specific IP or CIDR range

Public overlays additionally support hash-based shareable links that require no token at all, intended for
public display or embedding without authentication.

---

## Adding to OBS

1. Generate a static overlay in Overlabels. Every alert template requires a static overlay to render into.
2. A big "Add to OBS" button appears in the template viewer. Click it and a secure overlay URL is copied to your 
   clipboard. You can also drag the button directly into OBS and it will generate a Browser Source with perfect 
   defaults for you. This is the recommended way to add an overlay to OBS
3. If you want to manually add a browser source, copy the URL and paste it into a new Browser Source in OBS.
4. Set the source dimensions to match your canvas (typically 1920x1080).
5. Your overlay is live!

When a Twitch event fires - a follow, a sub, a raid - or an external event arrives - a Ko-fi donation, a
StreamLabs tip&hellip; The alert template renders over the static overlay, transitions in, displays for the configured
duration, and transitions out. No interaction required.

---

## Tech Stack

| Layer             | Technology                             |
|-------------------|----------------------------------------|
| Backend           | Laravel 12, PHP 8.4                    |
| Frontend          | Vue 3 (Composition API), TypeScript    |
| Styling           | TailwindCSS v4                         |
| UI Components     | RekaUI/Shadcn/Vue                      |
| Full-stack bridge | Inertia.js                             |
| Real-time         | Laravel Reverb (self-hosted WebSocket) |
| Code editor       | CodeMirror                             |
| Database          | PostgreSQL                             |
| Queue             | Redis                                  |
| Build             | Vite                                   |

---

## Self-Hosting

**No support is provided when you self-host Overlabels.** The following instructions are for development and testing.

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

**Required environment variables:**

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

**Optional (for external integrations):**

Only the OAuth-based integrations need app credentials. Ko-fi, Buy Me a Coffee, and Throne authenticate
per-user with a token or signature, so they need no environment configuration at all.

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

Your `APP_URL` must be publicly reachable for Twitch EventSub webhooks to deliver.
For local development, use [ngrok](https://ngrok.com) or a similar tunnel.

---

## Philosophy

Overlabels is built on one assumption: streamers who can write a `<div>` should not be locked out of good
overlays by proprietary tools.

- The triple-bracket syntax exists because simplicity is different from limitation. 
- The Controls system exists because overlays should respond to a streamer's intent, not just Twitch's event stream.
- External integrations exist because a streamer's ecosystem extends beyond Twitch.
- The copying system exists because good design compounds. Every overlay that exists is a starting point for the next 
  one.
- Overlabels overlays are free forever, for anyone, anytime. No paywalls. No tiers. Loose limits.

## Limitations of the Overlabels service
- You can create up to 1000 overlays per account.
- Each overlay can have up to 50 Controls.
- We do not offer asset hosting.
- Any script, iframe, embed is stripped from your overlay templates before saving.

## Limitations of the (NEW) Android GPS location app
> [!NOTE]
> The Android GPS Location app is still in active development and not ready for release just yet. 
- You can only use the app to display a single location.
- You can only send GPS location data to Overlabels every 5–60 seconds (user setting).

### If everything is free, how do you keep Overlabels sustainable?
Good question. First and foremost: Overlabels' footprint is actually rather small. The whole backend
is hosted on a few instances and the total hosting costs are negligible. In fact, the monthly fee
for my PhpStorm licence is more expensive than the cost of hosting Overlabels itself.

### But that still doesn't explain how you plan on making Overlabels sustainable?
Don't worry. I have plans ❤. For now, Overlabels is small and easy to maintain.

Of course you're free to send me a [Ko-fi](https://ko-fi.com/jasperfromoverlabels) tip if you like what I do!
Be sure to mention this README somewhere in your tip so I can link your support back to Overlabels.

---

## License

Overlabels is licensed under the **GNU Affero General Public License, version 3 or later**
(`AGPL-3.0-or-later`), as of August 9th, 2026. The full text is in [LICENSE](LICENSE).

Before that date this repository carried no licence at all. The `composer.json` metadata declared
`"license": "MIT"`, but that line arrived untouched in the initial Laravel scaffold commit
(`783b81fc`) as part of the starter kit's default metadata, and was never a deliberate choice. No
`LICENSE` file was ever published in this repository granting MIT terms. The AGPL applies going
forward from the date above; this is a forward-only relicense and no history has been rewritten.

What this means in practice:

- You can read, run, modify and share the source code freely.
- If you **self-host a modified version** and other people can reach it over a network, section 13
  of the AGPL requires you to offer those users the source of your modified version. Running an
  unmodified copy for yourself carries no such obligation.
- This governs the **Overlabels source code**. It does not govern the overlay templates, kits and
  controls you create with Overlabels - those are yours, and the copying rules for them are
  described under [Copying](#copying).

---

## Contributing

If you have questions, ideas, or improvements, open an issue or submit a pull request.
Overlabels grows best when people build on top of it.

Contributions are accepted under the same AGPL-3.0-or-later terms as the rest of the project.

~ JasperDiscovers
