# CHANGELOG

## February 27th, 2026
- **Onboarding now captures users at exactly the right moment.** When you generate your token during setup, the wizard immediately constructs your complete OBS Browser Source URL — token already embedded — with a one-click copy button. No more explaining what a URL hash is or asking users to manually paste a token into the right spot.
- **New interstitial warning step before token generation.** A dedicated screen now appears between the setup summary and the token reveal, with four amber-coloured callouts: your token is shown exactly once, you'll get a ready-to-use OBS URL, alerts won't fire without the overlay in OBS, and never share it on stream. A big "I read all of the above" button gets you through.
- **OBS setup dialog on the overlay detail page.** The ⓘ button next to the OBS URL is now always visible (not just for private overlays) and opens a modal with step-by-step OBS Browser Source setup instructions, a reminder that the `#YOUR_TOKEN_HERE` placeholder must be replaced, where to find a lost token, and a security warning.
- **Collapsible source viewer on the overlay detail page.** HTML, CSS, and HEAD source is now hidden behind a "View source / Hide source" toggle so the page isn't overwhelming by default.
- **Kebab menus on the overlay detail and edit pages.** Secondary actions (Preview, Fork, Delete) are now tucked into a `⋮` dropdown. The primary action (Edit / Save) stays as a standalone button to the left.
- **Admin: onboarding preview tool.** Admins can now trigger a fake onboarding run from the admin dashboard without needing a fresh account. The session flag is consumed on first render so it cleans itself up automatically.
- **Fixed: global Pusher socket was running on every page.** The main app was unconditionally creating a Pusher WebSocket connection on boot, causing a persistent reconnection loop and console spam on every page. Removed — the overlay's own connection is unaffected.
- **Fixed: shared TypeScript types.** Fifteen files each had their own local `interface Template`, causing TS2719 type incompatibility errors. Consolidated into shared `OverlayTemplate` and `AdminTemplate` types in `types/index.d.ts`.

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
