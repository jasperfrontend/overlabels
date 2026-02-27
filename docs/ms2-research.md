# MS2 Research Checklist
> Before implementing Milestone 2 (External Systems: Foundations), gather the following.
> The goal is to arrive with enough concrete data that the abstraction layer design becomes
> an engineering problem rather than a guessing game.

---

## 1. Ko-fi — do this first
Ko-fi is the simplest possible external service and should anchor the entire design.

- [ ] Read the Ko-fi webhook docs and grab a **real example payload** (all event types: donation, subscription, shop order)
- [ ] Note the verification method (Ko-fi uses a simple token string — no OAuth)
- [ ] Map each payload field to what an Overlabels alert needs: what becomes a template tag? what drives the alert?
- [ ] Write down the minimum fields your `ExternalEvent` contract must have to fire an alert from a Ko-fi donation

> The Ko-fi payload shape basically *is* your v1 `ExternalEvent` contract. Design the interface around real data, not assumptions.

---

## 2. What does an alert actually need? (internal audit)
Before designing the normaliser, document what the existing Twitch pipeline produces.

- [ ] Look at what `AlertTriggered` broadcasts (html, css, data, duration, transition_in, transition_out)
- [ ] Look at what template tags a Ko-fi alert would need (`[[[event.amount]]]`, `[[[event.from_name]]]`, etc.)
- [ ] Write a short list: **"an external event must produce these fields to fire an alert"** — that list is your contract

---

## 3. Credential vault — what does each service actually need?
Research auth requirements per service so you don't over-engineer (or under-engineer) the vault.

| Service | Auth type | What to store |
|---|---|---|
| Ko-fi | Verification token (string) | One token per user |
| Throne | ? | ? |
| Patreon | OAuth 2.0 + refresh token | access token, refresh token, expiry |
| Fourthwall | ? | ? |
| Buy Me a Coffee | ? | ? |

- [ ] Fill in the table above for each service
- [ ] Flag any that need token refresh (Patreon definitely does — note the refresh flow)
- [ ] Decide: does the vault need to handle OAuth refresh natively in MS2, or can that be deferred to MS3+?

---

## 4. Service payload comparison
Once you have Ko-fi nailed, skim the webhook/event shapes for the next 2-3 services.

- [ ] **Throne** — webhook docs, payload shape, auth method
- [ ] **Patreon** — webhook docs, payload shape (pledge created/updated/deleted), OAuth scope needed
- [ ] **Fourthwall** — webhook docs, payload shape, auth method
- [ ] **Buy Me a Coffee** — webhook docs, payload shape (very similar to Ko-fi — confirm this)

For each one, note:
- Does it use webhooks or polling?
- What auth does it need?
- Is the payload shape close enough to Ko-fi that the same normaliser handles it, or does it need its own handler?

---

## 5. Routing design question
The generic webhook receiver needs to know which service sent a given request.

- [ ] How does each service identify itself? (separate endpoint per service? a `source` field in the payload? a header?)
- [ ] Does Ko-fi send a `Content-Type: application/x-www-form-urlencoded` or `application/json`? (it's form-encoded — verify this)
- [ ] Decide: one endpoint per service (`/api/webhooks/kofi`, `/api/webhooks/throne`) or one generic endpoint with routing logic?

---

## Deliverable
By the end of this research you should be able to answer:

1. What does `interface ExternalEvent` look like in PHP?
2. What columns does the `external_credentials` table need?
3. Does the vault need OAuth refresh support in MS2 or can it wait?
4. What does the webhook router look like (one endpoint vs. many)?
5. Which service is MS3, and why?

---

## Planned integration order (current thinking)
1. Ko-fi (MS3) — simplest, no OAuth, great for proving the architecture
2. Throne (MS4) — similar simplicity, growing fast
3. Buy Me a Coffee (MS4 or MS5) — Ko-fi twin, low effort once Ko-fi works
4. Fourthwall (MS5) — merch + tips, slightly more complex
5. Patreon (MS6) — OAuth dance, recurring payments, worth the effort but save it for later
6. Streamlabs — low priority, research last
7. YouTube superchats — polling-only outlier, see Sidequests in MILESTONES.md
