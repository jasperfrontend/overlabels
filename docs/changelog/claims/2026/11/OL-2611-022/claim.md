## OL-2611-022 - feat(dashboard): a lobby instead of an inventory

**Shipped:** 2026-11-17
**Commit:** `git log --grep=OL-2611-022`

### Surface
- `app/Http/Controllers/DashboardController.php` - `index()` renders a new prop set; new `nextUp()` and `lastStream()`; `installedProducts()` adds `remaining` and `todo`; `gamesLeft()` removed; unused imports dropped
- `resources/js/pages/dashboard/index.vue` - rewritten: greeting, Next up card, On your stream strip, Lately (last stream + latest moments)
- `resources/js/components/InstalledProductsBox.vue` - deleted
- `resources/js/types/index.d.ts` - `InstalledProduct` gains optional `remaining` and `todo`
- `tests/Feature/Settings/UsagePageTest.php` - "the dashboard provides a usage summary for its strip" removed

### Claims
- **C1** [code] `DashboardController::index()` passes exactly the props `isNewUser`, `installedProducts`, `nextUp`, `showWelcomeDialog`, `lastStream`, `recentEvents` and `whatsNew`.
- **C2** [code] `index()` no longer passes `userAlertTemplates`, `userStaticTemplates`, `communityTemplates`, `userRecentEvents`, `recentUpdates`, `usage`, `gamesLeft` or `userId`.
- **C3** [code] `nextUp()` returns `kind: 'finish'` for the first installed product, newest install first, whose `remaining` is above 0.
- **C4** [code] When no installed product has `remaining` above 0, `nextUp()` returns `kind: 'try'` for the first listed `product`-category manifest the account has not installed.
- **C5** [code] `nextUp()` returns null when neither C3 nor C4 applies, and `index()` passes null for `nextUp` when `isNewUser` is true.
- **C6** [code] `installedProducts()` sets `remaining` to the number of `ProductSetup::steps()` wires in state `WiringCatalog::MISSING`, and `todo` to `ProductSetup::STEPS[key]['todo']` of the first, falling back to its lowercased label.
- **C7** [code] `lastStream()` reads the latest `StreamSession` by `started_at` and counts moments by a `created_at` window between `started_at` and `ended_at` (or now), not by `stream_session_id`.
- **C8** [code] `lastStream()` excludes `stream.online` and `stream.offline` Twitch events and `gps` external events from the moment count.
- **C9** [code] `index()` calls `mergeRecentEvents()` with a limit of 5.
- **C10** [code] `dashboard/index.vue` renders `WelcomeBanner` and none of the other sections when `isNewUser` is true, and mounts `WelcomeDialog` with `showWelcomeDialog` in both cases.
- **C11** [code] `dashboard/index.vue` does not call `useStreamState`; the live wording reads `lastStream.live`.
- **C12** [test] `DashboardWelcomeTest` asserts `isNewUser` and `showWelcomeDialog` for new, returning and product-owning accounts.
- **C13** [test] `WhatsNewCardTest` reads the `whatsNew` prop from the dashboard response.
- **C14** [code] The previous `dashboard/index.vue` declared a `usage` prop and rendered nothing from it; the removed `UsagePageTest` case asserted only that prop. The two `/settings/usage` cases in that file remain.

### Unchanged
- `WelcomeDialog.vue` and `WelcomeBanner.vue` render the new-account onboarding and are mounted by the page; neither file is in the diff, and `claimWelcomeDialog()` is untouched.
- `mergeRecentEvents()`, the source of the latest moments, is called with a smaller limit but its body is not in the diff; `EventsTable.vue`, which renders them, is not in the diff.
- `recentActivity()`, `recentEvents()` and `recentActivityDashboard()` in the same controller are not in the diff.

### Risk
The dashboard no longer lists overlays, alerts or the account's full activity; those are reached from
the sidebar and the section links. `nextUp()` and `installedProducts()` run the wiring checks for every
installed product on each dashboard load.
