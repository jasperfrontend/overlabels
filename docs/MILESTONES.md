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

<details>
<summary><strong>Milestone 4 — Full Responsive Dashboard</strong></summary>

- Every page in the dashboard is usable on mobile and tablet
- Sidebar navigation collapses correctly on small screens
- Overlay editor is usable on a laptop without a second monitor
- Tables degrade gracefully (priority columns, horizontal scroll where unavoidable)
- No new features — this milestone was purely polish and layout
</details>

<details>
<summary><strong>Milestone 4.5 — Security Audit & Dead Code Removal</strong></summary>

> *No new features ship until this is done. The goal is to be able to say with confidence:*
> *"this codebase is as safe as we can reasonably make it."*
>
> *The one known trade-off that is explicitly accepted:* hash-based public overlay URLs are
> *security-through-obscurity by design. A leaked hash gives read access to an overlay,*
> *but no write access — mutating state still requires a valid auth session and the correct*
> *Twitch ID. Streamers are warned about this. It stays.*

</details>

<details>
<summary><strong>Milestone 5d — Output Formatting (Pipe System)</strong></summary>

- Pipe syntax for template tags: `[[[tag|formatter]]]` or `[[[tag|formatter:args]]]`
- 8 built-in formatters: `round`, `number`, `currency`, `duration`, `date`, `uppercase`, `lowercase`
- Duration patterns with overflow: `hh:mm:ss`, `mm:ss`, `dd:hh:mm:ss` etc.
- Global locale setting (Settings > Appearance) drives default formatting for numbers, currencies, and dates
- Locale-to-currency mapping so `|currency` without args uses EUR for Dutch, GBP for British, etc.
- Pure client-side implementation using native `Intl` APIs - zero dependencies
- Full documentation at `/help/formatting` with example tables, locale comparisons, and quick reference
- PHP `extractTemplateTags()` updated to strip pipe expressions for the tag allowlist
</details>

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

## Milestone 6 — Community (Rebuilt Properly)
> *The original community feature was removed because it was bad. This time, do it right.*

- Public overlay gallery with search, filtering by type and tags
- User profiles showing public overlays
- Fork counts, view counts, featured overlays
- No gamification, no badges, no points. Just useful discovery.


*Last updated: April 2026*
