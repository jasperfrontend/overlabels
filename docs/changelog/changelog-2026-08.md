# CHANGELOG AUGUST 2026

## August 1st, 2026 - feat(updates): the updates feed is public

`https://overlabels.com/updates/overlabels-development-highlights-july-2026` bounced anyone who was not logged in. That is the wrong shape for what these posts are: announcements, written to be linked from Twitch, from Discord, from anywhere someone might first hear about the project. A link that only works if you already have an account is not a link.

Both routes moved out of the `auth.redirect` group and now sit with the other public routes, next to `/help`. Nothing else about them changed.

- **No new visibility logic was needed.** `UpdateController` already queried through the `published()` scope on both actions, which is `published_at <= now()`, so a post dated into the future stays a 404 for guests exactly as it did for logged-in visitors. Moving the route did not open a hole, because the gate was never the middleware.
- **`updates.*` joined the `guest` Ziggy group.** The index page calls `route('updates.index')` when you type in the search box or pick a tag, and the guest group is a whitelist, so without this the filters would have thrown for the exact visitors the change is for. Worth remembering for the next public page: making a route public is two edits, not one.
- **Guests get an Updates item in the sidebar.** Logged-out visitors see their own nav list, so the page was reachable but not findable. It now sits under Learn, right below Help.
- **A test pins all of it down**, including the future-dated 404, because "this page is public" is the kind of property that quietly stops being true.
