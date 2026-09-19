## OL-2609-110 - feat(products): the two chat display filters, on the designer instead of a settings page

**Shipped:** 2026-09-19
**Commit:** `git log --grep=OL-2609-110`

`/settings/chat` holds the two filters that decide what a chat overlay draws. A streamer designing
their chat on `/products/twitch_chat/design` had to leave for them. Both now sit on the designer,
writing the same preference through the same endpoint.

### Surface
- `resources/js/pages/products/design.vue` - the window slider, a "hide messages starting with !" checkbox and a hidden-chatters textarea gathered into one "What the feed shows" section, with a shared Saved confirmation
- `app/Http/Controllers/ProductController.php` - `design()` passes `chat_filters` and `max_hidden_logins`
- `routes/settings.php` - `settings.chat.update` gains a JSON branch returning the normalised filters
- `app/Models/User.php` - `chatFilters()` docblock names the designer as a third explicit caller
- `tests/Feature/ProductChatDesignerTest.php` - three tests added

### Claims
- **C1** [code] The designer writes both filters with one `PATCH /settings/chat` carrying `hide_commands` and `hidden_logins`, the same endpoint and the same payload shape `settings/Chat.vue` sends. It adds no route, no validation and no normalising of its own.
- **C2** [code] `PATCH /settings/chat` returns `{chat_filters: {...}}` to a request that wants JSON and `back()->with('success', ...)` otherwise. The parsing, the login regex, the dedupe and the `MAX_HIDDEN_LOGINS` cap above it are unchanged.
- **C3** [test] The JSON branch returns the list after normalising: `"@SpamBot\nspambot\nnot a login!\nanotherbot"` comes back as `['spambot', 'anotherbot']`.
- **C4** [test] `/settings/chat` still redirects and still flashes `success` for a non-JSON request.
- **C5** [test] The designer renders `chat_filters` and `max_hidden_logins`, and reflects a filter saved through the endpoint on the next render.
- **C6** [code] `ProductController::design()` calls `$user->chatFilters()` explicitly. `chat_filters` is still absent from `User::$appends`, which `ChatFilterSettingsTest`'s "does not leak the hidden list through incidental serialisation" pins.
- **C7** [code] The textarea is never rewritten from the response. `savedLoginCount` takes the server's count; `hiddenLoginsText` is only ever what was typed.
- **C8** [code] The hidden-chatters textarea debounces at 800 ms and the window slider at 250 ms. The checkbox writes on change with no debounce.
- **C9** [code] All three share one `confirmSaved()` flash. None of them is visible in the preview - the sample feed emits no `!` messages and no real logins - so the flash is the only confirmation any of them has.
- **C10** [code] The section's copy states the filters change the overlay only and that the message stays in chat, in the VOD and visible to everyone, matching what `settings/Chat.vue` says. Neither is described as moderation.
- **C11** [unverified] On overlabels.test: hiding twelve of the sample feed's twenty-four chatters and reloading, twenty bursts produced exactly the twelve unhidden names and none of the twelve hidden ones. That is the whole chain - preference, render payload, `setFilters`, ingest filtering.
- **C12** [unverified] On overlabels.test: typing `@SpamBot`, `spambot`, `not a login!` and `AnotherBot` into the textarea left the line reading "2 names are hidden" once the debounce fired, and the stored preference held `['spambot', 'anotherbot']`.

### Unchanged
- `resources/js/pages/settings/Chat.vue` is not in the diff. `/settings/chat` keeps its own heading, its longer explanation and its explicit Save button; the designer is a second writer of the same preference, not a replacement, and the two show each other's values because they read the same `chatFilters()`.
- `App\Support\ChatDesigner` is not in the diff. The filters are account preferences rather than look controls, so they are not in `GROUPS`, not in `CHOICES` and not part of any preset bundle - which is why `ChatPresets::KEYS` and the group-coverage test in OL-2609-109 C23 are untouched by adding two knobs.
- `resources/js/utils/chatFilters.ts` and `useTwitchChat.setFilters()` are not in the diff. Filtering still happens at ingest in the overlay, from the render payload, exactly as it did before the designer existed.
- The preview frame is not reloaded when a filter is written. These go out over axios like every other write on the page, for the reason OL-2609-109 C31 records.

### Risk
Nothing new is stored and nothing new is exposed. A filter written here takes effect when the browser
source next loads, not immediately, which the section says in as many words.
