# CHANGELOG

## March 1st, 2026 — MS2 + MS3: External Integrations & Ko-fi

### External integration rails (MS2)
- **New: external integration infrastructure.** A new `POST /api/webhooks/{service}/{webhook_token}` route handles incoming payloads from any supported third-party service. The pipeline: verify → deduplicate → normalize → update Controls → fire alert — exactly mirrors the Twitch EventSub pipeline. Adding a new service in the future means writing one driver class.
- **New: service-managed Controls.** Controls now support a `source` / `source_managed` flag. When a service (e.g. Ko-fi) writes to a control, the overlay receives the update over the existing `control.updated` broadcast — no renderer changes needed. Service-managed controls return 403 to any manual edit attempt from the dashboard.
- **New: user-scoped Controls.** `overlay_template_id` is now nullable. Controls with `NULL` template ID are user-global — they appear in every overlay belonging to that user. Ko-fi donation counters, latest donor name, etc. are all user-scoped.
- **New: namespaced control keys.** Service controls use a namespaced tag syntax: `[[[c:kofi:kofis_received]]]`. The broadcast key is `kofi:kofis_received`; the data map key is `c:kofi:kofis_received`. The colon was already in the tag extraction regex — no parser changes needed. CSS `[[[c:kofi:...]]]` works the same as any other tag replacement.
- **New: Settings → Integrations page.** `/settings/integrations` lists all supported services with connected/disconnected status. Additional services show "Coming soon".
- **New: `ExternalServiceDriver` interface + `ExternalServiceRegistry`.** Clean extensibility pattern for adding Throne, Patreon, Fourthwall, etc. in future milestones.

### Ko-fi integration (MS3)
- **New: Ko-fi connected.** Connect Ko-fi from Settings → Integrations → Ko-fi. Paste your verification token, copy your webhook URL into Ko-fi's API settings, and donation/subscription/shop order events flow directly into your overlays.
- **Automatic Ko-fi controls provisioned on connect.** Six controls are created automatically: `kofis_received` (counter), `latest_donor_name`, `latest_donation_amount`, `latest_donation_message`, `latest_donation_currency`, `total_received`. Use them in any template as `[[[c:kofi:kofis_received]]]` etc.
- **Ko-fi alerts.** Map Ko-fi donation/subscription/shop order events to any alert template from the Integrations settings page. The alert fires on the same broadcast channel as Twitch events — existing alert rendering requires no changes.
- **Disconnect Ko-fi** removes the integration and all auto-provisioned controls.
- **28 new tests** cover: KofiServiceDriver (unit), webhook pipeline (404/403/200/409/dedup/control updates), and source_managed guard (feature).

## February 28th, 2026
- **Fixed: control values in CSS now update live.** Using `[[[c:key]]]` tags inside overlay CSS (e.g. to drive `font-size` or `background-color` from a control) now reacts instantly when values change, just like HTML does. Previously the style tag was only injected once on load and never updated.
- **Fixed: channel points reward tags now use dot notation.** `[[[event.reward.title]]]`, `[[[event.reward.prompt]]]`, and `[[[event.reward.cost]]]` now correctly reflect the nested payload shape. The old underscore variants (`event.reward_title` etc.) were wrong.
- **Controls table now shows sort order and current value.** The controls list in the template editor and the controls manager both show the `sort_order` column and the current stored value at a glance.

## February 27th, 2026
- **Onboarding now captures users at exactly the right moment.** When you generate your token during setup, the wizard immediately constructs your complete OBS Browser Source URL — token already embedded — with a one-click copy button. No more explaining what a URL hash is or asking users to manually paste a token into the right spot.
- **New interstitial warning step before token generation.** A dedicated screen now appears between the setup summary and the token reveal, with four amber-coloured callouts: your token is shown exactly once, you'll get a ready-to-use OBS URL, alerts won't fire without the overlay in OBS, and never share it on stream. A big "I read all of the above" button gets you through.
- **OBS setup dialog on the overlay detail page.** The ⓘ button next to the OBS URL is now always visible (not just for private overlays) and opens a modal with step-by-step OBS Browser Source setup instructions, a reminder that the `#YOUR_TOKEN_HERE` placeholder must be replaced, where to find a lost token, and a security warning.
- **Collapsible source viewer on the overlay detail page.** HTML, CSS, and HEAD source is now hidden behind a "View source / Hide source" toggle so the page isn't overwhelming by default.
- **Kebab menus on the overlay detail and edit pages.** Secondary actions (Preview, Fork, Delete) are now tucked into a `⋮` dropdown. The primary action (Edit / Save) stays as a standalone button to the left.
- **Admin: onboarding preview tool.** Admins can now trigger a fake onboarding run from the admin dashboard without needing a fresh account. The session flag is consumed on first render so it cleans itself up automatically.
- **Fixed: global Pusher socket was running on every page.** The main app was unconditionally creating a Pusher WebSocket connection on boot, causing a persistent reconnection loop and console spam on every page. Removed — the overlay's own connection is unaffected.
- **Fixed: shared TypeScript types.** Fifteen files each had their own local `interface Template`, causing TS2719 type incompatibility errors. Consolidated into shared `OverlayTemplate` and `AdminTemplate` types in `types/index.d.ts`.
- **Alert transitions are now actually implemented.** Fade, scale, and four directional slide variants (↑ ↓ ← →) all work. Previously the transition type was stored and sent with the broadcast but no CSS existed to back it up, so every alert hard-cut regardless of what you'd configured. The `slide` legacy value falls back to fade gracefully.
- **Enter and exit animations are now independent.** You can configure a separate animation for when an alert appears and when it disappears — slide up from the bottom on entry, fade out on exit, for example. The Alerts Builder now shows two dropdowns per event with labels that reflect direction correctly for each context.

## v0.1.0 — Milestone 1: "This shit actually works" (February 2026)
Early access launch. See MILESTONES.md for the full scope of what this covers.

## February 22nd, 2026
- Added a new admin panel.
- Completely redid the homepage.
- Improved the og:image.
- Flipped the order of contents in this CHANGELOG.
## February 21st, 2026
- Implemented Controls.
  Users can now create single-purpose data stores as string, number, countup, countdown, datetime, or boolean. The implementation is portable, forkable, unique per overlay, straightforward to use, and on top of it all, the new Controls also work well with the comparison engine baked into Overlabels. This means you can now compare values you set and manipulate yourself without having to dive back into your code to make changes. [See how it works](https://overlabels.com/help/controls).
- Any HTML, CSS, or HEAD change to an overlay triggers a hard refresh of the overlay now. This removes any issues with cached data being left behind when embedding this overlay in OBS.
- The Dashboard and Template Details screen had a visual update which makes them more coherent and easier to understand.
- I ditched all Card layouts in favour of tables wherever it made sense. A list of stored overlays is tabular data after all, no matter how you look at it.
- I re-organised the main sidebar navigation, so it's easier to understand where you need to go. I also removed the menu items you don't really need and added those in a "Debug" menu in your profile link.
- I removed the whole community part of Overlabels because its implementation was awful, to say the least. I will work on a better implementation of the Community part of Overlabels, but this is honestly not a top priority right now. You can still view all public overlays on the "All overlays" page.
- The Overlabels Dashboard is still in no way, shape, or form responsive – but at least the Events view is now responsive. The new "Events view" offers a simple way for streamers to see an overview of the latest alerts and the option to replay them on your overlay. You can find this overview under "My activity" in the Overlabels Dashboard.
## February 17th, 2026
- Implemented a massive onboarding system for new users.
- Trying to fix the bug where alerts don't show more than once.
- Added a Twitch CLI test endpoints explanation page.
