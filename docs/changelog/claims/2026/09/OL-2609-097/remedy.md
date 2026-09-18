## Remedy of OL-2609-097 - fix(privacy): platform-wide sweep - encrypt user tokens, scrub Twitch payloads, finish account erasure, put a clock on viewer data

**Remedied:** 2026-09-18
**Claim:** OL-2609-100

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-100 C1 records the `Http::fake` / `Storage::fake('images')` `beforeEach` in `tests/Feature/AccountDeletionForkedKitTest.php` that Surface omitted |
| F2 | RECORD | OL-2609-100 C2 records the same `beforeEach` in `tests/Feature/AccountDeletionRedirectTest.php` |
| F3 | RECORD | OL-2609-100 C3 and C4 restate the ordering: `app/Services/UserDeletionService.php:73` hands back the EventSub subscriptions before the transaction, by the docblock's own phase 1; only the three steps after the commit are what C16 describes |
| F4 | RECORD | OL-2609-100 C5 cites OL-2609-064 C1 for the narrowed scope; C6 records that `CLAUDE.md`'s StreamLabs "Scopes" line still names `donations.create`. `CLAUDE.md` itself is not edited here - an agent-delegated run does not amend it |
| F5 | RECORD | OL-2609-100 C7 and C8 supersede the two OL-2609-096 Unchanged lines for `private_metadata` and `supporter_email_hash` |
