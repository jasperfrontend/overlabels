## OL-2611-003 - feat(chat): let chatters redact their message on the chat overlay with !hide

**Shipped:** 2026-11-07
**Commit:** `git log --grep=OL-2611-003`

### Surface
- `resources/js/utils/chatRedact.ts` - new file: `toRedactCommand()`, `redactMessage()`, `redactedHtml()`
- `resources/js/utils/chatRedact.test.ts` - new file
- `resources/js/utils/ircParser.ts` - optional `redacted` field on `ChatMessage`
- `resources/js/composables/useTwitchChat.ts` - `setRedactCommand()`; `handleLine()` applies `redactMessage()`; `flush()` skips emote occurrences of redacted messages
- `resources/js/composables/useTwitchChat.test.ts` - new `useTwitchChat redacted messages` describe block
- `resources/js/utils/chatSlots.ts` - new `chat.N.redacted` slot
- `resources/js/components/OverlayRenderer.vue` - `chatMessageHtml()` routes redacted messages to `redactedHtml()`; new `loadRedactedFont()`; reads `json.chat_redact_command`
- `app/Models/User.php` - `chat_redact` preference default, `CHAT_REDACT_COMMAND_PATTERN`, `chatRedact()`, `chatRedactCommand()`
- `routes/settings.php` - `GET /settings/chat` passes `chatRedact`; `PATCH /settings/chat` accepts optional `redact_enabled` / `redact_command`
- `resources/js/pages/settings/Chat.vue` - "Let chatters hide a message" section with id `redacted-messages`
- `app/Http/Controllers/OverlayTemplateController.php` - `chat_redact_command` in the render payload
- `app/Http/Controllers/ProductController.php` - `chat_redact` prop on `manage()`
- `resources/js/pages/products/show.vue` - card showing On/Off, linking to `/settings/chat#redacted-messages`
- `tests/Feature/ChatRedactSettingTest.php` - new file

### Claims
- **C1** [code] `User::PREFERENCE_DEFAULTS['chat_redact']` is `['enabled' => false, 'command' => 'hide']`.
- **C2** [code] `User::chatRedactCommand()` returns null when `chatRedact()['enabled']` is false, else the command word.
- **C3** [code] `chat_redact` is not in `User::$appends`.
- **C4** [code] The render payload built in `OverlayTemplateController` carries `chat_redact_command` from `$user->chatRedactCommand()`.
- **C5** [code] `PATCH /settings/chat` validates `redact_enabled` and `redact_command` with `sometimes`, and writes each preference only when its key is present in the validated input.
- **C6** [code] `PATCH /settings/chat` refuses a `redact_command` equal to `$user->plinkoCommand()` when `PlinkoSettingsController::installedFor()` is true.
- **C7** [code] `ProductController::manage()` passes `chat_redact` as `$user->chatRedact()` only when an instance exists and the manifest slug is `twitch-chat-overlay`, else null.
- **C8** [code] In `useTwitchChat.handleLine()`, message taps receive the parsed message before `redactMessage()` runs, and `shouldHideMessage(message, filters)` receives the redacted message.
- **C9** [code] `redactMessage()` returns the input object unchanged unless the trimmed text starts with `!<command>` (case-insensitive) followed by whitespace and a non-empty remainder.
- **C10** [code] A redacted message has `emotes: []` and `redacted: true`, and `text` is the remainder without the command.
- **C11** [code] `useTwitchChat.flush()` adds no `emoteOccurrences()` for a message with `redacted` set.
- **C12** [code] `OverlayRenderer.chatMessageHtml()` returns `redactedHtml(m.text)` for a redacted message and never calls `emoteParser.parseEmotes()` for it.
- **C13** [code] `redactedHtml()` entity-escapes `& < > " '`, maps every grapheme outside `U+0021-U+007E` and `U+00A1-U+024F` (other than whitespace) to `x`, and wraps the result in `<span class="ol-redacted">`.
- **C14** [code] `loadRedactedFont()` adds one `<link>` to `https://fonts.bunny.net/css?family=redacted:400&display=block` and one `<style>` setting `.ol-redacted` to `font-family: 'Redacted'`, and is only called when `toRedactCommand(json.chat_redact_command)` is non-empty.
- **C15** [code] `chatSlots()` emits `chat.N.redacted` as `'1'` or `''`.
- **C16** [test] `chatRedact.test.ts` covers `toRedactCommand`, `redactMessage` and `redactedHtml` as described in C9, C10 and C13.
- **C17** [test] `useTwitchChat.test.ts` "still shows it when the streamer hides commands" asserts a `!hide` message survives `hideCommands: true` while another `!` command is dropped.
- **C18** [test] `useTwitchChat.test.ts` "feeds no emote bubbles, which would give the message away" asserts a redacted message with a Twitch emote and a resolver-matched third-party emote adds no entries to `emotes`.
- **C19** [test] `useTwitchChat.test.ts` "hands taps the message as typed" asserts taps see `!hide my secret`.
- **C20** [test] `ChatRedactSettingTest` "is left alone by a save that does not mention it, like the chat designer does" asserts a JSON PATCH with only filter fields leaves `chatRedact()` unchanged.
- **C21** [test] `ChatRedactSettingTest` asserts the payload carries `chat_redact_command` null while off and the word while on.
- **C22** [test] `ChatRedactSettingTest` "refuses the Plinko word when Plinko is installed" asserts the C6 refusal.
- **C23** [unverified] The tests in C17 and C18 each failed when their guard in `useTwitchChat.ts` was removed, and passed with it restored.
- **C24** [unverified] In headless Firefox, `redactedHtml()` output rendered with the Bunny link from C14 drew Latin, emoji, Cyrillic, CJK and escaped HTML as solid bars, and `document.fonts.check("24px 'Redacted'")` returned true.
- **C25** [unverified] Not checked in OBS with live chat, nor against the ten Twitch Chat Overlay skins.

### Unchanged
- `resources/recipes/twitch-chat-overlay/chat.md` is not in the diff: the recipe already renders `[[[msg.html]]]`, which is where the redacted span lands, so installed copies need no reinstall.
- The `hide_commands` / `hidden_logins` handling in `chatFilters.ts` is not in the diff; only the message passed to it changed (C8).
- `PlinkoSettingsController` is not in the diff; the Plinko page does not refuse a word equal to the redact command.

### Risk
A change to the setting reaches an overlay only when its browser source reloads. Templates that
print `[[[msg.text]]]` instead of `[[[msg.html]]]` show a redacted message's words in plain text.
