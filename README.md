# Welcome to Overlabels

Overlabels lets you create OBS overlays by writing HTML and CSS directly, rather than using drag-and-drop editors. This gives you complete control over your overlay design while keeping the workflow straightforward for anyone familiar with web development.

Overlabels is built around openness: overlays are free forever, forkable by design, and meant to grow into a shared ecosystem of good design and good code.

## Getting Started

1.  Create an Overlabels account
2.  Generate a secure token for authentication
3.  Create your static overlay (the persistent container)
4.  Create event alert templates for different Twitch events
5.  Assign alerts to events in the Alerts Builder
6.  Add your overlay URL to OBS

## How It Works

Overlabels uses a custom triple-bracket template syntax to inject Twitch data into your HTML. The syntax is intentionally distinctive: too unique to accidentally conflict with other code, simple enough to understand immediately, and portable across any HTML document.

There are two types of data available through the template syntax:

1.  **Static data** from the Twitch Helix API (follower counts, subscriber counts, stream category, etc.)
2.  **Event data** from the Twitch EventSub API (new followers, subscriptions, raids, etc.)

### Basic Template Example

```
<div class="stat-item">
    <span class="stat-number">[[[followers_total]]]</span>
    <span class="stat-label">Followers</span>
</div>
```

This renders your current follower count, for example: `1300 Followers`.

### Conditional Template Syntax

Overlabels includes a comparison engine for conditional rendering based on boolean, numerical, and string values:

```
[[[if:channel_language = en]]]
  <p>Welcome to our English stream!</p>
[[[elseif:channel_language = es]]]
  <p>¡Bienvenidos a nuestro stream en Español!</p>
[[[endif]]]
```

Any template tag that outputs a value can be used in conditionals. Both static overlays and event alerts support conditional syntax.

Read more about Conditional Template Syntax on the website:  
[https://overlabels.com/help](https://overlabels.com/help)

## Static Overlays

Static overlays are the persistent HTML pages that display your stream information and serve as containers for event alerts. They continuously show data like follower counts, current game, and other stream statistics.

### Example Static Overlay

[https://overlabels.com/overlay/tame-rolling-house-full-eagle/public](https://overlabels.com/overlay/tame-rolling-house-full-eagle/public)

The `/public` endpoint shows the template structure without real data. To display your actual Twitch data, generate a secure token and replace `/public` with `/#your_token_here`.

### Token Security

Tokens authenticate your overlay to fetch your Twitch data securely. They are encrypted on the frontend using Laravel's CSRF protection before any backend communication. The actual Twitch data queries are performed directly from the frontend via Axios, never through the Laravel backend.

**IMPORTANT:** Keep your token secret. Treat it like a password and never share it on stream or with anyone.

If you suspect your token has been compromised, revoke it immediately in the app, generate a new one, and update your OBS overlay URLs.

## Event Alerts

Event alerts are triggered overlays that appear when specific Twitch events occur (new followers, subscriptions, raids, etc.). These require a static overlay as their container. Without this container, event alerts have nowhere to render.

### Creating Event Alerts

1.  Create a new template in the webapp and select **Event Alert**
2.  Use event-specific template syntax for each event type
3.  Check the Event-based Template Tags documentation for complete syntax details:  
    [https://overlabels.com/help](https://overlabels.com/help)

### Assigning Alerts to Events

The Alerts Builder lets you:

*   Assign specific templates to different Twitch EventSub events
*   Set display duration for each alert
*   Choose transition effects (currently in development)

## Forking, Template Kits, and the Overlay Ecosystem

Overlabels is built around sharing.

Any **public** overlay or template can be forked by anyone. Forking creates your own editable copy that you can modify, extend, or remix without affecting the original.

Forking is simple:

*   Open any public overlay or template
*   Press the **Fork** button
*   Instantly get your own version to work with

Overlays are **public by default**, but you can choose to keep your work private at any time.

### Template Kits

Template Kits are collections of overlays and event alerts designed to work together as a cohesive system rather than isolated files. Template Kits can be forked just like individual overlays.

This enables a growing ecosystem where:

*   Designers share high-quality building blocks
*   Streamers remix instead of reinvent
*   Proven patterns spread naturally

There are no licensing tricks, no hidden paywalls, and no artificial limits.

**Overlabels overlays are free forever, for anyone, anytime.**

## Adding to OBS

Once you have created your templates and configured your alerts:

1.  Copy your static overlay URL, including the secure token
2.  Add it as a Browser Source in OBS
3.  Set the source dimensions to fullscreen (Ctrl+S in OBS)
4.  Your overlay will now display live data and show alerts when events occur

When an event triggers, such as a new follower:

*   The event alert template is displayed
*   The relevant data in your static overlay updates automatically

## Current Status

Overlabels is currently an MVP: functional and secure, with room for improvement. Features such as transition effects are still in development.

## Questions or Issues?

If you have questions, ideas, or want to improve things, feel free to open an issue or submit a pull request. Overlabels grows best when people build on top of it.
