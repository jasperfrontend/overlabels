## Remedy of OL-2609-142 - fix(products): restore the three saved-look guard tests, and restate what OL-2609-141 pinned

**Remedied:** 2026-09-27
**Claim:** OL-2609-143

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-143 C1 restates that `ProductChatEmoteBubblesTest.php:274` directly asserts the raw ordered `product.designer.groups` array, not "the joined card copy" (a frontend computed in `show.vue` this test never touches); per-group key membership is still pinned by no test |
| F2 | RECORD | OL-2609-143 C2 restates that OL-2609-141 `remedy.md`'s F3 row should read RECORD, not FIXED, since the guards it pins never regressed and the restored tests were never seen red |
