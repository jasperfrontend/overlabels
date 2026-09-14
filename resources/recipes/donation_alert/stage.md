---
name: Donation stage
type: static
author: Overlabels
---

# Donation stage

A small counter in the corner: how many tips have come in through your donation service, and who sent the latest one. The Donation alert fires on this overlay, so its look lives here too - an alert renders into the DOM of the static overlay it is targeted at.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

The `{{service}}` in the tags below is a recipe ingredient. The install writes the donation service you picked in its place, so the account holds `c:kofi:donations_received` or `c:throne:donations_received`, never the placeholder.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Empty.

### `html`

The markup.

```html
<div class="donation-stage">
  <p class="donation-count"><span class="donation-number">[[[c:{{service}}:donations_received]]]</span> tips</p>
  [[[if:c:{{service}}:latest_donor_name]]]
  <p class="donation-latest">Latest from [[[c:{{service}}:latest_donor_name]]]</p>
  [[[endif]]]
</div>
```

### `css`

The stylesheet. Tags work in here too.

```css
html, body {
  margin: 0;
  background: transparent;
  color: #f4f4f5;
  font-family: system-ui, sans-serif;
}

.donation-stage {
  position: absolute;
  top: 1rem;
  left: 1rem;
  padding: 0.75rem 1rem;
  background: rgba(9, 9, 11, 0.75);
  border-left: 4px solid #7ff3d0;
}

.donation-count {
  margin: 0;
  font-size: 1.25rem;
  line-height: 1.2;
}

.donation-number {
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

.donation-latest {
  margin: 0.25rem 0 0;
  font-size: 0.9rem;
  color: #a1a1aa;
}

/* The Donation alert renders into this overlay's DOM, so its look is here. */
.alert {
  position: absolute;
  bottom: 1rem;
  left: 1rem;
  max-width: 60%;
  padding: 1rem 1.25rem;
  background: #7ff3d0;
  color: #040d16;
  font-size: 2rem;
  line-height: 1.2;
}

.alert p {
  margin: 0;
}

.alert .donation-message {
  margin-top: 0.5rem;
  font-size: 1.25rem;
  font-style: italic;
}
```

## Controls

This overlay defines no controls of its own.

## Requirements

This overlay reads live data from the donation service picked at install. The product install connects it for you; you authorize it on its settings page.
