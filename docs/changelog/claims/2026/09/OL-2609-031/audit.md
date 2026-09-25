## Audit of OL-2609-031 - docs(help): a guide for the living Twitch title

**Audited:** 2026-09-25
**Commit:** 116797d3f674ffd3dceb3ffab344a233c81fd411
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/help/pages/living-title.md:3 @116797d` `section: Live data`; `:8` `context: settings.title.show`; `:5` heading "Living Twitch title" is 19 chars; `:6` lead is 187 chars. File identical @HEAD (`git diff 116797d HEAD -- resources/help/pages/living-title.md` empty) |
| C2 | CONFIRMED | Catalogue tags in the Examples blocks (`followers_total`, `channel_game`, `subscribers_total`, `subscribers_points`, `followers_latest_user_name`, `goals_latest_description/current/target`) are `TAG_CATALOG` keys, `app/Services/TemplateDataMapperService.php:171,189,192,205,206,222-224 @116797d`. `c:twitch:*` keys (`follows_/subs_/raids_/bits_/chat_messages_/unique_chatters_this_stream`, `latest_cheerer_name`) provisioned in `app/Services/StreamSessionService.php:73-92 @116797d`, source `twitch` (`:347`). `c:kofi:total_received`/`latest_donor_name` in `KofiServiceDriver.php:124,128 @116797d`, and the same two keys in the StreamLabs, Fourthwall, BMAC and Throne drivers (`ThroneServiceDriver.php:164,168`). `c:checkin:latest_checkin_place`/`checkins_this_stream` in `CheckinServiceDriver.php:105,114` (service key `checkin`); `c:gps:session_distance` in `GpsServiceDriver.php:145` (service key `gps`). `c:wins` (`living-title.md:127`) and `c:day` (`:160`) are ones the reader is told to create. All keys still present @HEAD |
| C3 | CONFIRMED | `resources/dsl/dsl.json @116797d` `formatters`: `number` :26, `currency` :27, `distance` :34, `uppercase` :36; file unchanged since @116797d |
| C4 | CONFIRMED | `app/Services/Bot/BotChatAdminService.php:72-75 @116797d` match arms `title:set`, `title:show`, `title:resume`, `title:off`, and no other `title:` arm (`:76` default); table rows `living-title.md:213-216 @116797d` list the same four. File unchanged since @116797d |
| C5 | CONFIRMED | `php artisan test --filter=Help` @HEAD: 73 passed, 1 skipped (`HelpPageOgImageTest` "gives two help pages two different cards", resvg not installed). Includes `HelpTaxonomyTest` "keeps index.md filed the same way as the frontmatter", `HelpContextTest` "points every declared context at a route that exists" and "keeps any single context down to three pages". Link at `resources/help/pages/index.md:89-90 @116797d` under `### Live data` |

### Surface
Complete.

### Findings
None.

### Notes
- C5 was run at HEAD, not at 116797d; `living-title.md` is byte-identical at both, and `index.md` has since been edited by 3f1ff439, fc434739, 64a7e3c6 (OL-2609-074), f8361f19 and 1b641c7c, none of which touch the living-title line (now `index.md:93 @HEAD`).
- The skipped test needs resvg and does not bear on any assertion C5 names.
- `[[[c:goal ?? no goal yet]]]` (`living-title.md:48 @116797d`) is a control the reader is not told to create, but it sits in the syntax list under "What you can put in it", not in the Examples section C2 covers.
- `[[[counter:wins]]]` (`living-title.md:128 @116797d`) is in prose under Examples, not in an example block; it is a bot-reply tag (`app/Services/Bot/BotCommandResolver.php:40 @116797d`), not a title-template tag.
