## Remedy of OL-2611-019 audit

**Remedied:** 2026-11-16, in the session that wrote OL-2611-019 (Jasper's call, not a cold `/remy` run)
**New claim:** OL-2611-020

| Finding | Outcome | Where |
|---------|---------|-------|
| F1 | RECORD | OL-2611-020 C1 restates C13: no hand-edit check; the only comparison is the already-moved check |
| F2 | FIXED | New migration `2026_11_16_210000_drop_follower_bowling_aliases_left_behind_by_the_redesign_move.php` drops the aliases on every install that still lists `bot_aliases`, skip paths included; two new tests in `FollowerBowlingRedesignMigrationTest`. The shipped migration is not edited: it had already run on deploy |
| F3 | RECORD | OL-2611-020 C7-C9 name OL-2609-137 C7, C14 and its first Unchanged line, and OL-2609-038 C32 |
