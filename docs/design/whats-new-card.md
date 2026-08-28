# What's New card (parked)

**Status:** designed and agreed 2026-08-28, not built. **Unblocked** - the session fix it was
waiting on shipped the same day in `13baef0c` (see "What this was parked behind").
**Replaces:** the tile row + avatar hero in `resources/js/components/WelcomeCard.vue`, which is
rendered near the top of the authenticated dashboard.

## The problem this exists to solve

A tech-savvy developer was shown the dashboard cold and asked "which 2 things are new?". He could
not answer. The two `new` badges on the *My settings* and *Wiring status* tiles were missed
entirely, despite sitting in five large filled buttons in the visual centre of the page.

The assumption that broke: **a badge does not attract attention, it only confirms attention that
has already landed there.** It is a label, not a beacon. Contributing causes, in order of leverage:

1. The dismiss X makes the whole panel read as skippable onboarding fluff.
2. The tile row duplicates the sidebar, so the brain classifies it as "shortcuts I already have"
   and stops parsing individual tiles.
3. No preattentive contrast - a slightly darker purple pill on a purple tile in a purple app.
   The tiles spent the whole contrast budget, so nothing inside a tile can be louder than the tile.
4. Five equal tiles means zero hierarchy. The differentiator was applied one level too deep.
5. The avatar + "Welcome, {name}" hero is the largest, least informative element and steals the
   fixation.
6. **"NEW" is anchored to the author's last change, not to anything the reader experienced.** This
   is the root complaint: it is ambiguous by construction.

**Acceptance test:** show the dashboard cold to someone who has not seen it, ask "which things are
new?". A correct answer inside one second, with no scanning, is the bar.

## The design

Claude Design canvas: `claude.ai/design/p/d089c27b-bee8-493d-9e49-a9f0e999ed23`, artboard
`WelcomeCard v2.dc.html`. Two states, and the tile row and avatar hero are both gone.

**Reading the canvas from a fresh session:** use the `DesignSync` tool (`get_project`, `list_files`,
`get_file`) with that project id. It needs design-system authorization first - run `/design-login`,
or every call returns an authorization error. Worth reading: the artboard itself, and
`uploads/overlabels-dashboard-new-badge-handoff.md`, which is the diagnosis brief and is where the
"badge is a label, not a beacon" framing above comes from. `support.js` is the DC runtime and does
not need reading; `sc-if`, `{{bindings}}`, `style-hover` and `DCLogic` are legible straight off the
artboard.

**The artboard's copy is placeholder and is not trustworthy as product truth.** Its three example
rows are titled "Writing test" (a stand-in for Wiring status), "My settings" and "Loops in
templates"; the dates are invented; and it renders `[[[foreach: poll_options]]]`, which is not the
real syntax. Write the shipping copy from the code, not from the mock. Same for the dark-only inline
styles: map them onto the app's real theme tokens and `.btn` classes rather than importing the
design system CSS, which is a draft.

**Unread state** - two columns. Left rail (300px): "What's new" heading, a summary sentence, and a
link to `/updates` for the full history. Right column: one row per unseen change, each with a teal
dot that pings twice on load (not ambient), a title, a mono `new page - Aug 26` kind/date line, one
or two sentences of copy, and a deep link ("Check your wiring"). Footer: "This card only shows up
when something new ships" + a "Mark all as seen" button.

**Read state** - a thin bar: green dot, "All caught up. This bar lights up again when something new
ships", and an Undo link.

Motion respects `prefers-reduced-motion`. Teal + yellow are deliberately foreign hues against the
app's violet, because an accent from the same family cannot signal exception. Must hold up in every
colour theme, Sepia included - Sepia is what flattened the contrast in the first place.

## Decisions agreed

- **The feed is the existing `updates` table, not a new one.** `App\Models\Update` already has
  `title`, `slug`, `tags[]`, `excerpt`, `body`, `published_at`, an admin CRUD at `/admin/updates`
  and a public list at `/updates`. `excerpt` is the row copy and `published_at` is the date. This is
  what makes the changelog come alive for end users: one authoring surface, not two.
