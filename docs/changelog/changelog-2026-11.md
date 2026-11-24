# Changelog - November 2026

## OL-2611-037 - November 24th, 2026 - feat(alerts): burst triggers, so a burst can have an alert of its own

Since OL-2611-036 a burst of follows, subs, resubs, cheers or channel point redemptions plays one
alert, but it had to be the streamer's normal alert for that event. Now a burst can play an alert
made for it.

- **Five new triggers** on an alert's Triggers tab, each listed right under its own event: Follower
  Burst, Subscription Burst, Resubscription Burst, Bits Cheer Burst and Channel Points Burst. A burst
  plays the burst trigger's alert when one is assigned and the event's normal alert when not. A
  single event always plays the normal alert, so assigning only a burst alert means single events
  play nothing and bursts do.
- **Kits and previews know them.** Each burst trigger has preview samples (its event plus a count),
  so a kit can declare one and its preview plays it.
- **A reference page for the burst tags**, `[[[event.burst_count]]]` and
  `[[[event.burst_started_at]]]`, with a copyable example of one alert that serves both a single
  follow and a burst.
- The gift subs reference page no longer describes an "8-second window" left over from an older
  gift bomb detector; it describes how gift bombs are handled today.

## OL-2611-036 - November 24th, 2026 - feat(alerts): many of the same Twitch event at once play one alert

A raid arrives as a follow bomb: dozens or hundreds of follows within seconds, each with its own
alert, TTS line and chat line. The overlay queue drops most of them anyway, the TTS talks over the
streamer, and on the single events worker every one of those alerts cost ~50 ms while every other
streamer's alerts waited behind them (measured on prod the same day, issue #343). Now a burst is one
alert.

- **How it decides.** The first follow, sub, resub, cheer or channel point redemption waits about a
  second. If nothing of the same kind follows, it plays as it always did. If more arrive, they play
  ONE alert: the first event's, with a new `[[[event.burst_count]]]` tag holding how many there
  were and `[[[event.burst_started_at]]]` when it began (Unix seconds). The burst stays open while
  more keep coming, until a four-second gap closes it. Both timings are one constant each in
  `AlertBursts`.
- **Only the alert is merged.** Every event in a burst is still stored, counted, metered and sent to
  the overlay as an event. Stream sessions list the merged ones as "part of a burst alert".
- **The rules.** Cheers burst only across different viewers: one viewer cheering again gets an
  alert per cheer. Gifted subs stay with the gift bomb handling and never burst. Channel point
  redemptions burst per reward. Raids, hype trains and gift bombs are already one event each. The
  donation services (Ko-fi and the rest) always alert once per donation. An event with no alert
  mapped is never held.
- **No migration, nothing breaks.** Existing alerts do not use the new tags, and an unused tag
  renders as nothing, so a burst plays the streamer's normal alert once. Burst alerts of their own
  on the Triggers page come next.

A single event now reaches the overlay one to two seconds later than before: the database queue
delays in whole seconds.

## OL-2611-034 - November 22nd, 2026 - fix(eventsub): a refused recreate stays on record, and failed subscriptions are repaired one by one instead of wiped

