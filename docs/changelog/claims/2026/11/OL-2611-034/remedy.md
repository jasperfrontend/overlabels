## Remedy of OL-2611-034 - fix(eventsub): a refused recreate stays on record, and failed subscriptions are repaired one by one instead of wiped

**Remedied:** 2026-11-22
**Claim:** OL-2611-035

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-035 C1 and C2 cite OL-2611-032 C10 and C11 as reversed by OL-2611-034 C7 and the renamed `keeps not_found_on_twitch rows ...` test |
| F2 | RECORD | OL-2611-035 C3 restates what `names an account with refused rows and never retries it` actually asserts; C4 records that "one line per account" is code-only |
