---
name: Buy Me a Coffee alert
type: alert
author: Overlabels
---

# Buy Me a Coffee alert

Who supported you and how much, with their message if they left one. Plays a sound, speaks the line after the sound, and posts it to chat through the Overlabels bot.

An Overlabels **alert overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is an alert overlay: it renders when an event fires and is removed after its duration. It is targeted at nothing in particular, so it renders inside every static overlay you have in OBS, on top of whatever is there.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Empty.

### `html`

The markup.

```html
<div class="alert donation-alert">
  <p>[[[event.from_name]]] tipped [[[event.formatted_amount]]]</p>
  [[[if:event.message]]]
  <p class="donation-message">[[[event.message]]]</p>
  [[[endif]]]
</div>
```

### `css`

The stylesheet. The alert renders into another overlay's DOM, so everything it needs to look like something is here.

```css
.donation-alert {
  position: absolute;
  bottom: 1rem;
  left: 1rem;
  max-width: 60%;
  padding: 1rem 1.25rem;
  background: #7ff3d0;
  color: #040d16;
  font-family: system-ui, sans-serif;
  font-size: 2rem;
  line-height: 1.2;
}

.donation-alert p {
  margin: 0;
}

.donation-alert .donation-message {
  margin-top: 0.5rem;
  font-size: 1.25rem;
  font-style: italic;
}
```

## Controls

This overlay defines no controls of its own.

## Alert behaviour

- Plays a sound on fire: <https://cdn.freesound.org/previews/616/616694_12364629-hq.mp3>
- Speaks via text to speech after a 3000ms delay: `[[[event.from_name]]] tipped [[[event.formatted_amount]]]`
- Posts to Twitch chat via the @overlabels bot: `[[[event.from_name]]] tipped [[[event.formatted_amount]]]`

## Requirements

This overlay fires on a Buy Me a Coffee donation once Buy Me a Coffee is connected. The product install writes the trigger for you. Buy Me a Coffee also sends memberships, extras, commissions and wishlist purchases; the alert's Triggers tab switches those on for the same alert.