The first real repair run of the revived subscription monitor (OL-2611-032) exposed a blind spot.
Two accounts had lost 25 of their 29 Twitch subscriptions because their permission for Overlabels
was gone. The monitor deleted the dead records, asked Twitch to recreate them, and Twitch refused.
Nothing was stored, so the next report said 100% healthy and never named those accounts again.
Their own /wiring page went quiet too. Both accounts had stopped using Overlabels and were deleted,
but the next streamer whose permission lapses would have been invisible (issue #344).

- **A refused recreate is now kept on record.** A dead subscription stays until its recreate
  works, at which point the new one replaces it. If Twitch refuses it, or the permission it needs
  was never granted, it is marked as refused. The monitor names that account every hour ("refused
  by Twitch, the streamer must log in again") and stops retrying. /wiring shows the subscriptions
  as needing repair.
- **Logging in again now actually repairs it.** Before, a returning streamer's login only set up
  subscriptions for new or never-connected accounts, so logging back in after withdrawing
  permission changed nothing. A login now runs the setup whenever the account has refused
  subscriptions.
- **The same blind spot had a second door, and it is closed too.** A subscription Twitch revoked
  or disabled used to trigger a full wipe: every subscription of the account deleted, then all of
  them recreated, so a refusal left nothing behind. Now only the failed subscription is deleted, by
  its own id, and it goes through the same recreate-or-record-the-refusal path.

The full wipe still exists where it is asked for: the reconnect button on the integrations page,
the monitor's manual `--force`, and account deletion.

## OL-2611-032 - November 21st, 2026 - fix(eventsub): the hourly subscription monitor runs again, report-only, and can no longer delete other streamers' subscriptions

A real follow on JasperDiscovers never reached Overlabels. It was not the server, the connection
or Linode's emergency host migration the day before: other channels' raids, subs and follows kept
arriving all evening. The follow simply never left Twitch. That channel's follow subscription had
delivered nothing since September 10th while the database still listed it as enabled, and every
follow stored since then was a Twitch CLI test. Reconnecting created a fresh subscription, and a
follow from an alt account arrived straight away.

Something was supposed to catch that. `eventsub:monitor --fix` runs every hour, asks Twitch for the
real status of every subscription not checked in a day, and repairs the dead ones. It had crashed
on every run since a cleanup in April, before checking a single thing, so for seven months nothing
was watching.

- **The crash is fixed**: one query selected an event type and then read a count it never asked for.
- **The monitor now ships report-only.** Its repair code has not run against today's data since
  April, so before it touches anyone's subscriptions it reports for a while. It still checks every
  subscription against Twitch each hour and records the real status, which is what lets the report
  name the dead ones. Repairs come back once that report has been read (issue #342).
- **A subscription Twitch dropped is repaired, not forgotten.** Before, it was marked "not found on
  Twitch", stamped as checked, and never looked at again. With repairs on, the dead row is removed
  and the normal setup recreates just the missing event types, deleting nothing on Twitch.
- **The bot account can no longer take every streamer's chat notifications down with it.** Twitch's
  subscription search by user matches anyone named in a subscription, and the bot is named in every
  streamer's chat notification subscription. Removing the bot account's subscriptions, by a repair,
  a reconnect or an account erasure, would have deleted all of those too. Removal now only deletes
  subscriptions the account is the broadcaster of.
- **A partial answer from Twitch is no longer read as a dead subscription.** Twitch answers in pages
  of 100 and the check reads one; a subscription on page two is now left alone, not marked missing.
- **The Ghost User is skipped.** It has no Twitch login, so the monitor would have queued a setup
  for it every hour that failed every time.

## OL-2611-030 - November 20th, 2026 - feat(designer): the preview plays the game while you pick its colors

The color designer for the three full-screen games had one flaw the moment it shipped: the preview
showed the game as it stood, which on a quiet channel is an empty board, no tower and nobody
bowling. A streamer was asked to pick a "danger" color for a tower they had never seen lean.

Now the preview plays. Plinko's designer has the same sample chat bar as Twitch Chat, and half the
sample chat is the drop command, so balls fall, pegs light up and the scoreboard fills in the colors
being picked. Chat Tower and Follower Bowling are driven by the server rather than by chat, so they
get **moments** instead: a row of buttons above the preview.

- **Chat Tower**: Standing, Close to falling, About to fall, Toppled and New record. Each one is
  the tower at that point, built on the real physics, so the warning and danger colors, the flash
  on the block that topples it and the record flash all show up where they will on stream.
- **Follower Bowling**: Bowl. One throw exactly as the lane plays it, random outcome included, so a
  few clicks show a strike or a gutter too.
- **Live** puts the real game back.

A moment is never real. It is declared in the product's manifest as the same events the server
would broadcast, and the preview hands them to the overlay's own handlers in that one browser frame.
Nothing is written, nothing is broadcast, the all-time record is never touched, and an overlay in
OBS does not even listen. A "test" button that stacked or bowled for real was ruled out for exactly
that reason.

## OL-2611-029 - November 20th, 2026 - feat(products): a color designer for Plinko, Chat Tower and Follower Bowling

The three full-screen games can be recolored. Twitch Chat Plinko, Chat Tower and Follower Bowling
each have a designer now, like Twitch Chat and Chat Emote Bubbles: pick one of three themes, change
any single color, and save the result as a look of your own. The themes are the same three on all
three games, so a stream can match them:

- **Twilight** - deep violet with pink highlights, the colors the games have always had.
- **Nordic** - night blue with yellow highlights. Jasper's own Plinko colors, which started this.
- **Moss** - dark forest green with lime highlights.

Plinko has five colors, Follower Bowling six (the lane gets its own), and Chat Tower ten: the same
six plus the two warning colors for a tower close to falling and about to fall, the flash on the
block that toppled it, and the new-record flash. Tower's "Show how to play" switch moved onto the
designer page too.

Under the hood each overlay's palette was already one block of CSS variables, cleaned up the same
day so every color on screen is built from it, including the see-through glows and lines. Those
variables now read color controls, and the variables that were named after a color are named after
what they do: `--highlight`, `--warning`, `--danger`, `--record`. The Plinko board used to read its
colors once when it loaded, so a change only showed after reloading the browser source. It now
re-reads them twice a second, which is what makes the designer's preview recolor the pegs and balls
live.

Existing installs that nobody edited get the color controls from a migration, set to the colors
they already had, so nothing on stream changes until someone picks a new one. An overlay whose
markup or styles were edited by hand is left exactly as it is and logged; reinstalling gets it the
designer.

## OL-2611-028 - November 19th, 2026 - feat(bubbles): twenty shapes for Chat Emote Bubbles

Chat Emote Bubbles has a fifth look, **Choose another shape**, and with it twenty shapes to float
emotes in: a ghost, a cat head, a Christmas bauble, a water drop, a paw print, a flame, a squircle,
four stars, a starburst badge and a run of polygons from triangle to dodecagon. Pick one under
Shape in the designer. Each is drawn in your two colors and your gradient angle, like every other
look.

The emote is the content and the shape is the frame, so the emote never shrinks to fit. Every shape
grows around it instead, keeping the same breathing room on all sides, which is why a star or a
triangle comes out bigger than a squircle. Three new controls set the frame:

- **Border width** - the outline in pixels, 0 for none. A thicker outline grows the shape rather
  than squeezing the emote.
- **Fill strength** - how strongly the gradient fills the inside, from see-through to solid. The
  outline stays at full strength.
- **Breathing room** - the space between the emote and the outline.

Making the shapes as tall as they need to be showed up something the soap bubble had always hidden:
a bubble travelled exactly one bubble size past the edge before stopping. A paw print is taller
than that, so with Pop after at 0 a row of toe tops stayed parked along the bottom of the screen.
Bubbles now travel as far as their picture actually reaches, start far enough outside the screen
that nothing peeks in early, and stop fully on screen when they gather to pop. The other looks move
exactly as before.

Existing installs get all of it from a backfill. An overlay whose markup or styles were edited
where the backfill writes is left alone, and a select or description the streamer changed keeps
their version. The product's hero image is new too. The shape library started as Jasper's
prototype, sketched with Claude.

## OL-2611-026 - November 19th, 2026 - fix(designer): a preview token read off a stream works nowhere else

The designer's preview is the real overlay in a frame, and the frame's address carries a token. In
Firefox, "This Frame > Open Frame in New Tab" puts that address, token and all, in a tab of its own,
and on stream that is one screenshot away from a viewer holding a day's read access to the account:
every overlay, the live event channel, the events feed and the Lists.

The token is now tied to the browser that opened the designer. Opening the designer sets a cookie
that page scripts cannot read and that only goes to the overlay's own API, and the token's record
keeps a fingerprint of it. Without that cookie the token renders nothing and joins no channel, and
it never reads the events feed or a List at all, cookie or not. So the token can still show up in
an address bar, but it does nothing anywhere else.

It also lives two hours instead of a day. The open designer quietly renews it every twenty minutes
and when you come back to the tab, keeping the same token, so the preview never reloads. A designer
left past its token (a laptop that slept) gets a fresh one and reloads the frame once, as before.
OBS links are untouched.

## OL-2611-025 - November 19th, 2026 - feat(controls): the gradient angle is a circle you drag

Chat Emote Bubbles has two colors and an angle between them, and the angle was a slider from 0 to
360. Nobody pictures a direction from a number, so turning it meant dragging and squinting at the
preview. It is now a circle painted with your two colors and a line through it. The big end of the
line is your first color: drag it to where that color should be, and the circle shows exactly the
gradient your bubbles get. The number box stays beside it for anyone who wants exactly 90.

The circle reads the colors live, so changing Bubble color repaints it at once, and it greys out
with them on the Plain look. Arrow keys turn it one degree at a time, Shift makes that fifteen, and
holding Shift while you drag snaps to fifteen.

Any number control can be one: it names the two color controls it turns
(`angle_colors=["bubble_color","bubble_color_2"]`), the same way it names its choices. Existing
installs get it from a backfill. The sketch it came from was Jasper's, built with Gemini and Claude.

## OL-2611-023 - November 19th, 2026 - feat(controls): a knob greys itself out while another holds a value

Chat Emote Bubbles gained a fourth look, **Plain**: no bubble, snowflake or heart, just the emote,
drawn a little larger to fill the space. It is a "Start from" preset on the designer, and existing
installs get the choice too.

Plain made an old gap visible. The designer showed every knob all the time, so the two bubble
colors and the gradient angle stayed editable on a look that draws no bubble, just as Spawn X and Y
always had, though they only steer the cannon. Now a control can say when it is worth editing:

```
- `c:spawn_x` - For the cannon: how far across the screen a bubble appears...
  - enabled_if=["c:spawn = cannon"]
```

Each entry is the inside of an `[[[if:...]]]`, read and judged by the same code that runs `if` in
an overlay, so there is still one condition language in Overlabels. A list means every condition
must hold. The designer and the Values tab grey the knob out where it stands, with the reason
underneath ("Not used while Look is Plain."). Nothing moves, and nothing changes value: the colors
you picked are still there when you switch back to Heart, and the overlay reads them as before.

It started as a pipe, `[[[c:bubble_color|disabled:look:plain]]]`, which fell apart the moment it
needed two conditions. The pipe was the wrong home: it sits on one place a tag is printed, and
whether a knob is editable belongs to the control. The control's own detail line was already
there, next to `choices`.

Recipes are held to it when the catalogue loads: a condition must read another knob of the same
overlay, written `c:key`, with a value that is one of its choices. Declared on Emote Bubbles (the
three color knobs, Spawn X and Y) and Twitch Chat (Background color, unused with no background),
with backfills for existing installs.

Building it turned up an older bug: the control edit dialog threw away config it draws no field
for. Renaming a text control's label sent an empty config, so a product's Look dropdown turned
into a plain text box, and a number lost anything beyond its six fields. It now sends those keys
back as they were.

The designer's sample-chat slider stopped at 120 messages a minute for every product. A product
now sets its own top in its designer block (`sample_chat_max`): Emote Bubbles goes to 300, because
only some sample messages carry an emote and 120 never looked busy, and Twitch Chat stays at 120,
where a feed already reads as noise.

## OL-2611-022 - November 17th, 2026 - feat(dashboard): a lobby instead of an inventory

The dashboard listed your data: installed products, then a tab strip of overlays, alerts and stream
activity, then a column pointing at the updates page. Nobody lands on a dashboard to be told they own
thirty overlays. A streamer opening it before a stream is tired, and the page made them more tired.
It is now a lobby with four things on it, in the order someone actually wants them.

- **Hello.** A big time-of-day greeting with your avatar, and one sentence about your stream: live
  right now, or when you last went live and for how long. The newest unread post from Updates sits
  beside it as a single link, when there is one.
- **One door.** A single Next up card with one button. An installed product that is not finished
  setting up comes first ("Chat Tower is one step from your stream"); otherwise a game you do not
  have yet; otherwise "All set". Never a menu.
- **On your stream.** Installed products as a row of art tiles, each saying Ready or how many steps
  are left, ending in a tile to find something new.
- **Lately.** Your last stream (how long, how many moments) and the five latest moments, each with a
  link to its full page.

New accounts see exactly what they saw before: the welcome dialog and the banner that points at the
products. The old installed-products box is gone, and so are the overlay, alert and activity tabs;
those all live on their own pages in the sidebar.

## OL-2611-021 - November 17th, 2026 - feat(bot): !forgetme asks first, and !unforgetme is the way back

`!forgetme` did exactly what it said, the moment it was typed: everything Overlabels held about that
viewer deleted, everywhere, and every Overlabels channel ignoring them from then on. That is right
for someone who means it. It is not right for someone typing `!forgetme I hate this chat` at the end
of a bad night, who wakes up the next day to find check-ins and chat games silently doing nothing,
with no idea why and no way back short of an email nobody would find.

- **It asks first.** A bare `!forgetme`, or one with anything after it, now deletes nothing. The bot
  explains that the deletion cannot be undone, says `!unforgetme` exists, and waits for
  `!forgetme confirm` from the same viewer within two minutes. "Type it again" was rejected: Twitch
  drops a non-mod's identical message inside 30 seconds, so the repeat would often never arrive.
- **`!unforgetme` is the way back**, with the same two steps. It lifts the "ignore me" and nothing
  else: what was deleted stays deleted, because there was never a copy. Platform-owned and
  everyone-tier like `!forgetme`, so no channel can switch it off.
- **Still silent.** A viewer who opted out is never told so when a check-in does nothing. They asked
  to be left alone, and announcing it in chat would undo that. `!unforgetme` is mentioned in exactly
  two places: the confirmation question and `/viewers`.
- **The bot forwards only the first word** after the command. Whatever else someone typed never
  leaves the bot.

The bot shipped first, deliberately: the app now waits for a `confirm` that only the new bot passes
along, so the other order would have made `!forgetme` impossible to complete in the gap.

## OL-2611-019 - November 16th, 2026 - feat(bowling): a full-screen lane that runs itself, first come first served

Follower Bowling was the last game from before the products settled into what they are now:
full-screen scenes a streamer puts on their own OBS scene for a break or some downtime. It was a
strip along the bottom of a transparent canvas, hard to read on a light background, hidden behind a
`gobowl` switch, and it shipped two moderator commands to skip the line. It now looks and works like
Chat Tower and Twitch Chat Plinko.

- **One full-screen scene.** How to play and who is next on the left, a vertical lane in the middle
  with the ten newest followers racked as pins (avatar and name, readable on the dark field), and on
  the right who is bowling and how many pins went down. A strike gets a banner naming the bowler.
  Between throws the ball rests dimly on the approach.
- **`!bowl` and nothing else.** One spot per viewer, first come first served, and the next one in
  line bowls every fifteen seconds whenever someone is waiting. No switch: the lane is on screen
  when the streamer switches to its scene. `!fbfirst` and `!fbdraw` are gone; `!list lane pop first`
  and `draw` still work by hand.
- **The bowling itself is unchanged.** Same twenty expression controls, same dice roll from the
  moment a bowler leaves the line, same timing.
- **Every existing install is moved over**, edited or not: the new lane, no `gobowl` control, and
  the two aliases the old install created removed. Nobody but the author had the lane running.
- A new product hero showing the vertical lane mid-strike.

## OL-2611-018 - November 16th, 2026 - feat(checkin): a designer for the globe, with seven looks and your location

Chat Checkin's globe had two switches and a look that could only change if you knew CSS. It now has
a designer like Twitch Chat and Chat Emote Bubbles: seven looks to start from (Default, Classic,
Night, Day, Monotone, Warm, Cold), then every color, the dot size, the spin, the tilt, the pins and
the font are yours to change, and every change lands in OBS as you make it.

- **Your location lives in the designer.** The farthest checkin used to be measured from a home
  place on the Chat Checkin settings page. It is now a control on the globe, under Settings in the
  designer, and the server measures from it. The settings page points there when the product is
  installed. An account using the integration without the product keeps the old setting.
- **A look never touches your settings.** A preset used to have to set every knob on the page, so
  picking "Night" would have wiped your location. A designer group can now be marked personal:
  presets and saved looks leave it alone. Chat Checkin's Settings group holds the location and the
  HUD and pin list switches.
- **The globe follows the designer live.** It read its colors once when it loaded; it now re-reads
  them twice a second.
- **Continents on any globe color.** The globe body was drawn over the near-side continents. At the
  old 50% black that only dimmed them; at any solid color they vanished.
- **Existing installs are moved to Classic**, the exact look they had, with their old home place
  copied in as their location. The font becomes JetBrains Mono (the old generic monospace is not a
  web font), the near side is brighter and the far side no longer shows through faintly. An install
  whose CSS was edited by hand is left alone and logged.
- **Two globe bugs fixed on the way.** With anything ticking in the overlay's data (a timer, or a
  List's expiry countdown), toggling the HUD made the globe
  rebuild its name labels four times a second, so a name on the far side flickered instead of
  hiding. And the first control change after loading moved the globe, because the re-render
  stripped a position the globe had set on itself.

## OL-2611-016 - November 15th, 2026 - feat(tower): full-screen redesign and Coyote Time for whoever topples it

Chat Tower's overlay was a small column on the right edge of a transparent canvas, and it broke on a
light background. It is now a full-screen scene in Twitch Chat Plinko's look: a deep violet field,
how to play on the left, the tower big in the middle between its two fall lines, height and record
on the right. The `!stack` commands are pink text, no longer pills. Testing it on tall towers showed
the topple itself was wrong, so the fall was rebuilt too.

- **Bigger and readable.** Blocks are 240x60 with the chatter's name in bold white, darkened enough
  to read on any chat colour. The lean gauge sits under the ground and runs exactly from one fall
  line to the other. Fall lines, gauge pin and status turn orange when the tower is close to falling
  and pink when it is about to.
- **Tall towers no longer shoot up before they fall.** The camera followed the height control, which
  drops to 0 the instant the tower topples, so the whole stack slid upward half a screen while it
  started falling. The camera now follows the blocks on screen and holds still for the whole fall.
  The sway had the same flaw and snapped the tower straight at the moment of the topple; it now
  comes from each block's own position.
- **Coyote Time.** The block that knocks the tower over now actually appears. It used to arrive in
  the same instant as the fall and was never seen. It stays put in mid-air while the tower drops out
  from under it, each block toward the side it leaned, flashes red twice, then drops on its own and
  shrinks into the distance.
- **The culprit is the headline.** The banner after a topple leads with their name, "toppled the
  tower at N" under it, and waits until the culprit has dropped. While it is up, the status under
  Height reads "tower collapsed!" and the empty-ground placeholder stays out of the way.
- **`!stack` waits for the endgame.** For nine seconds after a topple, as long as the banner shows,
  `!stack` is silently ignored, so a new tower cannot start under the banner. An ignored stack does
  not use up the viewer's cooldown.
- The banner, record flash and empty ground each sit in their own wrapper, because one appearing
  next to another restarted the banner's delayed fade-in, and the banner briefly showed, vanished
  and came back.
- Only new installs get the new overlay. An existing install keeps the old one until it is
  reinstalled or the new overlay is imported.

## OL-2611-015 - November 15th, 2026 - feat(tower): test mode, so a streamer can try !stack offline without counting anything

Chat Tower only takes a `!stack` while the stream is live, which made it impossible to try out
before going live. Twitch Chat Plinko never had that problem, because the overlay reads chat itself
and runs the game in the browser source. The tower's blocks, records and counters live on the
server, so every stack passes the live check there. That check stays. The Chat Tower settings page
now has a Test mode switch that steps around it while you are offline.

- **Same tower, nothing kept.** In test mode an offline `!stack` lands and the tower sways and falls
  on the overlay exactly as on stream, and the bot speaks its usual lines. No event is stored,
  nothing counts toward usage, and blocks stacked, topples, tallest tower and the all-time record
  stay where they were. A test tower never counts as a record, so the record roster is untouched.
- **One-second cooldown** in test mode, whatever the streamer's own setting, so it can be stacked
  quickly by one person.
- **Only offline.** Once the stream is confidently live, test mode is ignored and every stack counts
  with the normal cooldown, so a switch left on cannot quietly stop a real stream counting.
- **Switching it off knocks the tower down**, so no test block is left standing for a real stream.
  The last stacker and last topple names written while testing stay until the next real ones.
- The switch uses the same yellow as the donation integrations' test mode.

## OL-2611-014 - November 15th, 2026 - feat(products): two-color gradient bubbles, a real color picker and calmer saves in the designer

Chat Emote Bubbles was one color. Every look - soap bubble, snowflake, heart - now paints a gradient
between two colors, turned by an angle: 0 runs top to bottom, 90 right to left, 180 bottom to top.
The same day showed why the designer felt slow while tuning it: every pause in a slider drag sent
its own save, each one queued a broadcast and covered the preview with "Applying settings" until it
arrived. Six pauses, six saves.

- **Two colors and an angle.** New controls Second color and Gradient angle, under Bubble color.
  The CSS used to tint everything through `color-mix()`, which only takes one color, so each look
  now paints the gradient and cuts it to shape with a mask. With both colors the same, the looks
  are what they were.
- **Every existing install gets it.** A migration adds the two controls (second color copied from
  the first, angle 0, so nothing changes on stream) and patches the five CSS rules involved, leaving
  the rest of a streamer's own edits alone. An install whose patched rules were rewritten by hand is
  left exactly as it is and named in the log.
- **One save per drag.** Sliders and color pickers move freely and save when you let go. Keys held
  down on a slider send at most two saves, and an older save can no longer land after a newer one
  and pull the knob back.
- **The designer and the Values tab use the app's own color picker**, with the presets and the
  transparency slider, instead of the browser's.
- **A plain stage.** The preview sits on the page's own background, dark or light with the theme,
  instead of a checkerboard that was hard on the eyes when following a moving bubble.

## OL-2611-013 - November 14th, 2026 - perf(twitch): answer the webhook first, process on a dedicated twitch-events worker

A stress test of the new alert queue turned up a slow webhook: 30 follows posted at once took prod 17
seconds to get through, and 14 of the calls never got their 200. Each notification did all its work
before answering Twitch: avatar lookups, counters, the alert with its own Twitch API calls. About
1.4 seconds per follow, on a web server that runs only a few PHP threads. Twitch revokes a
subscription that keeps failing to answer, so a follow-bot wave could have cost a streamer their
alerts.

- **The webhook answers straight away.** It checks the signature, removes what we must not keep,
  stores the event (which is also the guard against Twitch's redeliveries) and answers. Everything
  else happens a moment later.
- **A worker of its own.** The rest runs on a new `events` worker that only handles Twitch events,
  one at a time and in the order Twitch sent them, so a gift bomb's recipients still meet their
  gift first. Alerts never wait behind a text-to-speech job on the main worker.

## OL-2611-011 - November 14th, 2026 - feat(alerts): alerts take turns, and a gift bomb is one alert

Three raids fired a second apart showed the first and the last: every alert replaced whatever was on
screen, and an alert whose source had to be fetched was dropped if another arrived before the fetch
finished. A busy moment on stream lost alerts. They now take turns, the way StreamElements and
others play them.

- **Alerts queue.** An alert that arrives while another is showing waits for it, in timestamp order,
  with a short gap in between. One that is still loading keeps its place in line. Nothing jumps the
  line. At most 25 wait, and one that waited 3 minutes is dropped rather than shown late.
- **Live alerts still update in place.** Poll, prediction, hype train, goal and charity progress
  arrive over and over for one ongoing thing; those update the alert on screen instead of queueing
  copies. The alert broadcast now names its event type, because the alert's data only holds the
  tags its template uses.
- **Sound and TTS play when the alert shows**, not when it arrives, so a queued alert never talks
  over another. A dropped alert stays silent.
- **A gift bomb is one alert.** Twitch sends the gifter's message with the final number first
  ("gifted 25 subs"), then one gifted-sub message per recipient over the next seconds, 13 of them for
  25 gifts on prod. Each recipient used to fire its own New Subscriber alert, which would now queue
  for minutes. When a Gift Subscription alert fires, the next that-many gifted subs are absorbed: no
  alert, no TTS, no chat line, but they still count in counters, sub lists and tags. The countdown
  expires 2 minutes after the last gift or recipient, subs people pay for themselves are never
  absorbed, and without a Gift Subscription alert nothing changes.
- **The delivery log says so.** Absorbed subs are recorded as "part of a gift alert" on the stream
  sessions page.

## OL-2611-010 - November 10th, 2026 - feat(alerts): alert products ask for the Alerts overlay, and /kits leads with the ready-made kits

The loose ends of the Alerts overlay (OL-2611-007) and the kit picker (OL-2611-009).

- **Alert products point at the Alerts overlay.** The OBS step of the five donation alert products
  used to offer the person's five newest overlays, or send them off to make one. It now asks for the
  Alerts overlay with the same create and Add to OBS panel as everywhere else. It still counts as
  done when another overlay of theirs has been in OBS lately, because until the Alerts overlay is
  live the alert really does show there.
- **The products say so.** The five alert products and their alert documents now say the alert
  shows inside the Alerts overlay, not in every overlay in OBS.
- **/kits opens with the ready-made kits.** The same cards as the kit picker, above "Your kits",
  so an account past its first login finds them too. They no longer repeat in the public list.

## OL-2611-009 - November 10th, 2026 - feat(kits): new accounts pick a kit, and ready-made kits lead the Alerts shelf

Every new account used to get a silent copy of the starter kit, Midnight Purple, with its Twitch
alerts wired by matching words in the alert names. Nobody chose it, and a streamer who wanted
donation alerts was never shown that Stamped or Subtle existed. Kits are the quickest way to get
something good-looking on stream, so they are now the front door.

- **A kit picker for new accounts.** After their first Twitch login, a new account lands on
  "Pick your alerts" (`/start`): the featured kits as cards with their thumbnail and what they
  cover, each one opening the kit page where its alerts play before anything is copied. "Skip for
  now" goes to the dashboard. Someone who signed up on their way to a product still goes there.
- **No more silent copy.** Onboarding no longer copies the starter kit or guesses which alert goes
  with which Twitch event. A new account owns nothing until it picks.
- **Ready-made kits lead the Alerts shelf.** On /products, the Alerts shelf now opens with the
  featured kits. The five single-service alerts stay underneath as "Basic alerts", with their
  heads-up and description unchanged.
- **Featured is a flag.** A kit shows up in both places when it is public and has `featured_at`
  set. It is set by hand, and a copy of a featured kit never inherits it.
- Not in this change: a featured section on the Kits page itself, and a way for existing accounts
  to find the picker.

## OL-2611-008 - November 10th, 2026 - feat(alerts): alerts carry no controls and no Add to OBS; their pages show the Alerts overlay instead

The alert editor was a copy of the static overlay editor: it offered Controls, Values and Add to
OBS, none of which make sense for an alert. A control on an alert never reached the screen, since
an alert renders inside a static overlay and only that overlay's controls are sent. And adding an
alert to OBS on its own sidestepped the Alerts overlay that OL-2611-007 introduced.

- **No more Controls, Values or Add to OBS on alerts.** An alert's edit and view pages end with an
  "On stream" tab instead. It says that an alert does not go into OBS on its own, and shows the
  account's Alerts overlay: create it, add it to OBS, and whether OBS has loaded it.
- **Creating an alert shows the same panel.** Picking "Event Alert" on the create page puts the
  Alerts overlay right under the tips, and creating it there keeps the half-written alert.
- **The server agrees.** Adding or importing a control on an alert is refused. TTS and chat lines
  keep reading any of the account's controls, so nothing they did needed one on the alert.
- **One panel everywhere.** The kit page's Alerts overlay block became `AlertsOverlayPanel`, used on
  the kit page, both alert pages and the create page.
- Left alone: an alert already added to OBS on its own still renders, and controls already on an
  alert stay where they are.

## OL-2611-007 - November 10th, 2026 - feat(alerts): one Alerts overlay per account, and untargeted alerts render only there once OBS has it

An alert never renders on its own: it renders inside a static overlay that is in OBS. Until now an
alert with no targeting rendered in every static overlay of the account, so a streamer with two
overlays in OBS saw every alert twice, and a new streamer who installed an alert product with no
overlay in OBS saw nothing at all. Kits and alert products had no answer to "where do these show
up".

- **Every account can have one Alerts overlay.** A transparent, private static overlay that exists to
  hold alerts. A kit's owner creates it from the kit page and adds it to OBS with the usual Add to
  OBS button.
- **Once OBS has loaded it, untargeted alerts render there only.** The first load is stamped on the
  account. Before that, nothing changes: alerts keep rendering in every overlay, so creating the
  overlay can never take alerts off a stream before it is in OBS. Alerts with explicit targets keep
  them.
- **Kits can ship settings for their alerts.** A kit that contains its author's Alerts overlay
  merges that overlay's controls into the copier's own Alerts overlay when copied, instead of adding
  a second overlay. The Subtle kit's corner picker works this way.
- **The kit page shows where alerts go.** Above "On your stream", the owner of a kit with alerts sees
  their Alerts overlay: a create button, Add to OBS, and whether OBS has loaded it yet.
- Not in this change: the alert editor still has Controls, Values and Add to OBS tabs, and the alert
  products still say alerts show in every overlay. Both are next.

## OL-2611-006 - November 9th, 2026 - feat(kits): play a kit's alerts before installing it, and copying a kit wires them up

A kit page used to be a thumbnail and a list of names. Now a kit with alerts has a stage beside its
header: the alerts play over a stand-in stream, one button per event they fire on, with sound on
click. Before anyone clicks, it cycles through them silently so the page is never a still picture.

- **Sample events for everything.** `AlertEventSamples` holds a sample for every event the Triggers
  page offers: 27 Twitch events and every external service event, with variants where alerts
  usually branch (a Ko-fi first month and a renewal, a Throne surprise gift and a crowdfunded one).
  Each is a canned raw payload run through the real code a live event goes through: the service
  driver's `normalizeEvent()` and locale formatting, or the Twitch event mapper. A test reads the
  reference page of every Twitch event and fails if a sample does not produce a tag it documents.
- **Kits declare their triggers.** The kit form has an "Alert triggers" section: for each alert,
  which events fire it and for how long. It starts from the author's own Triggers page. One event
  belongs to one alert.
- **Copying a kit wires it.** "Copy this kit" now points the copier's events at the copied alerts.
  An event they already have an alert for, on or off, is left exactly as it is, and the toast says
  how many were wired and how many were left alone. The copy keeps its triggers, so a copy of a
  copy works the same. Onboarding's starter-kit copy wires nothing, as before.
- **The owner sees the state.** Under the stage, a kit's owner sees each event: wired, switched
  off, handled by another of their alerts, or not wired, plus a Connect button for a service that
  is not connected yet.
- **Private means not shared, not unusable.** A private alert in a public kit plays in the
  preview. The kit page no longer sends every template's full source to every visitor, though:
  the listing carries names only, and source goes out solely to render the preview.
- Older kits that declare nothing preview from their author's own Triggers, and an alert nothing
  points at plays with a sample Ko-fi tip.
- Not covered: motion driven by Expression Controls (no engine runs in the preview) and live
  values, which never appear by design.

## OL-2611-005 - November 9th, 2026 - feat(alerts): alerts of any size fire, and an alert's head finally applies

An alert used to travel to the overlay whole, html and css included, on every single fire. Reverb
refuses any broadcast over 10 KB, so an alert bigger than that could be saved, mapped and tested in
the editor, and then never appear on stream. The delivery ledger marked it `failed` and nothing said
why. Found while building the "Stamped" donation alerts kit, whose first draft was 10.2-11.6 KB per
alert and would never have shown.

- The overlay now loads the source of every alert that can fire on it when it opens (`alert_sources`
  in the render payload: head, html, css and a content-hash version), the same way it already
  preloaded compiled CSS and alert sounds.
- A broadcast carries the alert's slug, its version and the event data. The source rides along only
  while the whole message stays under 9000 bytes; past that it is sent as empty strings with
  `source_omitted: true`. A small alert therefore still arrives complete, which keeps overlays that
  were open in OBS during the deploy working without a reload.
- An alert the overlay has no current copy of (created or edited after OBS loaded it, and too big to
  inline) is fetched once from `POST /api/overlay/alert-source`, a token-authenticated read in the
  same shape as the render call. It writes nothing and records no access. Only the token owner's
  own alerts resolve.
- An alert's `head` field now works. It was never in the broadcast, so a font `<link>` in an alert's
  head did nothing and authors had to `@import` instead. Head stylesheets are also warmed when the
  overlay opens, so the first alert of a stream does not paint in a fallback font.
- Verified end to end on local Reverb in Firefox with a 21.9 KB alert: it fired from the mount-time
  copy, then again after an edit through the fetch, and a small alert still arrived inline.
- Chunking the broadcast across several messages was considered and rejected: it would keep
  resending a template that never changes between fires, add ordering and loss handling, and
  multiply the metered message count.

## OL-2611-004 - November 8th, 2026 - feat(store): Paddle fulfillment backend for one-off product purchases

The groundwork for selling paid products. Paddle is the merchant of record: it takes the payment,
charges the VAT, handles refunds and chargebacks, and issues the invoice as "Overlabels via
Paddle.com". Overlabels only has to know who bought what, and this is that half. There is no Buy
button yet, on purpose.

- Paddle reports every sale to `POST /api/paddle/webhook`. The signature is checked on the raw body
  before anything is read. A delivery that fails the check, or fails to save, is answered with an
  error so Paddle tries again (up to 60 times over about two days on live).
- A sale is tied to an Overlabels account through the checkout's `custom_data`
  (`overlabels_user_id`). Verified in the sandbox: the value survives to `transaction.completed`
  unchanged. A sale that names no real account is still recorded, with no user, so it can be
  matched by hand.
- Purchases are one-off. There are no plans and no subscription state; subscription events are
  acknowledged and ignored. An approved full refund or chargeback takes the product back, a
  reversed chargeback gives it back. `PaddlePurchase::userOwns($user, $productId)` is the check.
- Nothing about the buyer is stored beyond the Paddle customer id and which account it belongs to.
  No email, no address: Paddle keeps the billing details.
- `/settings/billing/portal` sends a buyer to their Paddle customer portal for their purchases and
  invoices. Nothing links to it yet.
- Prod needs `PADDLE_ENVIRONMENT`, `PADDLE_API_KEY`, `PADDLE_CLIENT_TOKEN` and
  `PADDLE_WEBHOOK_SECRET` before it can take a delivery.

## OL-2611-003 - November 7th, 2026 - feat(chat): let chatters redact their message on the chat overlay with !hide

A chatter types `!hide my message here` and the chat overlay draws "my message here" in the Redacted
font: solid bars where the words were, in the message's own colour. It is only the overlay. The
message is in Twitch chat as normal, for every viewer and moderator, and in the VOD.

It is off until the streamer turns it on, on the chat settings page (`/settings/chat`), where the
command word can be changed too. Telling chat about it is up to the streamer. The Twitch Chat Overlay
product page has a card saying whether it is on, which opens that setting.

The overlay does the work itself, from the chat connection it already has, so nothing new passes
through Overlabels and there is no bot cooldown in the way. It works in every chat overlay, including
ones installed before today and hand-written ones, without a reinstall.

- The whole message is hidden: emotes are drawn as bars rather than images, and a hidden message
  sends nothing to Chat Emote Bubbles.
- Characters the Redacted font cannot draw (emoji, Cyrillic, Japanese and so on) are swapped for an
  `x` first, so no message falls back to a readable font.
- The setting that hides `!` commands from the overlay does not hide a `!hide` message, and Plinko
  still hears every message as typed. The word cannot be the same as your Plinko word.
- Moderator deletions and timeouts still remove a hidden message.
- Templates get `[[[msg.redacted]]]` to style the whole row. `[[[msg.text]]]` is not redacted; the
  hidden version is in `[[[msg.html]]]`.
- After changing the setting, reload the chat overlay's browser source in OBS.

## OL-2611-002 - November 7th, 2026 - feat(products): Twitch Chat Plinko, the sixth product

Your chat types `!drop` and a ball with their name on it bounces down a Plinko board on stream. The
middle slots pay 5, the rare edges pay 1000, and the six latest drops and five best totals fill in
beside the board. It installs in one click like every product, needs nothing connected and no bot.

It is the first product that ships its own script. The overlay is one tag, `[[[plinko_board]]]`, built
the way `[[[checkin_globe]]]` is: the renderer swaps it for a canvas and loads the board's code only
when an overlay uses it. The board reads `!drop` straight from Twitch chat over the overlay's own
connection, the same one Twitch Chat Overlay and Chat Emote Bubbles use, so no message passes through
Overlabels and there is no command cooldown in the way.

Chat order is game order. Each chatter has one ball at a time, balls join the board at ten a second up
to 150 at once, and everyone else waits in a line that shows on screen with the next five names. That
is what keeps a raid of thousands a game rather than a pile: measured at 20, 200, 2,000 and 20,000
players typing `!drop` at once, it held 61 fps throughout. Balls bounce off pegs and off each other,
pegs light as they are hit, and the slot a ball lands in flashes.

Mods stay in charge: a deleted `!drop` loses its ball or its place in line, and a timeout or ban also
takes the chatter's score. Chatters hidden on the chat settings page never get a ball, while the
setting that hides `!` commands from a chat feed does not stop Plinko, which listens for exactly those.

- New product page, hero, product icon and help guide at `/help/twitch-chat-plinko`.
- `!drop` is the default command; each streamer can choose their own word on the Plinko settings page
  (Settings, Integrations, Plinko), which appears once Plinko is installed,
  since `!drop` is already a well-known command from another chat game.
- Rows, pace, cap and colours are CSS custom properties on `.ol-plinko`.
- Scores count from when the overlay loaded; reloading the browser source starts a new game.

## OL-2611-001 - November 6th, 2026 - feat(users): delete accounts that did nothing in their first 48 hours

> **Not for the blog.** Leave this entry out of `/changeblog` posts and the November highlights
> post entirely, not even a softened mention. It is housekeeping, and "accounts that do not use
> the product get deleted" reads as cold to the very people the posts are written for. The claim
> file stays as the record.

Every signup gets a copy of the starter Kit, EventSub subscriptions and a place on the broadcast
service, whether or not the person ever comes back. Most of those accounts were never used: they
held data nobody needed, and anyone returning to one landed on a dashboard with a stale Kit in it
instead of a fresh start.

`users:prune-idle` now runs every hour and hard-deletes an account once it is older than 48 hours
and all three of these hold:

- it never installed a product (the welcome funnel's install step),
- it has no active overlay token (revoked and expired tokens do not count),
- it owns nothing beyond what signing up created: the starter Kit copy, its overlays, their
  controls and the alert mappings onboarding wired to them. Editing that Kit does not count as
  using the account.

Anything else of its own keeps an account: another overlay, Kit or control, a connected
integration, the bot switched on, a bot command or alias, a list command, a saved look, an uploaded
image. Admins, the system user and banned accounts are never touched; a ban has to outlive the
account, or deleting it would hand a banned streamer a clean signup.

Deletion goes through `UserDeletionService`, the same path as self-serve account deletion, so the
Twitch subscriptions, the Twitch login grant and stored images are released too. Each run deletes at
most 100 accounts and re-checks each one just before deleting it. `--dry-run` lists who would go.
This applies to existing accounts as well as new ones.
