# Security Policy

Thanks for taking the time to look at Overlabels' security. Streamers trust this software to render content live to their audience, so we take vulnerability reports seriously.

## Reporting a Vulnerability

Please report security issues privately. Do **not** open a public GitHub issue for anything you suspect is a vulnerability.

- Email: jasper@emailjasper.com
- Subject line: `[Overlabels Security] <short description>`

What to include:

- A description of the issue and the impact you believe it has
- Steps to reproduce, or a proof-of-concept
- The affected version, commit SHA, or URL if relevant
- Whether you'd like to be credited in the fix notes (and how)

What to expect:

- Acknowledgement within 3 business days
- An initial assessment within 7 business days
- Coordinated disclosure - we'll agree on a timeline before anything is published

## Scope

In scope:

- The Overlabels web application (this repository)
- The overlay rendering pipeline and access-token model
- External integration webhooks (Ko-fi, StreamLabs, StreamElements, etc.)
- The Twitch EventSub webhook handler
- The companion bot and mobile clients in their respective repos

Out of scope:

- Vulnerabilities in third-party services we integrate with (please report those to the respective vendors)
- Self-XSS that requires the victim to paste attacker-controlled content into their own controls or templates
- Rate-limiting concerns on endpoints that are already rate-limited
- Reports generated solely by automated scanners without a working proof-of-concept

## Our Approach

Overlabels is open source. The codebase, including its security-relevant paths, is readable by anyone. We document defenses inline near the code they protect, rather than maintaining a separate catalogue of mitigations. If you're auditing the project, the source is the source of truth.

A few principles that shape the design:

- Overlay access tokens live in URL fragments and are never sent to the server in plaintext; the server stores `sha256(token)`
- Template tag substitution is single-pass by design, so user-supplied content cannot smuggle in tags that get re-evaluated
- External webhook payloads are verified per-driver before any side effects
- Admin and impersonation paths are gated by middleware and audited append-only

If you find a gap between these principles and what the code actually does, that's exactly the kind of report we want.

## Thanks

To everyone who has poked at this thing in good faith - whether you found something or not, the project is better for it.
