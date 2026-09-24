## Remedy of OL-2609-125 - feat(shortcuts): G then a letter jumps to a main navigation page, and the Ctrl+K dialog groups shortcuts into sections

**Remedied:** 2026-09-24
**Claim:** OL-2609-131

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2609-131 C1 records the `clearPending()` call on last-listener unmount in `useKeyboardShortcuts.ts` |
| F2 | RECORD | OL-2609-131 C2 and C3 record the `Shortcut` and `Resolution` exports; C4 names their only importer |
