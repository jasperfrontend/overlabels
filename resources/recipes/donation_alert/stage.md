---
name: Donation stage
type: static
author: Overlabels
---

# Donation stage

A small counter in the corner: how many tips have come in across every donation service you have connected, and who sent the latest one, wherever it came from. The Donation alert fires on this overlay, so its look lives here too - an alert renders into the DOM of the static overlay it is targeted at.

An Overlabels **static overlay** by Overlabels.

Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that resolve against live stream data and update over WebSockets. There is no JavaScript in an overlay - the template language does the work. The complete language specification is at <https://overlabels.com/llms.txt>; read it first if you are not already familiar with the syntax.

This is a static overlay: it stays on screen and updates continuously.

The markup names no service. Two expression controls of the overlay's own do the reading, across all five donation services, the way the Latest donator tutorial builds them by hand: `tips_total` adds up every service's `donations_received`, and `newest_donor` hands back the `latest_donor_name` paired with the newest `donations_received_at`. A service that is not connected has no controls, so it adds nothing and never wins.

## Source

The three fields below are the entire overlay. Nothing else is rendered.

### `head`

Empty.

### `html`

The markup.

```html
<div class="donation-stage">
  <p class="donation-count"><span class="donation-number">[[[c:tips_total]]]</span> tips</p>
  [[[if:c:newest_donor]]]
  <p class="donation-latest">Latest from [[[c:newest_donor]]]</p>
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

Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These 2 are defined by the overlay itself and are recreated, with the default values shown, for anyone who installs it.

| Tag | Type | Label | Default | Referenced in source |
|---|---|---|---|---|
| `[[[c:tips_total]]]` | expression | Tips across every service |  | yes |
| `[[[c:newest_donor]]]` | expression | Latest donor from any service |  | yes |

### Control detail

- `c:tips_total` - Every connected service's tip counter added up. A service that is not connected has no counter and adds nothing.
  - expression: `sum(c.streamlabs.donations_received, c.kofi.donations_received, c.bmac.donations_received, c.fourthwall.donations_received, c.throne.donations_received)`
- `c:newest_donor` - The name paired with the newest tip counter timestamp, so the service that last heard from anyone wins. The counter's timestamp, not the name's: two tips in a row from the same person leave the name unchanged, the counter never.
  - expression: `latest(c.streamlabs.donations_received_at, c.streamlabs.latest_donor_name, c.kofi.donations_received_at, c.kofi.latest_donor_name, c.bmac.donations_received_at, c.bmac.latest_donor_name, c.fourthwall.donations_received_at, c.fourthwall.latest_donor_name, c.throne.donations_received_at, c.throne.latest_donor_name)`

Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in seconds of when it last changed.

## Requirements

This overlay reads live data from whichever of the five donation services you connect: **Streamlabs**, **Ko-fi**, **Buy Me a Coffee**, **Fourthwall** and **Throne**. The product install connects the one you pick first; the rest connect on the Integrations settings page and join the count the moment they do. Connecting a service provisions its controls automatically - they are not part of the install, and they are account-wide rather than per-overlay. Per service, the two expression controls above read `donations_received` and `latest_donor_name`.
