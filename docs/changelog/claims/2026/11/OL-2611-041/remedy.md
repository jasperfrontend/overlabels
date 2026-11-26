## Remedy of OL-2611-041 - feat(reports): the Overlabels Overload Report

**Remedied:** 2026-11-26
**Claim:** OL-2611-042

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-042 C1 discloses `resources/help/pages/bot/commands.md` (the `!ol report stop` row, line 130) |
| F2 | FIXED | `.github/workflows/deploy.yml:148` ends with a backslash continuation instead of a literal `\n`; the extracted secrets loop run under `bash -e` printed `n=''` before and no such line after (no Pest test can reach a workflow file) |
| F3 | RECORD | OL-2611-042 C4 restates C12: the print button replaces the PDF link only when no Gotenberg URL is configured; a failing printer keeps the link and answers 503 |
| F4 | RECORD | OL-2611-042 C5 restates the order: in `FloodGate::apply()` the note runs before the tick's broadcast and `AlertBursts::grow()` / `reannounce()` |
| F5 | FIXED | `config/services.php:162` `gotenberg` block moved above the Sqids comment, which again sits on `map_slug` (comment position only, no test possible; pint passes) |
