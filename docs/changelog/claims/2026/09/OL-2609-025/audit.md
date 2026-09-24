## Audit of OL-2609-025 - docs(twitch): record that bot replies depend on moderator status, not the channel:bot grant

**Audited:** 2026-09-24
**Commit:** 053b77c9eca91ee770f6de3f88d5ec413d4b0095
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `git show 053b77c:CLAUDE.md \| grep "Not investigated further"` returns nothing; the diff removes it from the "Corollary worth knowing" bullet (old `CLAUDE.md:336 @053b77c~1`). Also absent @HEAD. |
| C2 | CONFIRMED | `resources/js/pages/settings/integrations/index.vue:342 @053b77c` - "Run `<code>/mod overlabels</code>` in your Twitch chat..."; the file is not in the diff. @HEAD the line is gone from `index.vue` and lives in `resources/js/pages/settings/integrations/bot.vue:70,93 @HEAD`, moved by e06e1fff (OL-2609-069, disclosed as its C15). |
| C3 | CONFIRMED | `app/Services/TwitchScopeService.php:46 @053b77c` - `'channel:bot'` inside `REQUIRED_SCOPES` (opens line 23); the file is not in the diff. Same at `:46 @HEAD`; the file was later touched by f58bff84 (OL-2609-026), which leaves `channel:bot` in place. |
| C4 | UNVERIFIABLE | tagged [unverified]; `bot.js` and the bot container's env belong to the bot repo. This repo's `config/deploy.yml @053b77c` defines no bot-container env, and `sendChatMessageAsApp` appears in-tree only in prose (`docs/changelog/changelog-2026-05.md:421 @053b77c`). |
| C5 | UNVERIFIABLE | tagged [unverified]; third-party API documentation. |
| C6 | UNVERIFIABLE | tagged [unverified]; owner statement and production bot logs. |

### Surface
Complete. `git show --stat --format= 053b77c` lists `CLAUDE.md` (listed in Surface) and the claim file (exempt).

### Findings
None.

### Notes
- Unchanged is confirmed: the diff touches only `CLAUDE.md` and the claim file, so no send path, scope list or settings page is in it.
- `app/Services/TwitchScopeService.php:40-45 @053b77c` (still `:44 @HEAD`) has a comment saying that without `channel:bot` "Twitch returns 401 on bot replies". The new `CLAUDE.md:336 @053b77c` bullet calls that scope "decorative for sends". The comment was left as it was, which matches Unchanged, so the tree now holds two statements that disagree.
- The new bullet puts "re-auth fixes the 401" in quote marks and attributes it to the May 16th changelog. That is a paraphrase: `docs/changelog/changelog-2026-05.md:425 @053b77c` says replies "return 401 from Helix until they refresh scopes". The entry predates claim IDs, and no claim here asserts the quote.
- `CLAUDE.md:388 @HEAD` still says "`/settings/integrations` already tells streamers to run `/mod overlabels`". Since OL-2609-069 that instruction lives on `/settings/integrations/bot`.
- No [test] claims, so no tests were run.
