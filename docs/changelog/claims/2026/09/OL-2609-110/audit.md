## Audit of OL-2609-110 - feat(products): the two chat display filters, on the designer instead of a settings page

**Audited:** 2026-09-25
**Commit:** 67f9dd9d16cbc48cdbd51af66d03e5c36d861151
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/pages/products/design.vue:223 @67f9dd9` - `axios.patch('/settings/chat', { hide_commands, hidden_logins: hiddenLoginsText.value })`; `resources/js/pages/settings/Chat.vue:46-50 @67f9dd9` sends the same two keys to `route('settings.chat.update')`. The diff adds no route and no validation; `typedLoginCount` (design.vue @67f9dd9) only counts lines for the over-cap warning and is not sent |
| C2 | CONFIRMED | `routes/settings.php:79 @67f9dd9` - `if ($request->wantsJson()) return response()->json(['chat_filters' => $user->chatFilters()])`; `:83` - `back()->with('success', ...)`. The hunk only adds lines after `$user->save()`; the regex (`:63`), `unique()` (`:64`) and `take(User::MAX_HIDDEN_LOGINS)` (`:65`) are not in the diff. Same @HEAD |
| C3 | CONFIRMED | `tests/Feature/ProductChatDesignerTest.php:246 @67f9dd9` - `patchJson` with `"@SpamBot\nspambot\nnot a login!\nanotherbot"`, `assertJsonPath('chat_filters.hidden_logins', ['spambot', 'anotherbot'])`. `php artisan test --filter=ProductChatDesignerTest`: 17 passed |
| C4 | CONFIRMED | `tests/Feature/ProductChatDesignerTest.php:270-275 @67f9dd9` - non-JSON `patch('/settings/chat', ...)`, `assertRedirect()`, `assertSessionHas('success')`. Passed in the same run |
| C5 | CONFIRMED | `tests/Feature/ProductChatDesignerTest.php:230 @67f9dd9` asserts `chat_filters.hide_commands`, `chat_filters.hidden_logins` and `max_hidden_logins`; `:246-266` saves through the endpoint and then asserts the next designer render carries both filters. Passed in the same run |
| C6 | CONFIRMED | `app/Http/Controllers/ProductController.php:407 @67f9dd9` - `'chat_filters' => $user->chatFilters()`; `app/Models/User.php:120 @67f9dd9` - `$appends = ['locale', 'foreach_caps']`; `tests/Feature/ChatFilterSettingsTest.php:175` "does not leak the hidden list through incidental serialisation" exists and `--filter=ChatFilterSettingsTest` passed (18). Same @HEAD (`ProductController.php:491`) |
| C7 | CONFIRMED | `design.vue:227 @67f9dd9` - `savedLoginCount.value = (data?.chat_filters?.hidden_logins ?? []).length`; `hiddenLoginsText` is written only by its initial `ref(...join('\n'))` and `v-model` (`:510`). @HEAD `writeChatFilters()` also calls `remember('chat_filters', ...)` (1ffbc595, disclosed in OL-2609-120 Unchanged); the textarea is still not rewritten |
| C8 | CONFIRMED | `design.vue:244 @67f9dd9` - filters debounce `800`; `:193` window debounce `250`; `:495` checkbox `@change="writeHideCommands(...)"`, which calls `writeChatFilters()` directly. Same @HEAD (`:468`, `:413`, `:893`) |
| C9 | CONTRADICTED (compound) | First half CONFIRMED: `design.vue:182 @67f9dd9` (window) and `:228` (filters) both call `confirmSaved()` (`:170`). Second half CONTRADICTED: the sample feed does have hideable logins (`chatSample.ts @67f9dd9` - `chatter${n}`, pool of 24), and `OverlayRenderer.vue:947 @67f9dd9` calls `setFilters(json.chat_filters)` before `startSampleChat()` at `:956`, so a hidden sample chatter IS dropped from the preview once it reloads - as this claim's own C11 records and the section copy (`design.vue:527 @67f9dd9`, "the preview here follows on reload") says. The "no `!` messages" part holds: `WORDS` and the emote codes contain no leading `!` |
| C10 | CONFIRMED | `design.vue:527 @67f9dd9` - "changes your overlay only: the message is still in chat, still in the VOD, and everyone watching still sees it"; `settings/Chat.vue @67f9dd9` says "still in chat, still in the VOD, and every viewer and moderator still sees it". The word "moderation" does not appear in the new section. Same @HEAD (`:925`) |
| C11 | UNVERIFIABLE | tagged [unverified] |
| C12 | UNVERIFIABLE | tagged [unverified] |

### Surface
Complete.

### Findings
- **F1** compound claim, one half false - C9's "None of them is visible in the preview" is contradicted by `OverlayRenderer.vue:947,956 @67f9dd9` (filters are applied to the sample feed) and by the claim's own C11. A follow-up claim should say the filters are not visible until the preview reloads.
- **F2** contradiction with CLAUDE.md - the "Chat display filters" section says of `chatFilters()`: "Callers ask for it explicitly - the settings page and the render payload, nowhere else." `ProductController.php:407 @67f9dd9` adds a third caller. The `User.php` docblock was updated, but CLAUDE.md was not and still says "nowhere else" @HEAD. Update that CLAUDE.md line to name the designer.
- **F3** earlier claim narrowed without citation - OL-2609-016 C5 records "`settings.chat.update` returns `back()->with('success', ...)`". `routes/settings.php:79 @67f9dd9` now returns JSON to a JSON request instead, and OL-2609-110 does not cite OL-2609-016 inline. A follow-up claim should cite it (e.g. "narrows OL-2609-016 C5").

### Notes
- Tests were run at HEAD, not at the shipped commit. The three OL-2609-110 tests have no changed lines between @67f9dd9 and HEAD (`git diff 67f9dd9 HEAD -- tests/Feature/ProductChatDesignerTest.php`).
- Unchanged lines confirmed: `settings/Chat.vue`, `App\Support\ChatDesigner`, `ChatPresets`, `chatFilters.ts` and `useTwitchChat.ts` are not in `git show --stat 67f9dd9`, and `writeChatFilters()` does not reload the frame.
- The diff also renames the window slider's heading ("Messages on screen" to "What the feed shows") and its label ("How many at once" to "How many messages at once"). Both are covered by the design.vue Surface line.
- 1ffbc595 (no trailer, `.vue` only, so exempt from the claim rule) changed `writeChatFilters()` after this commit. OL-2609-120's Unchanged section discloses it.
