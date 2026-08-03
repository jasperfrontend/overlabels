# CHANGELOG AUGUST 2026

## August 4th, 2026 - feat(reference): integration controls are generated from the drivers now, not remembered

The reference is a vault of hand-written markdown, which is the right call for Twitch tags: they change when Twitch changes, which is rarely and loudly. It is the wrong call for integration controls, which are defined in PHP and change whenever a driver does. Left to hand-maintenance, it had converged exactly where you would expect: 4 of 7 services documented, none of them completely, and every one of the four asserting that the shared donation schema covered "all four integrations" when seven drivers provision those same six keys.

Finding them was itself the argument. Ko-fi's page was filed as `ko-fi-auto-provisioned-controls` while its service key is `kofi`, so it did not turn up in a search for the thing it documents. Buy Me a Coffee, Throne and GPS had no page at all.

There is now an `integration-controls` category, emitted from `getAutoProvisionedControls()` on each registered driver. Eight pages: one per service, plus an index.

- **`php artisan help:build-integration-controls`** reads `ExternalServiceRegistry` and writes the markdown. Adding a service to the registry adds its reference pages, its sitemap URLs and its rows in `help-reference-index.json`, with no separate documentation step to forget.
- **The output is committed, not gitignored.** That way the diff appears in review when a driver changes, and - the part that made this cheap - every existing consumer keeps working untouched. The Blade pages, the sitemap, the JSON index and the Alt+R palette's Vite glob all already handle `.md` files. Zero consumers changed.
- **`--check` fails when the committed files no longer match the drivers**, and a test runs it. Verified the way these things have to be verified: renamed one Ko-fi control label, watched it fail and name `kofi.md`, put it back.
- **The shared six are separated from what each service adds**, because flattening them into one table is what makes someone think `latest_item_name` is portable. Ko-fi, StreamLabs, StreamElements and Fourthwall have exactly the six; Buy Me a Coffee adds one; Throne adds three. GPS shares none of them and is labelled as not using the schema at all rather than pretending to extend it.
- **`ExternalServiceRegistry::displayName()`** now owns the service-name map. It was a private method on `IntegrationController` and a second inline copy in `AdminUserController`; the generator needed a third, which is how you end up with "Streamlabs" and "StreamLabs" both being correct somewhere. IntegrationController delegates now. AdminUserController still has its copy and could follow.
- **`llms.txt` carries the shared-schema rule inline** and points at the generated index for exact keys, so a model reading the file alone learns that swapping `c:kofi:` for `c:streamlabs:` is a valid port.

- **The four hand-written pages they replace are gone**, with 301s from their old URLs to the generated ones. They were filed under `eventsub-tags` despite documenting controls rather than EventSub tags, nothing anywhere linked to them, and their central claim had gone stale. The reference is the best-indexed part of the site, so retiring a page there means redirecting it, not deleting the URL.

The Ko-fi, StreamLabs, StreamElements and Fourthwall `*-events` and `*-event-tags` entries are untouched: those document `event.*` payload fields, which is a different thing that is still hand-written for good reason.

## August 4th, 2026 - docs(llms): the homepage now says out loud that machines are welcome

Yesterday's work gave `/llms.txt` a page pointing at it inside the reference. The reference is well indexed, so that was the right first move, but it left the strongest page on the site out of the chain: the homepage is plain Blade, canonical, priority 1.0, and the first thing anything crawls. Its only mention of the file was `<link rel="llms-txt">` in the head, which is exactly the non-link that started this whole problem.

The footer now carries the invitation as visible body copy.

- **"Reading this as a machine? You are welcome here, and this is not a grudging robots.txt allowance."** Four links follow: the file itself, its explainer page, `/help.md` as the crawl entry point, and the JSON tag catalogue.
- **Visible, not hidden.** A hidden keyword block would be a spam signal, and this is a real invitation to a real audience. It sits in the footer where it does not interrupt the pitch above it.
- **The chain is now closed from the top**: homepage (priority 1.0, crawled) to `for-machines/llms-txt` to `/llms.txt`, with the reference's own 140-page footer as a second path in.
- A test pins the homepage anchor, since losing it would silently undo the part of this that matters most.
- **"No rate limit" came back out of all four places it had been written.** The math is not the problem: `llms.txt` is 24KB raw and 9.7KB gzipped, served by Caddy's file server with no PHP process and no database query behind it, so ten thousand requests is about 97MB and a rounding error against the transfer allowance. The problem is the sentence. It advertises the absence of protection, and it is a promise that becomes a lie the day we want to add one. "No login, no API key, nothing to sign up for" says the useful part and commits to nothing. Both machine-facing entries now ask politely for caching instead.

