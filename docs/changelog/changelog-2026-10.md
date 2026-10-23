# Changelog - October 2026

## OL-2610-006 - October 24th, 2026 - feat(products): the finished product page leads with the one step left

Finishing a product used to land you on a page that said "Installed", then "Connected" (even for
a product that connects nothing), then "Everything is in place", then a card with a lone "1" in it,
with the Add to OBS button somewhere below the fold and nothing pointing at it. It had grown one
block at a time and it read that way.

The finished page is now one green band that says "Everything is in place" with one sentence that
is true, followed by the steps that are left, followed by reference material.

- Coming back after the last setup step, the page scrolls to the next step on its own and lights it
  up. For a chat product that is "Last step: Add Chat Tower to OBS" with the big button. A later
  visit stays put and nothing glows.
- The OBS step turns into a green tick once a real overlay link has actually loaded the overlay,
  never because a checkbox was ticked. The page checks again when the OBS dialog closes and when you
  come back to the window. Opening the designer does not count as a load.
- Alert products read the same way: the OBS step, then the test tip, then "It landed". The "paste
  your link into Ko-fi" row lives inside the test-tip card, because the test tip is what proves the
  paste worked.
- "Installed", the "Connected" pill, the bot line, the lone "1" and the "X is installed." toast are
  gone. Uninstall sits at the bottom of the page.
- Nine ready messages were rewritten so none of them claims the overlay is already in OBS before it
  is, and seven notes the page now says by itself were removed. Twitch Chat and Chat Emote Bubbles
  show their OBS size hint right under the Add to OBS button.

## OL-2610-005 - October 23rd, 2026 - feat(admin): a funnel page that shows how far new accounts get

The welcome dialog and banner went out today, and there was no way to tell whether anyone followed
them. There is now. Every account gets a first-time stamp for four steps: reaching the products
page, opening a product, installing one, and which welcome link (banner, dialog, or neither)
brought them to the products page.

- `/admin/funnel` lists every signup in the last 7, 30 or 90 days or all time, with how long after
  signup each step happened, and the totals per step on top.
- Each step is stamped once, the first time, so later visits write nothing.
- An install stays counted after an uninstall. Installs from before today still show, because
  their row is the fallback.
- An admin looking through someone's account stamps nothing for them.
- Accounts older than today show blanks for the products page, product page and banner columns,
  even if they got further.

## OL-2610-004 - October 23rd, 2026 - feat(dashboard): new users now actually get welcomed

Honest version first: signing up dropped you on a dashboard that said nothing about where to go
next. Of the last ten people who signed up, one made it to the products. That one is on me.

New accounts are now welcomed and pointed at the products:

- A "Welcome to Overlabels" dialog on the first visit to the dashboard, with one button to the
  products.
- A bright cyan welcome banner above the tabs, with your Twitch avatar and name. The whole banner
  is the link, and it stays until your first product is installed.
- "New" means no product installed, not a recent signup. Someone who signed up months ago, left,
  and came back with nothing installed gets the same welcome.

The official product badge on the products pages is cyan now too, to match.

## OL-2610-003 - October 9th, 2026 - feat(home): a headline that says what the games are for, and a promise about AI

The homepage opened with "Give your Twitch chat something to play.", which read with an undertone
nobody asked for, and its paragraph said "nobody else has them" about games that partly borrow their
ideas from elsewhere. The headline is now "Twitch Chat Games for Downtime and BRB Breaks": what they
are, and when a streamer reaches for one. The paragraph under it names the two actual games, Chat
Tower and Follower Bowling, and the Twitch chat overlay, each linked to its row further down the page.

The dashboard button beside "See the products" made way for one line: free forever, no registration
required, and Overlabels never sells your data or uses it to train AI. "Promised" links to a new page,
`/ai` (a help page at `/help/ai`), which keeps that promise in writing and is honest about the edges:

- What is encrypted (Twitch tokens and integration credentials) and what is not, and that the data is
  stored in the EU.
- ElevenLabs as the one AI service Overlabels talks to, and only for alerts set to be spoken.
- That Overlabels is built with Claude, that Claude has no access to the live database of its own,
  and that three users who agreed to it are the only real data it sees.
- That the code is open source, so anyone can point their own AI at it.

The page title is now "Overlabels: Free Twitch Chat Games and Chat Overlay", the descriptions are
short enough that search results and link previews no longer cut them off, and the share image was
redrawn around the new headline with all five products on it.

## OL-2610-002 - October 8th, 2026 - feat(products): a three-knob designer that plays itself on the Twitch Chat Overlay page

The Twitch Chat Overlay's designer is the most capable thing on the platform, and its product page
showed it as ten blocks of text. Dropping the whole designer on a visitor would bury them, so the
page now shows exactly three of its controls, working: Start from (all ten looks), Accent and Font
size, beside a preview with made-up chat that keeps arriving.

The preview runs the overlay's own stylesheet, straight out of the recipe, with the designer's own
ten presets, so every look on the page is the look OBS shows - Broadcast even drops to a ticker
strip at the bottom. A cursor plays the knobs by itself: it picks the next look, then an accent,
then drags the font size. The moment a visitor's mouse or keyboard enters, the cursor gets out of
the way and the knobs are theirs; it comes back a few seconds after they leave.

- "Your name in chat" puts the visitor on the broadcaster's line. A logged-in streamer starts with
  their own Twitch name.
- The rest of the designer is named in one line, not shown, with an "Open the full designer"
  button: straight to the designer for someone who has the product, to the installer for everyone
  else.
- Reduced motion never plays it, and chat only moves while the section is on screen.
- Every other product keeps its looks as text.

## OL-2610-001 - October 7th, 2026 - feat(products): a full sales page for every chat product

The product pages that shipped in September were a summary at best: one demo, one paragraph that
was half pitch and half install instructions, and three bullets that said the same thing again. It
felt overwhelming and still did not sell anything. Every chat product now gets a real product page,
designed first as a mockup for Chat Checkin and then made into one template.

The page goes: a one-line promise with the Get button and a Works with row (Twitch, Streamlabs,
OBS) above the live demo; "What your chat does" as three numbered pictures with a line each; why
streamers use it, said plainly; the looks you can pick; setup in two or three steps; questions; and
a closing button with the other products underneath. The "live in OBS" bar above the demo is gone
from these pages.

- Each product's copy lives in a new `pitch` block in its manifest, so a new product gets the page
  by writing text, not code.
- The setup steps are derived from whether the product needs the bot, so Chat Emote Bubbles and
  Twitch Chat Overlay say two steps and the others three.
- Products with a designer show its presets as their looks; Chat Checkin shows its three layouts.
- Chat Checkin says "farthest from you" and points at its settings for the home location, which is
  what "farthest" is measured from.
- The five donation alert pages are unchanged.
