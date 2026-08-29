---
title: Show your latest donator from every source - Overlabels Tutorial
description: One name on screen, whichever of your five donation services it came from, using the latest() function and the automatic _at timestamps.
heading: Latest donator from any source
lead: You have Ko-fi and Streamlabs connected. Both track a latest donor. Which one is actually the most recent? This is what latest() is for, and it is the piece of Overlabels nobody finds on their own.
canonical: https://overlabels.com/help/tutorials/latest-donator
context: settings.integrations.index
keywords: tips, tipping service, money, income, revenue, get paid, dono
---

## The problem

Connect Ko-fi and you get `[[[c:kofi:latest_donor_name]]]`. Connect Streamlabs and you get
`[[[c:streamlabs:latest_donor_name]]]`. Connect all five donation services and you have five separate
"latest donor" values, each true about its own service and none of them true about your stream.

Put both on screen and your overlay shows two names, one of which is hours stale. Pick one and you are
ignoring the other service entirely.

You want one name: whoever donated most recently, wherever it came from.

## Every control knows when it changed

This is the part that makes it work, and it is easy to miss.

**Every control has an automatic `_at` companion** holding the Unix timestamp of when it last changed.
You never create it and you cannot forget to update it - it exists for every control, always:

```
c:kofi:donations_received       ->  12
c:kofi:donations_received_at    ->  1755381240
```

So "which service donated most recently" is just "which `_at` is the biggest number".

## Compare the counter, not the name

Read that heading above one more time: `_at` is when a control **changed**, which is not the same thing
as when a donation arrived.

If Marijke tips you twice in a row, `latest_donor_name` gets set to "Marijke", and then set to
"Marijke" again. The value never moved, so its `_at` never moved either. Compare
`latest_donor_name_at` and that second tip is invisible - your overlay keeps showing whoever last
donated under a *different* name, possibly from days ago.

`donations_received` has no such problem. It is a counter, so it goes up by one on every single
donation no matter who sent it, and its `_at` is therefore a true "when did this service last hear from
anyone". That is the timestamp to compare, and every donation service has it.

## latest() picks the winner

`latest()` takes pairs of **value, label** and returns the label that came with the highest value. Feed
it timestamps as the values and names as the labels, and it hands back the name attached to the most
recent timestamp.

Create an **Expression Control** with the key `newest_donor`:

```
latest(
  c.kofi.donations_received_at,       c.kofi.latest_donor_name,
  c.streamlabs.donations_received_at, c.streamlabs.latest_donor_name
)
```

Then use it like any other control:

```
Latest tip: [[[c:newest_donor]]]
```

One name. Always the right one.

> [!NOTE]
> Expressions use dots where tags use colons. `[[[c:kofi:latest_donor_name]]]` in a template is
> `c.kofi.latest_donor_name` in an expression. Same value, different place.

## All five services

The keys are identical across Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne, so extending
this is copy and paste:

```
latest(
  c.kofi.donations_received_at,       c.kofi.latest_donor_name,
  c.streamlabs.donations_received_at, c.streamlabs.latest_donor_name,
  c.fourthwall.donations_received_at, c.fourthwall.latest_donor_name,
  c.bmac.donations_received_at,       c.bmac.latest_donor_name,
  c.throne.donations_received_at,     c.throne.latest_donor_name
)
```

Only list the services you have actually connected. A service you have not connected has no controls,
so its terms resolve to nothing and it never wins - but leaving them out keeps the expression readable.

## The amount, and which service it was

`latest()` returns whichever label you paired with the winning timestamp, so the same trick gives you
anything about that donation. Three more expression controls:

```
newest_donor_amount:
latest(
  c.kofi.donations_received_at,       c.kofi.latest_donation_amount,
  c.streamlabs.donations_received_at, c.streamlabs.latest_donation_amount
)

newest_donor_currency:
latest(
  c.kofi.donations_received_at,       c.kofi.latest_donation_currency,
  c.streamlabs.donations_received_at, c.streamlabs.latest_donation_currency
)

newest_donor_service:
latest(
  c.kofi.donations_received_at,       "Ko-fi",
  c.streamlabs.donations_received_at, "Streamlabs"
)
```

Note that all of them compare the **same** timestamps - `donations_received_at` in every case. That is
deliberate. You are answering one question ("which service went last?") and then reading several
different facts off the winner. Comparing `latest_donation_amount_at` in one and
`latest_donation_message_at` in another could pick two different services and pair a name with someone
else's number - on top of both being the wrong kind of timestamp, for the reason above.

Then:

```
[[[c:newest_donor]]] tipped [[[c:newest_donor_amount|currency:EUR]]] via [[[c:newest_donor_service]]]
```

## Hiding it before the first donation

With nothing donated yet, every timestamp is the moment the control was created and the name is empty,
so you get a card with a blank in it. Wrap it:

```
[[[if:c:newest_donor]]]
  <div class="tip-card">
    <span class="tip-label">Latest tip</span>
    <span class="tip-name">[[[c:newest_donor]]]</span>
    <span class="tip-amount">[[[c:newest_donor_amount|currency:EUR]]]</span>
  </div>
[[[endif]]]
```

## Biggest instead of most recent

Same shape, different question. `argmax()` compares numbers rather than timestamps, so pairing amounts
with names gives you the largest single tip instead of the newest:

```
argmax(
  c.kofi.latest_donation_amount,       c.kofi.latest_donor_name,
  c.streamlabs.latest_donation_amount, c.streamlabs.latest_donor_name
)
```

> [!WARNING]
> That comparison is currency-naive. It compares 50 against 40 without caring that one is JPY and the
> other EUR. If your donations arrive in mixed currencies, `argmax()` will confidently pick the wrong
> one. `latest()` has no such problem, because a timestamp is a timestamp.

## Where next

- [Expression Controls](/help/expressions) - the whole math layer, including `oldest()`, `argmin()`,
  `clamp()` and the worked GPS distance example
- [Controls](/help/controls) - what controls are and how the service-managed ones work
- [Integration Presets](/help/integration-presets) - every auto-managed control across every service,
  searchable
