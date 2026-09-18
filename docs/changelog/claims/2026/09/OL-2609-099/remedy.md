## Remedy of OL-2609-099 - feat(bot): !forgetme, and a one-off notice on a viewer's first interaction

**Remedied:** 2026-09-18
**Claim:** OL-2609-101

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-101 C3 names `/manage`, `/followage` and `/accountage` at `routes/api.php:237-239` as bot command endpoints with no `{login}` either |
| F2 | FIXED | `tests/Feature/ViewerErasureTest.php` "deletes the viewer from every table that is keyed to them" now creates and counts a `ListAppendHistory` row; failed then passed, verified against `ViewerErasureService.php:73` stubbed to `0` and then restored |
| F3 | RECORD | OL-2609-101 C4 places the `source_managed` 403 on `OverlayControlController::update()`/`setValue()`; C5 notes the same wording in the service's own comment |
| F4 | RECORD | OL-2609-101 C6, C7, C8 and C9 state the three `isSuppressed()` call sites, the two surfaces that rewrite erased data, and the promise those limit |
