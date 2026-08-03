# CHANGELOG AUGUST 2026

## August 3rd, 2026 - ui(events): the events feed's empty state now offers the way out instead of describing it

"No events match your filters. Try widening the time range or clearing search." Two problems: the advice was static, so it suggested clearing a search you had not typed and widening a range already set to All Time; and it described an action rather than offering one, while the filter panel it refers to is collapsible, so the search box was often not even on screen.

The empty state now says which of your filters is actually responsible, quotes the search term back at you, and makes clearing it a button.

- **Three branches, each one true.** A search is named and clearable; a narrowed time range is called out on its own; anything else falls back to the plain message. No sentence mentions a filter that is not set.
- **`EmptyState` grew a default slot**, so callers that need markup in the copy can pass it while `message` keeps working. The slot falls back to `message`, and every existing caller passes a plain string or the named `action` slot, so nothing else changed.
- **Clearing cancels the pending debounce** before it applies, so a keystroke still in flight cannot land after the clear and re-filter the list behind you.
- **All three feeds got it**, since the same sentence was copy-pasted across the token-authed feed, `/dashboard/recents` and `/dashboard/events`.

## August 3rd, 2026 - ui(events): the list-feed card on Recent Activity is collapsed by default

"Send these events to a list" occupied most of the first screen of Recent Activity: a title, an explanatory paragraph, a status block, a three-column configuration grid, an event-type fieldset and a save button, all above the events you came to look at. Since the hash-authed feed route landed, mirroring events into a list is a cool thing to have rather than something you need on the way in, and it was pushing the actual point of the page below the fold.

It is now a disclosure. Collapsed you get the title and a chevron; expanded, everything it had before, unchanged.

- **The heading is the toggle** - `<h3>` wrapping a `<button aria-expanded>`, which is the standard disclosure shape, so it keeps its heading semantics and announces its own state instead of being a div that happens to respond to clicks.
- **One thing survives the collapse: whether a feed is actually running.** The old card deliberately kept "which lists are receiving events" always visible, and hiding that outright would mean a live feed with no sign of itself anywhere. Collapsed, a green dot and "2 lists receiving" sit next to the title - but only when something is on. If you have no feeds, which is the case for everyone this change is for, it really is just the title and the chevron.

## August 3rd, 2026 - fix(events): searching "poll" found nothing, searching "po" found polls

Typing `Po` in the Recent Activity search returned Poll started, Poll updated and Poll ended. Typing `Poll` returned nothing. Being more specific made the results worse, which is the sort of thing that makes you distrust a search box entirely.

The search only ever looked at the stored payload - `event_data::text` for Twitch, `normalized_payload::text` for external services. A poll payload does not contain the word "poll" anywhere; the word lives in the event type (`channel.poll.end`) and in the label the feed renders in the browser, neither of which was being searched. The `Po` results were a coincidence: poll payloads carry a `channel_points_voting` key, and "po" is a substring of "points". Nothing about polls was ever actually matching.

- **The event type is searched alongside the payload now.** That is where the words people actually type live - "poll", "raid", "cheer", "follow", "donation". Both event tables get it, so it works the same either side of the union.
- **The two conditions are grouped, and that matters more than it looks.** An ungrouped `orWhere` would have escaped the surrounding `user_id` scope and the GPS exclusion, turning a search into a way to read every user's events. The same `applyFilters()` also backs the new bulk delete, so the identical mistake would have deleted other people's rows. There is a test on each path that fails if the grouping is ever removed.
- **Still not searchable: the multi-word labels.** The feed renders "Poll updated" client-side while the server's catalogue calls the same event "Poll Progress", so there is no single string to match against. Searching "poll" finds it; searching "poll updated" does not. Worth fixing by making one of those the source of truth rather than by teaching the query about both.

## August 3rd, 2026 - fix(events): the recents search box was eating characters

Typing in the search filter on Recent Activity felt like it was fighting you. Pause for half a second reaching for Shift and the search would fire early; keep typing while it loaded and the letters you typed would vanish a beat later. It read as "the input is disabled while loading and skips keys", but the field was never disabled and never missed a keystroke. It was throwing them away afterwards.

