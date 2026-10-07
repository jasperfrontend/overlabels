# Changelog - October 2026

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
