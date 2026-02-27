# Overlabels — Milestones

A forward-looking roadmap. Each milestone represents a coherent, shippable layer of the product.
Completed milestones are kept here as a record of intent vs. reality.

---

## ✅ Milestone 1 — "This shit actually works"
> *Early access launch. Core overlay system is feature-complete for a v0.x release.*

- HTML/CSS/HEAD overlay editor with CodeMirror
- Template tag system (`[[[tag_name]]]`) with live Twitch data
- Controls system — per-overlay mutable values (`[[[c:key]]]`) with counter, timer, datetime, text, number types
- EventSub webhook pipeline for real-time alerts (follow, sub, raid, etc.)
- Access token system for secure OBS embedding
- Fork system with control import wizard
- Admin panel with user management, impersonation, and audit log
- No sugar, no animations, no confetti. Overlays do what you build them to do.

---

## ✅ Milestone 1.5 — Separate In/Out Alert Transitions
> *Alerts should be able to enter one way and leave another. A hard prerequisite before accepting*
> *external event sources — every new source will want its own transition feel.*

- Replace the single `transition_type` column on `event_template_mappings` with `transition_in` and `transition_out`
- Migration with a default of `fade` for both new columns; drop `transition_type`
- Backend validation and broadcast updated to send `transition_in` / `transition_out`
- `OverlayRenderer.vue` uses `:css="false"` + GSAP or pure CSS `@keyframes` — or a dynamic computed transition name for enter vs. leave
- UI: two separate selects (Enter animation / Exit animation) in the alert mapping form
- Available options: `fade`, `scale`, `slide-top`, `slide-bottom`, `slide-left`, `slide-right`, `none`
- No Animate.css — all transitions are hand-rolled in the overlay shell CSS
- Existing `none` behaviour (instant cut) must still work correctly for both enter and exit independently

---

## Milestone 2 — External Systems: Foundations
> *Build the integration layer BEFORE integrating anything. The goal of this milestone is a clean,*
> *extensible architecture that makes MS3, MS4+, and any future system a "plug in, not rebuild" job.*

- Define a standard `ExternalEvent` contract / interface that all systems speak
- Build a generic webhook receiver that routes to the right handler by source
- Per-user credential vault (encrypted storage for API keys, OAuth tokens per service)
- Abstract "event normaliser" so downstream features (alerts, controls) don't care where the event came from
- No actual integrations ship in this milestone — only the rails they'll run on

---

## Milestone 3 — Ko-fi Integration
> *First real integration, built entirely on the MS2 foundation.*

- Ko-fi webhook receiver using the MS2 architecture
- Normalised Ko-fi events: donation, subscription, shop order
- Ko-fi event tags available in overlay templates
- Verified end-to-end: Ko-fi donation → Overlabels event → alert fires in OBS

---

## Milestone 4 — Full Responsive Dashboard
> *The dashboard should work on any screen, top to bottom, left to right.*

- Every page in the dashboard is usable on mobile and tablet
- Sidebar navigation collapses correctly on small screens
- Overlay editor is usable on a laptop without a second monitor
- Tables degrade gracefully (priority columns, horizontal scroll where unavoidable)
- No new features — this milestone is purely polish and layout

---

## Milestone 5a — Twitch Bot: Foundation
> *MS5 is too large for one milestone. This is the framework layer.*

- Bot connection layer (Twitch IRC / EventSub Chat)
- Per-user bot auth (users connect their own bot account or use a shared one)
- Command registration system: declare a command, its arguments, and its handler
- Permission model: who can trigger which commands (broadcaster, mods, everyone)
- Groundwork is generic enough that adding new command types later is additive, not invasive

## Milestone 5b — Twitch Bot: Read Commands
> *Commands that surface information from Overlabels into chat.*

- `!control <key>` — print current value of a control to chat
- `!overlay` — print the overlay status (active/inactive)
- Configurable response templates per command

## Milestone 5c — Twitch Bot: Write Commands
> *Commands that mutate state. This is where it gets interesting.*

- `!set <key> <value>` — set a control value
- `!increment <key>` / `!decrement <key>` — counter manipulation
- `!reset <key>` — reset to default
- Broadcaster/mod-only by default, configurable per command
- Changes fire the same `ControlValueUpdated` broadcast the overlay already listens to

---

## Milestone 6 — Community (Rebuilt Properly)
> *The original community feature was removed because it was bad. This time, do it right.*

- Public overlay gallery with search, filtering by type and tags
- User profiles showing public overlays
- Fork counts, view counts, featured overlays
- No gamification, no badges, no points. Just useful discovery.

---

## Milestone 7 — Profit 💸
> *lol*

---

## Sidequests
> *Optional features that don't belong to a specific milestone. Too good to forget, too niche to prioritise.*

### Sidequest: YouTube Quota Dashboard
YouTube's Live Chat API is polling-only (no webhooks, no websockets) and costs 5 quota units per call against a 10,000 unit/day limit — shared across the entire app unless users bring their own OAuth credentials. A "YouTube bot" is just an OAuth'd account hammering this same endpoint; there is no separate protocol.

The idea:
- Use the `stream.online` EventSub event to start a polling job and `stream.offline` to kill it
- Users set their own poll interval (5s / 10s / 30s) with a live preview: "at this rate you get ~X hours of coverage today"
- A dashboard widget shows remaining quota units, current burn rate, and estimated time until the midnight Pacific reset
- Users with high coverage needs register their own YouTube OAuth app (like Twitch's client ID/secret) to get their own quota bucket

Captures: superchats (with amount, currency, message), memberships (new + gifted).
Notably: nobody surfaces API quota to users in a meaningful way — this would feel crafted.

*Last updated: February 2026*
