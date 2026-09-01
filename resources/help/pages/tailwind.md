---
title: Styling with Tailwind - how utility classes really work in Overlabels
section: Building overlays
description: "Overlabels compiles Tailwind v3 utility classes at save time, not in the browser: what that means in practice, which syntax works, why borders need one extra line, and when to just write CSS."
heading: Styling with Tailwind
lead: You can style an overlay with Tailwind utility classes and never write a stylesheet. But Overlabels does not load Tailwind the way a website does, and knowing how the classes actually become CSS explains every surprise you might hit - including the one where your borders are invisible.
canonical: https://overlabels.com/help/tailwind
---

Write `class="flex items-center gap-3"` in a template and it just works. Underneath, there is no
Tailwind script running in your overlay, and knowing what happens instead is worth two minutes of
your time.

## What actually happens when you save

When you save a template, the editor scans everything you wrote - the html field, the head field and
the css field - for anything that looks like a utility class. It then compiles **only the rules you
actually used** into a small stylesheet and stores it with the template. When your overlay loads in
OBS, that pre-compiled stylesheet is injected and that is the whole story. Nothing is parsed or
generated in the browser source.

A typical overlay compiles to a few kilobytes of CSS. The old way of doing this - shipping the
entire Tailwind library to the browser and letting it work things out at runtime - made overlays
noticeably slower to paint, which matters when the overlay in question is a follower alert that
needs to be on screen *now*.

Three practical consequences:

1. **Classes compile when you save through the editor.** The scan runs on the text you authored, so
   a class inside an `[[[if:]]]` branch or a `[[[foreach:]]]` loop compiles fine whether or not that
   branch renders today. But HTML that arrives any other way is never scanned - there is no runtime
   compiler to catch it.
2. **A typo'd class silently does nothing.** `felx` compiles to no rule and no error, the same
   philosophy as a typo'd `[[[tag]]]` rendering as nothing. If a class seems dead, spell-check it
   first.
3. **Your own CSS always wins.** The compiled utilities are injected *before* your css field's
   stylesheet, so anything you write by hand overrides a utility class on the same element. The two
   are meant to mix - utilities for layout, your own CSS for the fancy parts.

## It speaks Tailwind v3, not v4

The compiler understands **Tailwind v3** class names. If you are pasting from a tutorial, an AI
assistant or your own muscle memory, aim for v3 syntax. Everything you would expect works:

- The full core vocabulary: `flex`, `grid`, `p-5`, `mt-4`, `rounded-xl`, `text-2xl`, `font-bold`,
  `truncate`, `uppercase`, and so on.
- **Arbitrary values**: `w-[380px]`, `text-[13px]`, `tracking-[0.08em]`, `bg-[rgba(10,10,16,0.72)]`,
  `border-l-[#a970ff]`.
- **Opacity shorthand**: `bg-white/5`, `border-white/10`, `text-black/60`.
- **Variants**: `hover:`, `first:`, `last:`, `odd:` and friends, like `last:border-b-0` to drop the
  divider under the final row of a list.
- **Fractional spacing**: `gap-3.5`, `py-2.5`, `mt-0.5`.

What does *not* work:

- **Tailwind v4-only syntax.** New v4 utility names and v4 CSS conventions will not compile.
- **Stylesheet directives.** `@apply`, `@tailwind` and `theme()` are features of Tailwind's own
  build tooling, not of class scanning. In your css field, write plain CSS.
- **A config file.** There is no `tailwind.config` to extend. The default scale, colors and
  breakpoints are what you get - and arbitrary values cover everything the defaults do not.

## The border gotcha

This is the one that catches people. Tailwind normally ships a "preflight" stylesheet that, among
other things, sets every element's border style to solid with zero width. Overlabels deliberately
injects **no global reset** into your overlay - your overlay is your canvas, and a framework
reaching in to restyle every element would be rude.

The consequence: border utilities like `border`, `border-2` and `border-b` set a border *width* and
*color*, but browsers default every border's *style* to `none`. Width times none is invisible. Your
classes compiled fine, and nothing paints.

Two fixes, pick one:

Add the border-style utility next to your width utilities:

```html
<div class="border border-solid border-white/10">...</div>
```

Or put Tailwind's own reset line at the top of your css field once, and forget about it:

```css
* { border-style: solid; border-width: 0; }
```

The second one is what Tailwind itself does, and it makes every border utility in the template
behave exactly as the Tailwind docs describe. Keep both properties together: `border-style: solid`
alone would give every element a visible default-width border.

> [!TIP]
> Divider lines under list rows are the classic victim. If your `border-b border-white/5` rows show
> no lines, this is why.

## When to just write CSS

Always a fine answer. The css field is plain CSS with no compiler in the way, and several things
belong there rather than in classes:

- **Font families** for a font you loaded in the head field.
- **Keyframe animations** and anything with `@keyframes`.
- **The transparent-page basics** most overlays start with:

```css
html, body { margin: 0; background: transparent; }
```

Utilities and hand-written CSS coexist happily in one template - and because your stylesheet is
injected after the compiled one, you never have to fight the framework to win an argument.

> [!NOTE]
> One thing to keep out of the css field: `[[[tag]]]` values and `??` defaults. A stylesheet with no
> tags in it is injected once and left alone, which is the fast path described in
> [How an overlay renders](/help/rendering).
