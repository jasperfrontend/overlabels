## Remedy of OL-2611-038 - feat(eventsub): floods become numbers - the last hundred kept, the rest counted

**Remedied:** 2026-11-26
**Claim:** OL-2611-039

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-039 C1 and C2 record that counted events skip `processStoredEvent()` and that a tick calls neither `refreshCachesForEvent()` nor `LivingTitleService::schedule()`, narrowing OL-2611-036 Unchanged; C3 leaves whether that is intended to Jasper |
| F2 | RECORD | OL-2611-039 C4 records that `FloodGate::withTail()` (`app/Services/Floods/FloodGate.php:318-327`) skips the kept-list write on lock timeout, correcting OL-2611-038 C5 |
| F3 | RECORD | OL-2611-039 C5 and C6 record `FloodTick::handle()`'s missing-user path and `FloodGate::forget()` |
