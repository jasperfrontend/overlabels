---
name: Donation alert
type: alert
author: Overlabels
---

# Donation alert

Who tipped and how much, with their message if they left one. Plays a sound, speaks the line after the sound, and posts it to chat through the Overlabels bot.

An Overlabels **alert overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is an alert overlay: it renders when an event fires and is removed after its duration.

The `event.*` tags it reads are the same for every donation service, so one alert serves all five: the product install writes a trigger per service, and each one waits until that service is connected. Its look lives in the Donation stage's stylesheet, because an alert renders into the DOM of the static overlay it is targeted at.

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

Empty.

## Controls

This overlay defines no controls of its own.

## Alert behaviour

- Plays a sound on fire: <https://cdn.freesound.org/previews/616/616694_12364629-hq.mp3>
- Speaks via text to speech after a 3000ms delay: `[[[event.from_name]]] tipped [[[event.formatted_amount]]]`
- Posts to Twitch chat via the @overlabels bot: `[[[event.from_name]]] tipped [[[event.formatted_amount]]]`

## Requirements

This overlay fires on a donation from any of the five donation services, Streamlabs, Ko-fi, Buy Me a Coffee, Fourthwall or Throne, once that service is connected. The product install writes the five triggers and targets the Donation stage for you.
