---
title: Overlay Access Tokens - how overlay URLs stay private
description: "How Overlabels overlay tokens work: 256 bits of randomness, hashed on storage, carried in the URL fragment so they are never sent to a server. Expiry, IP allowlists, access logs, and what to do when one leaks."
heading: Overlay Access Tokens
lead: The 64-character token at the end of your overlay URL is the thing that makes the overlay yours. Here is what it is, why it lives after the # instead of in the path, and what to do the moment you think one has leaked.
canonical: https://overlabels.com/help/tokens
context: tokens.index
---

Your overlay URL looks like this:

```
https://overlabels.com/overlay/my-overlay#7f3a9c...  <- 64 hex characters
```

Everything before the `#` is public and boring. Everything after it is the credential. This page explains
what that credential is and how it is handled, because "paste this URL into OBS" deserves a better answer
than "trust us".

## What the token is

When you generate a token, Overlabels asks the operating system for **32 random bytes** and renders them
as hexadecimal. That is 64 characters and **256 bits of randomness**. There is no pattern, no encoded user
ID, no timestamp inside it. It is not guessable, and it is not derived from anything about you.

The first 8 characters are stored separately as a **prefix**, purely so your [tokens page](/tokens) can
show you which token is which without ever holding the real thing.

## Why it lives after the `#`

This is the part worth understanding, because it is the whole security design in one sentence:

> **Browsers never send the URL fragment to the server.**

The fragment - everything after the `#` - is a client-side-only construct. It does not appear in the HTTP
request line, so it never lands in a server access log, a proxy log, a CDN log, or a `Referer` header on
an outbound request. A page that logs every URL it serves still never sees your token.

The overlay page reads it with `window.location.hash`, checks it is 64 characters, and hands it to the
renderer, which sends it to the API deliberately and only for the calls that need it.

> [!NOTE]
> This is why you cannot "fix" a broken overlay by putting the token in the path. `/overlay/my-overlay/7f3a9c...`
> would work exactly once and then sit in a log file forever.

## What the server stores

Not the token. The server stores **`sha256(token)`**, and the column is hidden from every model
serialisation by default.

That has a consequence you will notice: the plain token is shown to you **once**, at the moment you
generate it. Overlabels cannot show it to you again later, because it genuinely does not have it. If you
lose it, you generate a new one - it is two clicks, and the old one keeps working until you revoke it.

When a token is used, Overlabels hashes what it was given and looks for a matching row. Same input, same
hash, match. A copy of the database contains no usable overlay credentials.

## Controls on a token

Every token carries a few optional restrictions, all checked on each use:

| Control        | What it does                                                                 |
|----------------|------------------------------------------------------------------------------|
| **Active**     | The kill switch. An inactive token fails to resolve at all                    |
| **Expiry**     | Set `expires_at` and the token stops working after that moment                |
| **IP allowlist** | Restricts use to specific client IP addresses                              |
| **Abilities**  | Comma-separated scope list. Unset means no restriction                        |

> [!WARNING]
> **The IP allowlist is an exact address match, not a CIDR range.** `203.0.113.7` matches only
> `203.0.113.7`. Writing `203.0.113.0/24` will match nothing at all and lock your own overlay out. It is
> also only enforced when the request has a client IP to check. Most home connections get a new address
> periodically, so this is a feature for fixed-IP setups, not a default worth turning on.

## Access logging

Each successful use increments an access counter and stamps `last_used_at`, so your tokens page can show
you a token that has not been touched in six months and is safe to clean up.

Overlabels also writes an access log row per use: which template slug was loaded, the client IP, the user
agent, and when. If you ever need to answer "is something using this token that should not be", that is
the record that answers it.

## If a token leaks

Showing your overlay URL on stream is the usual way this happens - a browser source dragged into frame, an
alt-tab, a screenshot of your OBS setup.

Do this:

1. Go to [your tokens page](/tokens) and **revoke** the exposed token. It stops working immediately.
2. Generate a new one.
3. Update the browser source in OBS. Only the part after the `#` changes.

Revoking one token does not affect any other token, so a per-scene or per-machine token is a reasonable
habit if you want a small blast radius.

**What could someone actually do with a leaked token?** Read your overlay and the live data it renders -
follower counts, your latest subscriber, whatever your controls hold. A token identifies you and can only
ever reach your own data, never another user's. It is not a login: it cannot change your Twitch account,
your templates, or your settings.

## Public overlays

Not every overlay needs a token. An overlay you have marked **public** can be shared with a link that
carries no credential at all, which is what you want for a Discord preview, a portfolio, or showing
someone a design.

The trade is exactly what it sounds like: a public link is public. Use tokens for the overlay that is
actually in your scene, and public links for showing the thing off.

## Related

- [Lists in realtime](/help/lists-realtime) - the same token authenticates the Lists API
- [How an overlay renders](/help/rendering) - where the token is used in the boot sequence