- **Selection is by a `whatsnew` tag.** `tags` is already a json array and `UpdateController`
  already filters with `whereJsonContains`. Zero migration. Adding a post to the card is adding a
  tag while writing it.
- **Dismissal is per user per update**, in an `update_dismissals` table (user_id, update_id, unique
  together, both FKs cascading). This beats a single `updates_seen_at` timestamp: "Mark all as seen"
  is a bulk insert, **Undo is a real delete** rather than page state that dies on reload, and a
  per-row dismiss becomes free later if wanted.
- **Do NOT anchor to last login.** See "Why this is parked" - the clock is wrong, and dismissal
  records make it redundant anyway.
- **New accounts are caught up by definition:** filter `published_at > users.created_at`. No
  registration hook, no seeded rows. You are not accountable for what shipped before someone
  existed.
- **No retro-tagging.** No update carries `whatsnew` today, so at rollout every card is empty and
  the feature lights up the first time a post is tagged. That means no migration backfill and no
  wall of history for existing users. Retro-tagging old posts throws that away.

Resulting selection, in one query:

```
Update::published()
  ->whereJsonContains('tags', 'whatsnew')
  ->where('published_at', '>', $user->created_at)
  ->whereDoesntHave('dismissals', fn ($q) => $q->where('user_id', $user->id))
  ->orderByDesc('published_at')
  ->limit(5)
```

`Update` has no `dismissals()` relationship yet - that HasMany is part of the build, not something
already present.

## Decisions still open

- **Where the mono kind label comes from** (`new page - Aug 26`). A second tag alongside `whatsnew`
  is the zero-migration option, but nothing decides the vocabulary yet. Keep it a small closed set -
  new page, new syntax, new integration - or drop the label entirely.
- **The per-row CTA** ("Check your wiring"). Not in the schema, and the only part needing a real
  migration. An admin free-text URL rots silently on a route rename; storing a route name + params
  resolves server-side and fails loudly, which is testable.
- **`excerpt` is nullable**, so a tagged post can reach the card with no body copy. Either require it
  for `whatsnew` posts at save time or decide what an empty row renders as. Note the standing rule:
  empty renders as nothing, never a placeholder or a dash.
- **Date formatting goes through `users.locale`**, which already exists and is already appended to
  the shared user prop. Do not hand-format.
- **The cap and its overflow copy.** ~5 rows plus "and N more in Updates".
- **The summary sentence.** Without a login clock, "3 changes shipped since your last visit on
  Aug 14" has to become something honest. "3 changes you haven't seen yet" needs no clock at all.
- **Whether the tiles and the avatar hero actually die.** They do in the artboard. Note the
  consequence: **`/wiring` has no sidebar entry**, so the tile is currently its only way into that
  page. Deleting the row without adding a sidebar item makes wiring reachable only from a card
  entry that disappears once marked seen.
- The existing `overlabels:welcome-card-dismissed` localStorage key becomes obsolete - "mark as
  seen" is self-clearing, so the dismiss X can go. That also moves the state from per-device to
  per-account, which is correct for "what changed while you were away".

## What this was parked behind (resolved)

The first draft anchored "new" to the user's last login. The assumption under that - that sessions
here are long-lived, so a login is a rare event - was false: prod ran on the default
`SESSION_LIFETIME` of 120 minutes with no remember-me, so two hours away logged you out and a login
meant nothing about when you last visited.

Fixed and shipped 2026-08-28 in `13baef0c`: the `remember_token` column is restored, the Twitch
callback logs in with remember, and sessions now survive idle expiry. Pinned by
`tests/Feature/PersistentLoginTest.php`.

**That does not revive the login clock.** Nothing records logins - there is no `last_login_at`, no
listener and no login audit anywhere in the app, and `overlay_access_logs` is overlay-token traffic,
not sign-ins. Adding one would be new plumbing for a marker the dismissal records already make
redundant. Build this on `update_dismissals`, as agreed above.