## August 3rd, 2026 - docs(llms): llms.txt now has a page pointing at it, because a meta tag is not a link

Overlabels has published a complete overlay-authoring guide at `/llms.txt` for a while, and every attempt to get an assistant to actually read it ran into the same wall: it would not fetch a URL nothing had indexed, and nothing would index a URL nothing linked to. The `<link rel="llms-txt">` in the document head reads like a link but is not one - crawlers follow anchors, and `llms.txt` is a convention rather than a ratified standard, so no crawler goes looking for it on its own. The file was published and invisible.

It now has a page whose whole subject is the file, on the one part of this site a crawler can actually read.

- **`/help/reference/for-machines/llms-txt`.** The reference vault is plain server-rendered Blade - every `/help/*` prose page is an Inertia shell that hands a fetcher ~27KB of `<head>` and no words. So the reference is the only crawlable HTML documentation on the site, and that is where this belongs. The entry says what the file contains, states in plain language that any model may read it, and includes a copy-pasteable prompt for handing the URL to an assistant.
- **Two neighbours, so the category is not a lonely SEO page.** `markdown-endpoints` documents the "append `.md` to any help URL" convention, and `help-reference-index-json` documents the full tag catalogue as JSON. All three were already described inside `llms.txt` itself, with nowhere on the web to point at.
- **Body copy on `/help/reference` proper**, in the article column above the fold - the highest-priority page in the section, and the first thing in `<main>`. Not a badge, not an icon link. There is a comment in the Blade saying so.
- **A footer on every reference page**, so all 140 of them link to the file rather than just the index.
- **JSON-LD** naming `llms.txt` as a free `DataDownload` with `encodingFormat: text/plain`, which is the machine-readable way to say the thing the prose says.
- **The link is reciprocal now.** `llms.txt` §11 points back at the page that explains it, and `sitemap.xml` moved the file from priority 0.5 to 0.9 - nothing on this site matters more to a machine reader.
- **`@context` is a Blade directive.** Writing the JSON-LD out as literal JSON compiled `"@context"` into a call to the Context facade and swallowed the rest of the template, which took the entire reference down with it. The structured data is built as a PHP array and `json_encode`d, so Blade never sees the `@`-prefixed keys. `@type` and `@id` are not directives today; nothing says they will not be.
- **Eight tests pin the chain** from sitemap to index page to explainer to the file and back, including one that fails if `public/help-reference-index.json` is left unregenerated after a new entry is added.

## August 3rd, 2026 - ui(events): the events feed's empty state now offers the way out instead of describing it

"No events match your filters. Try widening the time range or clearing search." Two problems: the advice was static, so it suggested clearing a search you had not typed and widening a range already set to All Time; and it described an action rather than offering one, while the filter panel it refers to is collapsible, so the search box was often not even on screen.

The empty state now says which of your filters is actually responsible, quotes the search term back at you, and makes clearing it a button.

- **Three branches, each one true.** A search is named and clearable; a narrowed time range is called out on its own; anything else falls back to the plain message. No sentence mentions a filter that is not set.
- **`EmptyState` grew a default slot**, so callers that need markup in the copy can pass it while `message` keeps working. The slot falls back to `message`, and every existing caller passes a plain string or the named `action` slot, so nothing else changed.
- **Clearing cancels the pending debounce** before it applies, so a keystroke still in flight cannot land after the clear and re-filter the list behind you.
- **All three feeds got it**, since the same sentence was copy-pasted across the token-authed feed, `/dashboard/recents` and `/dashboard/events`.
- **`/dashboard/events` also got the echo guard** that Recent Activity received earlier the same day. It had been carrying the identical character-eating search box the whole time - same wholesale replacement of the local filter object on every response, same one-round-trip-stale value written back into the field being typed in. Found while adding the empty state to the same file.
- **Then all of it got deduplicated**, because writing the same guard twice is how the second page came to be missing it in the first place. `useEventFilters` now owns the filter ref, the echo guard, the debounce and the clear for both Inertia feeds; `EventsEmptyState` owns the empty copy for all three. 200 lines out of the three consumers, ~90 back as shared code, and one place left to get it wrong.
- **The token-authed feed deliberately does not use the composable.** Its filters are local state that never round-trips through props, so it has no echo to guard against - wiring it through a watcher that watches nothing would be indirection bought with no bug fixed. It shares the empty state and nothing else.

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
