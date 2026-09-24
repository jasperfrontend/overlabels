## Remedy of OL-2609-129 - feat(lists): the Lists pages live at /lists, out from under /dashboard

**Remedied:** 2026-09-24
**Claim:** OL-2609-132

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-132 C1 and C2 restate `ListControllerTest`'s actual coverage: preservation on POST `/lists` only, the `lists/*` exemption untested |
| F2 | RECORD | OL-2609-132 C3 records C8 as shipped at `290db402` and that it was false; C4 records the in-place rewrite in `276d3ab4` and that its text holds at HEAD |
