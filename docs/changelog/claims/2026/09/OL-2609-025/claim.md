## OL-2609-025 - docs(twitch): record that bot replies depend on moderator status, not the channel:bot grant

**Shipped:** 2026-09-07
**Commit:** `git log --grep=OL-2609-025`

### Surface
- `CLAUDE.md` - in "Chat notices and Plus Points", the "Corollary worth knowing" bullet is replaced by a confirmed finding and a decision

### Claims
- **C1** [code] `CLAUDE.md` no longer contains the phrase "Not investigated further".
- **C2** [code] `resources/js/pages/settings/integrations/index.vue` contains the instruction `/mod overlabels`; it is not in the diff.
- **C3** [code] `TwitchScopeService::REQUIRED_SCOPES` still contains `channel:bot`; it is not in the diff.
- **C4** [unverified] The bot container on production runs with `TWITCH_CLIENT_ID` equal to the main app's `TWITCHBOT_CLIENT_ID`, and `bot.js` sends every reply with `sendChatMessageAsApp`, an app-token send on that app.
- **C5** [unverified] Twitch's Send Chat Message reference requires an app-token send to come from an application holding `user:bot` and `channel:bot`, or from a user with moderator privileges in the broadcaster's chat.
- **C6** [unverified] On 2026-09-07 the project owner confirmed the bot account is a moderator in the jasperdiscovers and ticanuk channels and not in casualelephant's; retained bot logs (from 2026-09-06) show replies only in jasperdiscovers and no send errors.

### Unchanged
- No code moves. The bot's send path, the two Twitch apps, the `channel:bot` scope in the login and the settings-page instruction are all as they were; only the written record changes.
