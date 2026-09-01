---
title: The Overlabels Manifesto
section: Getting started
description: Our principles for building a data engine, not a design engine. Why Overlabels exists and the philosophy behind it.
heading: The Overlabels Manifesto
lead: Our principles for building a data engine, not a design engine. Why Overlabels exists and the philosophy behind it.
canonical: https://overlabels.com/help/manifesto
---

## Overlabels is a data engine, not a design engine.

We exist to pipe live Twitch data into overlays. Styling and animation belong to your own CSS, not to us.

## The template layer is declarative, not decorative.

Template tags describe what data to show and when to show it, not how it should look. Overlabels never
delivers any styled contents as a payload, that's all up to you. Wrap the output in a class, style the
class. It's HTML and CSS after all.

## Native web standards do the heavy lifting. Scripting is a no-no.

Overlabels leans on native web standards: HTML, CSS, conditional logic, and a built-in expression engine
with math functions like sum, avg, min, max, clamp, round, and more. That's a surprisingly powerful
toolkit, and it covers the vast majority of overlay use cases without any custom scripting.

## Logic is allowed, for all your data.

Conditions (if/else), comparisons, and what we call ["controls"](/help/controls), exist to control data
flow. They can also control styles, animations, or transforms. You can use the conditional template tags
in your CSS declarations as well. Overlabels gives you the tools, it's up to you how to use them.

## No opinionated sugar.

There will never be tags like `[[[blink]]]`, `[[[rotation=90]]]`, or `[[[rainbow]]]`.
Those belong to CSS.

Then again, if you want to create a Control that blinks your text and you work it out in CSS and
conditionals, please - be my guest.

## We keep overlays portable.

Any overlay built with Overlabels works in any recent OBS version and any recent browser.

Overlays can be shared freely between users unless you choose to keep them private.

Any overlay created with Overlabels will work with any account.

## Minimum viable magic.

Overlabels is like Minecraft in a sense. Immensely powerful and based on logic and options, but don't
expect a 20-page guidebook. You can use HTML, CSS, conditionals, and Controls to create overlays. How you
style them, what you do with them, and how you control them, is up to you. Start small, e.g. [how to show 
your latest follower on screen](/help/tutorials/latest-follower).

> [!NOTE]
> These principles guide every decision we make. They ensure Overlabels remains focused, predictable, and
> respectful of web standards, while also keeping the system fast and easy to use.