`recents.vue` kept a local copy of the filters and watched the server's copy to stay in sync, replacing the whole object on every response. The search input is bound to that object. So every response wrote the server's `search` value back into the box you were still typing in - and a response can only ever carry what you asked for one round trip ago. Type `test`, pause, request goes out; type `F`; the response for `test` lands and the box snaps back to `test`. The `F` is gone. The slower the response, the more characters it swallows, which is why it looked like loading was the thing blocking input.

- **The watcher now ignores its own echo.** We remember the term we last dispatched, and when a response comes back carrying exactly that, we leave the box alone - local state is by definition at least as current. A value we did not ask for is real news (back/forward, someone else's link) and is still adopted. The dropdowns sync unconditionally, since you cannot be mid-edit in a `<select>`.
- **The debounce went from 300ms to 500ms.** 300 is tuned for a search box that is the whole page. This one filters a table you are reading, and reaching for a modifier key routinely takes longer than that.
- **Search stopped littering browser history.** The filter visit was pushing an entry per keystroke batch, so leaving the page meant pressing back through `t`, `te`, `tes`. It replaces now.
- **A filter change only fetches the feed.** It was re-sending the template list, the facet counts and the user's lists on every keystroke. Those cannot change from a filter, so the visit asks for `recentEvents` and `filters`, and the controller defers the other three behind closures so their queries do not run either. Making the response smaller also shrinks the window the bug above lived in, which is why it was worth doing in the same pass rather than later.

## August 3rd, 2026 - feat(events): select and delete rows from the recent-events feed

Every integration has a test button, and every test event lands in your feed and stays there. Fire a few Twitch CLI events while wiring up an alert and you are left with `testFromUser raid 56171 viewers` sitting in your history forever, next to the real raids.

The obvious fix was to detect test events and hide them, and that turns out to be a trap. The donor name is the only thing the payloads have in common, and it is different for every service: Twitch CLI sends `testFromUser`, Ko-fi sends `Jo Example`, Throne sends `marie_123`, StreamLabs sends `Kevin`, Fourthwall sends `supporter username`, Buy Me a Coffee sends `John`, and StreamElements picks a fresh random name on every payload. Filtering on any of those means the day a real viewer called Kevin tips you, their donation quietly vanishes. There is no name-based rule that is safe.

Test mode looked like the way out, since it already exists per integration and the webhook controller already branches on it - but it does not mean what the name suggests. It only tells the system to stop rejecting repeated UUIDs, so the *first* test event you fire lands identically whether test mode is on or off. Flagging on it would have missed the single test that most people fire, which is exactly the one that causes the clutter.

So no detection. The feed just lets you select rows and delete them.

- **Selection keys are `source:id`, not `id`.** The feed is a `UNION ALL` over `twitch_events` and `external_events`, whose ids both start at 1 and collide freely. A flat id list would have deleted a stranger's Ko-fi tip because it happened to share a number with the follow you picked. A test pins this down specifically.
- **The filter bar is the bulk selector.** Alongside per-row checkboxes and select-all-on-page there is "select all N matching these filters", which re-derives the set server-side from the same `normalizeFilters()` the feed renders with. Filter to StreamLabs, search "Kevin", delete the 47 that match. It sidesteps the identification problem entirely by letting you define what junk means per cleanup, rather than shipping a rule that has to hold for seven services forever.
- **Deleting a row takes its hidden rows with it.** Gift-sub bombs fold N recipient events under the gifter, and a resub hides the bare `channel.subscribe` that Twitch fires alongside it. Those rows exist but are not rendered, so deleting only what you can see would have left them behind to reappear ungrouped on the next load, looking like the delete had failed.
- **`user_id` scoping is the authorization boundary, and it is load-bearing twice over.** `twitch_events.user_id` is nullable for events from broadcasters we do not know, so a delete scoped by id alone would reach rows belonging to nobody. GPS rows are excluded on the way out for the same reason they are excluded on the way in: they never appear in this feed, so they can never be picked from it, spoofed source or not.
- **No delete on the token-authed `/events/feed`.** It shares the same table component, behind a prop the recents page passes and the feed does not. Overlay tokens live in an OBS browser source URL and are write-capable for exactly two actions today; a destructive third is a worse trade than the convenience is worth.
- **Nothing is rolled back.** Deleting a donation event does not decrement `donations_received`, because those controls are running counters rather than a projection of the event tables. The confirm copy says so, and points at nothing, because the per-integration seed actions already exist for that.
- **Deleting while live is allowed.** A lock would not have protected the numbers anyway: session stats are a time-window query computed at read time, so an event deleted thirty seconds after the stream ends moves them exactly as much as one deleted mid-stream. It would only have postponed the same mutation while blocking the case that actually needs it, which is clearing follow-bot spam in the middle of the raid. The confirm says the stats will move and gets out of the way.

## August 1st, 2026 - fix(auth): logging out landed on a 404 instead of the homepage

Account > Log out did log you out, then dropped you on a 404 at `/logout`. Same day, same root cause as the dialog fix below it: `/` is a plain Blade view, and Inertia has no idea what to do with one.

Yesterday's backstop navigates to the URL of the visit that is in flight, because the `httpException` payload is typed as status, data and headers with no URL on it. For a link click those two URLs are the same, which is every case that was tested. Logout is the one flow where they diverge: the visit starts at `POST /logout`, the server redirects to `/`, the XHR follows that redirect itself, and Inertia gets a full HTML document back. The backstop then fired and sent the browser to the URL it had recorded - `/logout` - as a GET. There is no `GET /logout`, so `Route::fallback` served the 404. Logged out, stranded, one route short of home.

- **`Inertia::location()` instead of `redirect('/')`.** It answers the XHR with 409 + `X-Inertia-Location`, which Inertia turns into a real navigation natively, never reaching the backstop. This is already how `RedirectIfUnauthenticated` and the 419 handler send an Inertia request to a non-Inertia page - it is the existing pattern, not a new one. A session teardown wants a fresh document anyway.
- **The backstop now only resumes GET visits.** A non-GET visit that ends on plain HTML got there by a redirect, so the recorded URL is guaranteed to be the wrong target - and re-issuing a write as a GET is a bad outcome even when a route does exist to receive it. One-line guard, closes the class rather than the instance.
- **A duplicate `POST /logout` in `web.php` was deleted.** It had been dead the whole time: `RouteCollection` keys on method+URI, `auth.php` is required further down the same file, and the later registration wins. `route:list` only ever showed one. Worth knowing before debugging a route that looks right and never runs.
- **Logout had no tests at all**, which is why this shipped twice in two forms. It has three now, and the one that matters was confirmed to fail against the old controller.
- **`BotFollowageTest` was failing on the calendar, not on the code.** It asserted "2 years, 2 months, 30 days" off a `now()->subMonths(3)` fixture. `HumanDuration` walks calendar units, so subtracting 3 months from a high day-of-month clamps into a short month and the answer shifts by a whole unit - meaning the test passed or failed depending on the day the suite ran. It now freezes the clock in `beforeEach` exactly like `BotAccountageTest` already did, anchored to the 15th because no month is short enough to overflow it. With `now()` frozen the span is exact, so both duration assertions moved from `toContain` fragments to the full sentence chat actually sees.

## August 1st, 2026 - feat(updates): the updates feed is public

`https://overlabels.com/updates/overlabels-development-highlights-july-2026` bounced anyone who was not logged in. That is the wrong shape for what these posts are: announcements, written to be linked from Twitch, from Discord, from anywhere someone might first hear about the project. A link that only works if you already have an account is not a link.

Both routes moved out of the `auth.redirect` group and now sit with the other public routes, next to `/help`. Nothing else about them changed.

- **No new visibility logic was needed.** `UpdateController` already queried through the `published()` scope on both actions, which is `published_at <= now()`, so a post dated into the future stays a 404 for guests exactly as it did for logged-in visitors. Moving the route did not open a hole, because the gate was never the middleware.
- **`updates.*` joined the `guest` Ziggy group.** The index page calls `route('updates.index')` when you type in the search box or pick a tag, and the guest group is a whitelist, so without this the filters would have thrown for the exact visitors the change is for. Worth remembering for the next public page: making a route public is two edits, not one.
- **Guests get an Updates item in the sidebar.** Logged-out visitors see their own nav list, so the page was reachable but not findable. It now sits under Learn, right below Help.
- **A test pins all of it down**, including the future-dated 404, because "this page is public" is the kind of property that quietly stops being true.
