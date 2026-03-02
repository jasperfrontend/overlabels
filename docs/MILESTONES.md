# Overlabels — Milestones

A forward-looking roadmap. Each milestone represents a coherent, shippable layer of the product.
Completed milestones are kept here as a record of intent vs. reality.

---

## ✅ Completed Milestones

<details>
<summary><strong>Milestone 1 — "This shit actually works"</strong></summary>

- HTML/CSS/HEAD overlay editor with CodeMirror
- Template tag system (`[[[tag_name]]]`)
- Controls system
- EventSub pipeline
- Access token system
- Fork system
- Admin panel
</details>

<details>
<summary><strong>Milestone 1.5 — Separate In/Out Alert Transitions</strong></summary>

- `transition_in` / `transition_out`
- Migration & validation updates
- UI split for enter/exit animations
- Hand-rolled CSS transitions
</details>

<details>
<summary><strong>Milestone 2 — External Systems: Foundations</strong></summary>

- `ExternalServiceDriver` interface + `ExternalServiceRegistry` — plug-in architecture for any future service
- Generic webhook receiver (`POST /api/webhooks/{service}/{token}`) routes to the right driver by source
- Per-user credential vault (encrypted storage via `Crypt::encryptString`)
- `NormalizedExternalEvent` DTO — downstream features (alerts, controls) don't care where the event came from
- `ExternalEvent` append-only store with global dedup on `(service, message_id)`
- `ExternalControlService` + `ExternalAlertService` as the two action layers
- No actual integrations shipped in this milestone — only the rails they run on
</details>

<details>
<summary><strong>Milestone 3 — Ko-fi Integration</strong></summary>

- Ko-fi webhook receiver using the MS2 architecture (`KofiServiceDriver`)
- Normalised Ko-fi events: donation, subscription, shop order, commission
- Ko-fi alert mappings in Alerts Builder — per event type, with duration + enter/exit animations
- Ko-fi controls addable to static overlay templates via ControlFormModal presets (6 controls: received count, latest donor, amount, message, currency, total)
- `event.*` template tags available in Ko-fi alert templates (`event.from_name`, `event.amount`, `event.currency`, etc.) + `event.source` for multi-service template reuse
- Test mode toggle — bypasses dedup so the same payload can be fired repeatedly without a new transaction ID
- Ko-fi events appear in the dashboard activity feed alongside Twitch events, and are replayable on overlays
- Verified end-to-end: Ko-fi donation → Overlabels event → alert fires in OBS
</details>

---

## Milestone 4 — Full Responsive Dashboard
> *The dashboard should work on any screen, top to bottom, left to right.*

- Every page in the dashboard is usable on mobile and tablet
- Sidebar navigation collapses correctly on small screens
- Overlay editor is usable on a laptop without a second monitor
- Tables degrade gracefully (priority columns, horizontal scroll where unavoidable)
- No new features — this milestone is purely polish and layout

---

## Milestone 4.5 — Security Audit & Dead Code Removal
> *No new features ship until this is done. The goal is to be able to say with confidence:*
> *"this codebase is as safe as we can reasonably make it."*
>
> *The one known trade-off that is explicitly accepted:* hash-based public overlay URLs are
> *security-through-obscurity by design. A leaked hash gives read access to an overlay,*
> *but no write access — mutating state still requires a valid auth session and the correct*
> *Twitch ID. Streamers are warned about this. It stays.*

### Codebase cleanup
- Audit all routes — remove or gate anything that shouldn't be publicly reachable
- Remove dead routes, unused controllers, unused models, unused migrations
- Remove unused npm packages and composer packages
- Drop or repurpose any database tables that are no longer referenced
- Delete dead Vue components, composables, and utility files
- Confirm every queue job, event, and listener is actually wired up and used

### Security review
- Audit every controller for missing auth middleware (especially API routes)
- Confirm all user-owned resources check ownership before read/write/delete (no IDOR)
- Confirm no raw user input is ever passed to `exec`, `shell_exec`, `system`, or eval-equivalent
- Review all file upload paths (if any) for extension and MIME validation
- Confirm CSRF protection is in place on all state-mutating web routes
- Confirm webhook endpoints that skip CSRF (intentionally) have their own signature/token verification
- Confirm encrypted credentials (`Crypt::encryptString`) are never logged or serialised into responses
- Review rate limiting — ensure public-facing endpoints (overlay render, webhooks) are rate-limited
- Confirm no sensitive values leak into JavaScript via Inertia shared props or `window.*`
- Review admin panel access — confirm `EnsureAdminRole` middleware is applied everywhere and returns 404 (not 403) to non-admins

### Known accepted risks (document, don't fix)
- **Hash-based overlay URLs** (`/overlay/{hash}`) — the hash is a client-side decryption key, never parsed or validated on the backend. The alternative — backend-rendered templates gated behind a session — would couple the rendering pipeline to auth state, add server overhead on every overlay frame, and break OBS browser sources entirely (no session support). Frontend stays dumb, backend stays detached. A leaked hash gives read access to static overlay content; it grants no write access and no ability to mutate state, which still requires a valid auth session. Streamers are warned. This is the right trade-off.
- OBS browser sources cannot hold auth sessions — URL-fragment token delivery is the only viable mechanism for read-only overlay access and that is fine

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

*Last updated: March 2026*
