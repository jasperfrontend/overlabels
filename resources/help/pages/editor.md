---
title: The Code Editor - Autocomplete and Bang Snippets in Overlabels
description: How the template editor completes your tags as you type, knows your own controls and Lists, and expands !chat, !subs and !kofi into working blocks.
heading: The code editor
lead: Type [[[ and the editor offers every tag you can use, including your own controls and Lists. Type !chat and it writes the whole loop for you.
canonical: https://overlabels.com/help/editor
---

The editor on the Code tab is CodeMirror, the same editor behind a lot of browser-based tools. It
knows Overlabels tags, so you rarely have to remember an exact name or look one up on the Tags tab.

## Tag autocomplete

Type `[[[` and a list opens. Keep typing to narrow it down, use the arrow keys to pick, and press
Enter or Tab to accept. The editor closes the brackets for you, so accepting `followers_total` gives
you [[[followers_total]]] with the cursor ready after it.

The list is built for your account, not a generic one:

- **Every static tag**, grouped the way the Tags tab groups them. Each one shows its type, and the
  description appears beside the list while it is selected.
- **Your controls.** The template's own controls come up as `c:name`, and controls from a connected
  service come up under that service, like [[[c:kofi:donations_received]]]. Connect Ko-fi and the
  Ko-fi controls appear the next time you open the editor.
- **Your Lists**, with every way to read one: [[[c:list:donors]]], `:count`, `:first`, `:last`,
  `:random`, `:empty` and `:json`.
- **The block keywords** - `if:`, `elseif:`, `else`, `endif`, `foreach:` and `endforeach`. Accepting
  `if:` or `foreach:` leaves the cursor inside the tag so you can carry on typing the condition or
  the loop.
- **Event tags** such as [[[event.user_name]]], but only when you are editing an alert. A static
  overlay never receives an event payload, so they are not offered there.

A few places are smarter about what fits:

- After a `|` the list switches to the formatters, with a hint for the argument each one takes. See
  [Formatting Pipes](/help/formatting) for what they do.
- After `foreach:` the list is the things you can loop over. Accepting `subscribers` writes
  `subscribers as sub`, so the alias is set up for you.
- Inside a loop, the alias completes to that item's fields. Type `[[[sub.` inside a subscribers loop
  and you get `sub.user_name`, `sub.tier`, `sub.is_gift` and the rest; inside a chat loop `msg.` gives
  you `msg.author`, `msg.html` and the others. The loop tags `loop.index`, `loop.first`,
  `loop.last` and `loop.count` only show up while you are inside a loop, because that is the only
  place they mean anything.

Autocomplete works on all three tabs - HEAD, BODY and CSS - and inside a `<style>` block in the
BODY, so [[[c:accent_color]]] completes in a CSS rule the same way it does in HTML.

> [!TIP]
> The list only knows tags that exist. If you type one that is not offered, check the spelling:
> a tag that does not exist renders as nothing, without an error, so the editor is the earliest
> place a typo shows up.

## Bang snippets

A bang is a shortcut for a whole block. Type `!` followed by a letter or two and the list shows the
snippets; accept one and it expands in place, indented to match the line you are on.

```html
!chat
```

becomes

```html
[[[foreach:chat as msg]]]
  <div class="chat-line"><span style="color: [[[msg.color]]]">[[[msg.author]]]</span>: [[[msg.html]]]</div>
[[[endforeach]]]
```

The snippets that are always there:

| Bang | What it writes |
|---|---|
| `!chat` | A live chat feed, one line per message, in the chatter's colour with emotes rendered |
| `!subs` | Your most recent subscribers, one row each |
| `!followers` | Your most recent followers, one row each |
| `!goals` | Your active goals with their progress |
| `!if` | An `if` block. The cursor lands in the condition; Tab moves it to the body |
| `!ifelse` | An `if` block with an `else` branch |
| `!foreach` | An empty loop. The alias appears twice and the two are linked, so renaming it once renames both |

And one per donation service you have connected: `!kofi`, `!streamlabs`, `!fourthwall`, `!bmac`
and `!throne` each write a block showing the latest donor, the amount and their message from that
service's controls. A bang for a service you have not connected is not offered, so everything in
the list works on your account.

Some snippets have fields - the highlighted spots the cursor jumps to. Tab moves to the next field,
Shift+Tab goes back, and Escape leaves the snippet where it is. Once you have typed past the last
field the snippet is just text.

> [!NOTE]
> A bare `!` on its own does not open the list, so an exclamation mark at the end of a sentence
> stays out of your way. If you want the full list without typing a letter, press Ctrl+Space.
