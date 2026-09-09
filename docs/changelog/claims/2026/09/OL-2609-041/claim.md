## OL-2609-041 - fix(admin): the Twitch Bot page is in the admin menu

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-041`

### Surface
- `resources/js/components/AppSidebar.vue` - `Twitch Bot` entry appended to the admin nav items

### Claims
- **C1** [code] The admin nav list in `AppSidebar.vue` ends with `{ title: 'Twitch Bot', href: route('admin.twitchbot.index'), icon: BotIcon }`, inside the same computed that already gates on `isAdmin` and `isOnAdminPage`, so a non-admin never evaluates the route.
- **C2** [code] `BotIcon` was already imported in the file; no import is added.
