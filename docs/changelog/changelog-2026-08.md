# CHANGELOG AUGUST 2026

## August 1st, 2026 - fix(auth): logging out landed on a 404 instead of the homepage

Account > Log out did log you out, then dropped you on a 404 at `/logout`. Same day, same root cause as the dialog fix below it: `/` is a plain Blade view, and Inertia has no idea what to do with one.

Yesterday's backstop navigates to the URL of the visit that is in flight, because the `httpException` payload is typed as status, data and headers with no URL on it. For a link click those two URLs are the same, which is every case that was tested. Logout is the one flow where they diverge: the visit starts at `POST /logout`, the server redirects to `/`, the XHR follows that redirect itself, and Inertia gets a full HTML document back. The backstop then fired and sent the browser to the URL it had recorded - `/logout` - as a GET. There is no `GET /logout`, so `Route::fallback` served the 404. Logged out, stranded, one route short of home.

- **`Inertia::location()` instead of `redirect('/')`.** It answers the XHR with 409 + `X-Inertia-Location`, which Inertia turns into a real navigation natively, never reaching the backstop. This is already how `RedirectIfUnauthenticated` and the 419 handler send an Inertia request to a non-Inertia page - it is the existing pattern, not a new one. A session teardown wants a fresh document anyway.
- **The backstop now only resumes GET visits.** A non-GET visit that ends on plain HTML got there by a redirect, so the recorded URL is guaranteed to be the wrong target - and re-issuing a write as a GET is a bad outcome even when a route does exist to receive it. One-line guard, closes the class rather than the instance.
- **A duplicate `POST /logout` in `web.php` was deleted.** It had been dead the whole time: `RouteCollection` keys on method+URI, `auth.php` is required further down the same file, and the later registration wins. `route:list` only ever showed one. Worth knowing before debugging a route that looks right and never runs.
- **Logout had no tests at all**, which is why this shipped twice in two forms. It has three now, and the one that matters was confirmed to fail against the old controller.

## August 1st, 2026 - feat(updates): the updates feed is public

`https://overlabels.com/updates/overlabels-development-highlights-july-2026` bounced anyone who was not logged in. That is the wrong shape for what these posts are: announcements, written to be linked from Twitch, from Discord, from anywhere someone might first hear about the project. A link that only works if you already have an account is not a link.

Both routes moved out of the `auth.redirect` group and now sit with the other public routes, next to `/help`. Nothing else about them changed.

- **No new visibility logic was needed.** `UpdateController` already queried through the `published()` scope on both actions, which is `published_at <= now()`, so a post dated into the future stays a 404 for guests exactly as it did for logged-in visitors. Moving the route did not open a hole, because the gate was never the middleware.
- **`updates.*` joined the `guest` Ziggy group.** The index page calls `route('updates.index')` when you type in the search box or pick a tag, and the guest group is a whitelist, so without this the filters would have thrown for the exact visitors the change is for. Worth remembering for the next public page: making a route public is two edits, not one.
- **Guests get an Updates item in the sidebar.** Logged-out visitors see their own nav list, so the page was reachable but not findable. It now sits under Learn, right below Help.
- **A test pins all of it down**, including the future-dated 404, because "this page is public" is the kind of property that quietly stops being true.
