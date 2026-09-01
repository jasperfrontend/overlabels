## OL-2609-016 - fix(settings): one flash toast in AppLayout, confirmations on every settings save

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-016`

### Surface
- `app/Http/Middleware/HandleInertiaRequests.php` - `flash.message` / `flash.type` now also read the `success` and `error` session keys
- `resources/js/layouts/AppLayout.vue` - watches `page.props.flash` and renders one `RekaToast`
- `resources/js/pages/dashboard/index.vue` - its own flash watcher and `RekaToast` removed
- `resources/js/pages/dashboard/events.vue` - same
- `resources/js/pages/dashboard/recents.vue` - same
- `resources/js/pages/TwitchData.vue` - flash watcher removed; its own toast for axios refreshes kept
- `resources/js/pages/admin/events/index.vue` - inline flash banner removed
- `resources/js/pages/admin/logs/index.vue` - inline flash banner removed
- `resources/js/pages/gamejam/admin.vue` - inline flash banner removed
- `resources/js/pages/settings/integrations/fourthwall.vue` - inline `flash.success` / `flash.error` banners removed; test-mode chatter removed; seed toast added
- `resources/js/pages/settings/integrations/kofi.vue` - test-mode chatter removed; seed toast added; em dashes and ellipses replaced
- `resources/js/pages/settings/integrations/bmac.vue` - test-mode chatter removed; seed toast added
- `resources/js/pages/settings/integrations/streamlabs.vue` - same
- `resources/js/pages/settings/integrations/throne.vue` - same
- `resources/js/pages/settings/Chat.vue` - made-up inline confirmation removed; error stays inline
- `routes/settings.php` - `settings.chat.update` flashes `success`
- `app/Http/Controllers/Settings/BotCommandsController.php` - `store` / `update` / `destroy` flash `success`
- `app/Http/Controllers/Settings/BotAliasesController.php` - same
- `resources/js/pages/overlaytokens/index.vue` - `RekaToast` on revoke and delete

### Claims
- **C1** [code] `HandleInertiaRequests::share()` resolves `flash.message` as session `message`, falling back to `success`, then `error`; `flash.type` as session `type`, falling back to `'success'` when `success` is set, then `'error'` when `error` is set.
- **C2** [code] `AppLayout.vue` mounts exactly one `RekaToast`, fed by an `immediate` watcher on `page.props.flash` (the object, not the message string) and keyed on a counter, so two consecutive identical flashes each show a toast.
- **C3** [code] No file under `resources/js/pages/` reads `page.props.flash.message`, `flash.success` or `flash.error` any more; `templates/show.vue` still reads `flash.fork_wizard`, which is not a message.
- **C4** [code] `TwitchData.vue` keeps its own `toastMessage` for the axios-driven refresh results, which are not session flashes.
- **C5** [code] `Chat.vue` no longer has a `confirmation` ref; `settings.chat.update` returns `back()->with('success', ...)` and the success text reaches the user only via C2.
- **C6** [code] `BotCommandsController` and `BotAliasesController` attach `->with('success', ...)` to all six `store` / `update` / `destroy` redirects, naming the command with a `!` prefix (the validator strips the bang before storage).
- **C7** [code] On the five donation integration pages the test-mode block no longer renders the `enabled` label span, the `saving` span, or the amber "Test mode is on" box; the yellow "Turn this off before going live" warning is kept.
- **C8** [code] On the same five pages `setSeedCount()` sets a local `toastMessage` on success, rendered by a page-level `RekaToast` with `type="success"`.
- **C9** [code] `overlaytokens/index.vue` sets a local `toastMessage` after a successful revoke and after a successful delete, naming the token.
- **C10** [code] `kofi.vue` contains no em dash and no ellipsis character.
- **C11** [unverified] In Chrome against the local dev server, saving `/settings/chat` twice showed two success toasts, and saving a bot command redirected to `/settings/bot/commands` with a "Command !stevetest saved." toast.

### Unchanged
- The nineteen existing `->with('success', ...)` sites in `KitController`, `OverlayTemplateController`, `DonationIntegrationController` and the integration controllers are not in the diff; they become visible through C1 without being edited.
- `RekaToast.vue` itself is not in the diff; the stacking, timers and palette are as they were.
- The Ko-fi `verification_token` `required` rule in `KofiIntegrationController::save()` is not in the diff (separate change).
- `/tokens`, `/tags`, `/twitchdata` and `/testing` keep their paths in this commit (separate change).
- The integration enable/disable buttons on `settings/integrations/index.vue` and the test-mode toggles get no toast on purpose: the button label and toggle colour already show the result where the click happened.

### Risk
Pages that flashed `success` or `error` into a key nobody read (template create/update/delete/copy, kit create/update/delete/copy, every integration connect and disconnect, the Fourthwall and StreamLabs OAuth failures) now show that text as a toast.
