## OL-2610-003 - feat(home): a headline that says what the games are for, and a promise about AI

**Shipped:** 2026-10-09
**Commit:** `8579e6fa`, `ad59988b`, `db8db2ba` (shipped without a trailer; the entry was written after them). `git log --grep=OL-2610-003` finds only the commit adding this file and the prose entry.

### Surface
- `resources/views/welcome/hero.blade.php` - new H1 and lede, links to `#chat-tower`, `#follower-bowling` and `#twitch-chat-overlay`, the dashboard button removed, the free-forever line with a link to `/ai`
- `resources/views/welcome.blade.php` - `<title>`, meta description, og and twitter title, description and image alt
- `resources/help/pages/ai.md` - new help page
- `resources/help/pages/index.md` - links `/help/ai` under Getting started
- `routes/web.php` - `Route::redirect('/ai', '/help/ai', 301)`
- `public/ogimage.jpg`, `public/ogimage.png` - redrawn share image

### Claims
- **C1** [code] `hero.blade.php`'s `<h1>` reads "Twitch Chat Games for Downtime and BRB Breaks".
- **C2** [code] `hero.blade.php` no longer contains "nobody else has them" or a link to `dashboard.index`.
- **C3** [code] `hero.blade.php` links "Chat Tower" to `#chat-tower`, "Follower Bowling" to `#follower-bowling` and "Twitch chat overlay" to `#twitch-chat-overlay`, the `id`s of the rows in `welcome/products.blade.php`.
- **C4** [code] `hero.blade.php` links "Promised" to `/ai`, and `routes/web.php` redirects `/ai` to `/help/ai` with a 301.
- **C5** [code] `welcome.blade.php`'s `<title>`, `og:title` and `twitter:title` are "Overlabels: Free Twitch Chat Games and Chat Overlay" (51 characters).
- **C6** [code] `welcome.blade.php`'s meta description is 145 characters, and its `og:description` and `twitter:description` are 105.
- **C7** [code] `ai.md` declares `section: Getting started` and is linked from `index.md` under `### Getting started`.
- **C8** [code] `ai.md` names ElevenLabs as the service spoken alert text is sent to, matching `TtsService::synthesize()`'s request to `api.elevenlabs.io`.
- **C9** [code] `ai.md`'s statement that Twitch tokens and integration credentials are encrypted matches `/help/your-data`.
- **C10** [code] `public/ogimage.jpg` and `public/ogimage.png` are 1200 by 630.
- **C11** [unverified] `ai.md` states that three users agreed to have their real data seen during development and that the author's Claude account does not allow training on its conversations. Both are the author's statements; neither can be checked from the tree.

### Risk
- C11 is a public promise resting on an account setting outside the repository.
