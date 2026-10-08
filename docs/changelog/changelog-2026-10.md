# Changelog - October 2026

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
