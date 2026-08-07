# CHANGELOG AUGUST 2026

## August 7th, 2026 - fix(controls): your latest cheerer stopped being erased at every go-live

`c:twitch:latest_cheerer_name` was wiped to a literal `"0"` the moment a stream started, and its 25 equivalents across Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee and Throne were not. Same idea, opposite lifecycle, and the only thing separating them was which code path created the row.

- **Nobody ever decided this.** `resetControls()` arrived in March with six presets, every one of them named `*_this_stream`. Resetting them *is* the feature and it was exactly right. Five weeks later the `channel.cheer` presets landed - explicitly "so bits payloads can drive overlays the same way donations do" - and the three `latest_cheer*` ones inherited the reset purely because they share `source='twitch'`. The filter predated the controls it was erasing.
- **So the reset broke the one thing that commit set out to build.** Bits parity with donations was the stated goal, and `latest_donor_name` persisting while `latest_cheerer_name` did not is precisely the parity failing. It stayed invisible for four months because you only notice when you race the two against each other.
- **The reset is now scoped by key, not by source.** The eight `*_this_stream` counters still reset at go-live, because that is the only thing implementing what their labels promise - they increment additively and nothing else ever zeroes them. The three `latest_cheer*` controls no longer do. A key earns its place on the list only if its label promises per-stream scope.
- **This is the pattern the GPS integration already used.** Its reset takes an explicit key list and deliberately leaves cumulative `distance` alone behind a separate manual button. The twitch path was the one place resetting by source with no filter.
- **`latest()` racing across services now behaves as documented.** Twitch was the only competitor whose `_at` moved at go-live, so it won every race at stream start holding `"0"`. Nothing about `latest()` changed - it was reporting the freshest write, correctly, the whole time.
- **You may notice this on your next stream.** An overlay that went blank at go-live and stayed blank until someone cheered will now keep showing the previous cheerer. That is what the label says it does, but it is a visible change rather than a silent one.
- **Nine tests pin it**, and the three that assert the new behaviour were verified to fail against the old filter. The rest cover the scoping that was already right - other users, other sources, and user-created controls that merely share a key name - so a future change cannot widen the blast radius unnoticed.

## August 7th, 2026 - fix(deps): Dependabot could not update anything, and had been failing silently

Dependabot tried to ship a security update for `league/commonmark` and died with "Your requirements could not be resolved to an installable set of packages". The interesting part is that it had nothing to do with commonmark, and it would have broken every future composer security update the same way.

Dependabot injects its own `config.platform.php`, and set it to `8.4` - which composer reads as **8.4.0**. That number is the floor of our `require: php ^8.4`. Meanwhile `nunomaduro/collision` pulls `symfony/console` v8.1.1, which requires `php >=8.4.1`. So resolution failed on a constraint three packages away from the one being updated, before commonmark was ever considered.

- **The PHP constraint was simply false.** `^8.4` claimed the app runs on 8.4.0. It cannot - symfony/console needs 8.4.1. Local dev is on 8.4.24 and prod is a FrankenPHP 8.4 image, so nothing ever surfaced it. `require.php` is now `^8.4.1`, which is the truth.
- **`config.platform.php` is now pinned explicitly to 8.4.1.** Dependabot's own error says "overridden via config.platform", so it honours one that already exists - that is the lever that actually fixes the automation. It also makes resolution identical on a laptop, in CI, and in the Docker build, rather than silently depending on whichever PHP the `composer:2` image happens to ship that week.
- **The six advisories were real but not reachable.** All denial-of-service via crafted markdown, five high and one medium, published the day before. Both CommonMark call sites read from `resource_path('help/reference')` and `resource_path('help/pages')` - repo files, authored by us. No user-supplied markdown reaches the parser and there are no markdown mailables, so exploiting it required commit access. Worth fixing, not worth panicking, and Dependabot being broken did not leave anything exposed.
- **The update moved exactly one package.** `league/commonmark` 2.8.3 -> 2.9.0, no installs, no removals, nothing else touched. `composer audit` now reports no advisories, and the suite passes 1233.

## August 7th, 2026 - feat(ops): a backup that never runs is now louder than one that fails

The Discord webhook only ever answered one of the two questions. It fires when the backup runs and fails. It cannot fire when the backup never runs at all, because the thing that would send the message is the thing that is down - and silence from a dead scheduler is indistinguishable from silence after a perfect night. Healthchecks.io alerts on the absence of a ping, which is the only shape of check that works when the failure mode is "nothing happened".

- **It is three lines, because Laravel already had this.** `pingOnSuccess()` and `pingOnFailure()` are built into the scheduler. No new command, no new service, no HTTP client of our own - the entire feature is a conditional on the existing schedule entry.
- **The ping hangs off the schedule, not the command, and that distinction is the whole point.** Attached to the command, a manual `php artisan backup:database` would satisfy the switch and reset the timer - so the one thing the switch exists to catch, a scheduler that has quietly stopped firing, could be masked by a human doing the backup by hand. On the schedule, only the scheduler can feed it.
- **Failures ping `/fail` rather than just staying quiet**, so a broken backup flips the check immediately instead of waiting out the 30-minute grace period. Healthchecks and Discord now agree rather than reporting on different clocks.
- **A Healthchecks outage cannot fail a backup.** `Event::pingCallback()` already catches transport exceptions and reports them rather than throwing - checked in the framework source rather than assumed, because a monitoring tool that can break the thing it monitors is worse than no monitoring.
- **The tests drive the real callbacks.** A recording HTTP client is bound in place of Guzzle and the event's success and failure paths are invoked with actual exit codes, so they assert the URLs genuinely requested rather than that some callback happens to be registered. Both were verified to fail with the ping block disabled.
- **An empty `HC_PING_URL` registers no pings at all**, so nothing breaks in dev or CI, and `rtrim()` guards the one input mistake that would 404 every failure ping: a trailing slash on the env var.

## August 6th, 2026 - docs(ops): the backup stopped being a guess

The nightly job fired on its own at 03:00 UTC, and the object it produced was pulled back out of R2 and restored into local dev. Zero errors, 54,061 rows across 61 tables, and then the actual application ran on top of it: Twitch login, templates and controls, a static overlay authenticating off its URL-fragment token, and a live follower alert arriving through EventSub and Reverb. Production was untouched - EventSub subscription count identical before and after.

- **The doc now records that, because "no restore test" had become false in both directions it claimed.** The R2 read path and the restore had never been exercised when that line was written; now they have, with the specifics worth re-checking against: row-for-row parity with every `COPY` block, 55 sequences ahead of their table's max id, 71 foreign keys validated, 0 pending migrations.
- **The remaining gap is stated more precisely than "manual".** Nothing detects a dump that uploads cleanly and will not restore. The nightly run proves the write path every night and proves nothing at all about the read path, which is the half you need on the worst day.
- **It names the three changes that should trigger redoing it**: the dump flags, the pinned client major, and the `r2` disk config. Each can break a restore while leaving the nightly run looking perfectly healthy, which is exactly the failure mode this whole exercise exists to catch.

## August 6th, 2026 - refactor(ui): five list designs for one idea

`TemplateTable` rendered no table. It was a table once, the contents got rewritten, the name stayed. Next to it sat `TemplateList`, which was that component copy-pasted and drifted. `UpdatesList` was a third copy of the same row. And `/triggers` and `/dashboard/lists` had each invented their own list from scratch, with their own borders, their own hover, and in the case of Lists no `:active` state at all. Five designs, one idea.

There is now one `CollectionList`, and everything that renders a collection of rows goes through it.

- **`.overlabels-background` was already the house row skin** - left accent bar, violet on hover, gradient wash on press - and four of the six surfaces already used it. The two that reinvented themselves simply hadn't found it. It is renamed `.collection-row`, which is what it is, and it no longer carries padding: every caller was overriding that anyway with a different value, which is how one skin produced four densities.
- **Rows navigate by stretched link.** The row is a plain container with an absolutely positioned `<Link>` covering it. That is strictly better than either pattern it replaces: `TemplateTable` used `role="button"` on a div with `router.visit`, which gives you no middle-click, no ctrl-click, no "open in new tab" and no "copy link address"; `TemplateList` nested its dropdown button inside the `<a>`, which is invalid HTML and meant clicking the kebab also navigated. Action buttons now just sit above the link on `z-10` instead of stopping propagation.
- **Actions stay visible below `md`.** They were `opacity-0` until hover, and a touch device has no hover, so on a phone the kebab menu was unreachable on every template row.
- **Merging the two template components resolved four behaviour bugs**, all of them cases where the copy had drifted from the original. `TemplateList` offered Delete on kit-bound templates the server rejects. It said "Fork template", which CLAUDE.md forbids in frontend copy. Its "Preview (inline)" and "Preview (new tab)" pointed at the same URL. And its "Copy link for OBS" copied a URL ending in the literal string `YOUR_TOKEN_HERE`. The merged `TemplateCollection` keeps the correct behaviour from each side, including the copy-confirmation checkmark, which only the list had.
- **The external event label was reading "bmac: donation".** It came from a hardcoded map covering Ko-fi and Streamlabs, written before the other three donation services existed. It now derives from `SERVICE_LABELS`, the thing that already exists for this, so Throne and Buy Me a Coffee read like Ko-fi always did and the sixth integration needs no edit here.
- **`/triggers` rendered its Twitch rows and its external rows as two copies of the same 30 lines.** They are one `v-for` over sections now. A shadowed trigger takes the accent bar amber rather than adding a second border in a different colour system.
- **Four hand-rolled empty states became `EmptyState`**, which also already existed.

`EventsTable` and `ControlsManager` keep their own row markup - they wrap each row in a popover and a collapsible respectively, so they are not the same shape - but they were already on the shared skin and still are, so they move with it.

## August 6th, 2026 - fix(routing): the Triggers page was called three different things

The nav said "Triggers". The URL said `/alerts`. The Inertia component said `events/index`. Three names for one page, and the only way to know they were the same page was to have written it.

- **The URL is `/triggers` and the route is `triggers.index`.** `events.index` was the worst of the three names, because `admin.events.index` is a genuinely different page - the raw event feed - so grepping for "events.index" returned two unrelated things.
- **The page folder is `resources/js/pages/triggers/`.** Moved with `git mv`, so the history follows it.
- **`/alerts` still resolves**, as an unnamed 301 to `/triggers`. Bookmarks and tabs left open in OBS keep working. It is unnamed on purpose: nothing should link there, and Ziggy skips unnamed routes, so it cannot be reached from the frontend by accident.
- **Five integration settings pages linked to "Alerts Builder"**, hardcoded as `href="/alerts"` rather than through Ziggy - which is why the rename would have broken them silently. They now say "Triggers" and point at `/triggers`, matching what the nav item is actually called.

## August 5th, 2026 - fix(docs): the restore procedure named a psql that cannot read our own dumps

Verifying the first real backup - by inspecting the `.sql` rather than restoring it - turned up two things the docs asserted confidently and wrongly.

- **The restore procedure pointed at psql 17, which aborts on our dumps.** Every dump pg_dump 16.14 writes now opens with `\restrict` and closes with `\unrestrict`, a psql meta-command added in PostgreSQL 18 and backpatched only as far as 17.6, there to stop a malicious object name smuggling psql commands into a restore. The local client is 17.5. By default it prints `invalid command \restrict` and carries on, restoring fine but with the guard silently inactive; under `ON_ERROR_STOP=on` it aborts with exit code 3. The procedure now uses the psql 18 binaries and sets `ON_ERROR_STOP=on` explicitly, so a partial restore is loud rather than a database that looks fine and is missing a table from the middle.
- **The Dockerfile justified its PGDG repo with the wrong operating system.** The comment claimed Debian bookworm ships only client 15. The FrankenPHP base is Debian 13 (trixie), which ships client 17 - it would have dumped a 16 server unaided, so the PGDG repo was never load-bearing the way the comment said. It stays, because pinning the client major explicitly beats letting it drift with the base image, but the comment now says that instead of inventing a constraint. The `$VERSION_CODENAME` lookup turns out to have been the part actually doing work: a hardcoded suite would have pointed a bookworm repo at a trixie system.

The dump itself checked out: completion marker present, 61 tables with 61 `COPY` blocks, 92 indexes, 71 foreign keys, zero `OWNER TO` or `GRANT` statements, and row counts identical to production on every table that does not grow by itself.

## August 5th, 2026 - feat(ops): production had no database backup

Linode's weekly VM snapshot was the only copy of production, and a VM snapshot is not a database backup. It is an image of a running disk taken while Postgres was mid-write, it restores as a whole machine or not at all, and on a bad day it is six days stale. Fifty-two megabytes of users, templates, overlays, controls and stream history, with a week-long worst case.

There is now a nightly `pg_dump` to a Cloudflare R2 bucket in the EU, at 03:00 UTC.

- **It adds no infrastructure.** The scheduler role already runs `schedule:run` every 60 seconds and already has `DB_HOST` and `DB_PASSWORD` injected, so the whole thing is one artisan command and one `Schedule::command()` line. No host cron to provision by hand, no fourth accessory container, nothing that lives outside the repo and has to be remembered.
- **The Dockerfile now pulls `postgresql-client-16` from PGDG rather than Debian.** `pg_dump` refuses to run against a server newer than itself, prod is Postgres 16.13, and Debian bookworm only ships client 15 - so the obvious `apt-get install postgresql-client` would have produced a backup system that fails on its first night with a version-mismatch error. The signing key is stored ASCII-armored and referenced with `signed-by`, which apt reads directly, so the runtime image does not need gnupg.
- **`--no-owner --no-privileges` is what makes the dump restorable.** Without them every statement references the `overlabels` role, and a restore into local dev - which runs as `postgres` - aborts on the first unknown role. The whole point of the file is the day you need to read it back, on a machine that is not the one that wrote it.
- **A dump under 10 KB is treated as a failure.** The schema alone is several hundred KB. The failure this guards against is not a crash, it is `pg_dump` exiting 0 having written nothing useful, which gives you thirty days of empty files and no idea until the day it matters.
- **The upload is verified by reading the object size back**, not by trusting the write. That is the difference between "we uploaded something" and "the backup is there". The `r2` disk is also the only one in `filesystems.php` with `throw => true`; the user-facing disks return false on failure, and a silent false here would report a failed upload as a successful backup.
- **Retention is an R2 lifecycle rule, not code.** Thirty days, set on the bucket. Deletion logic that runs nightly against a bucket full of backups is a thing that can have a bug, and its bug deletes backups.
- **The bucket is EU jurisdiction, and that is load-bearing in a non-obvious way.** It is what keeps the objects physically in the EU, and it is also baked into the S3 endpoint hostname - an EU bucket answers on `<account>.eu.r2.cloudflarestorage.com` and returns 403 on the plain host. That 403 looks exactly like a bad credential, so `R2_JURISDICTION` is pinned in `deploy.yml` with a comment saying to check it before rotating any keys.
- **The dump is deliberately not encrypted before upload.** EU jurisdiction plus Cloudflare's DPA and SCCs plus R2's own at-rest AES-256 is a defensible position without client-side encryption on top. The decider was the other direction: a passphrase living only in GitHub secrets is a single point of failure, and it fails by turning every historical backup into unrecoverable noise at the exact moment you need one.
- **Failures shout at Discord**, via `BACKUP_ALERT_WEBHOOK_URL`. Best-effort and optional - an unset webhook logs instead, and a webhook that itself fails cannot mask the backup failure underneath it.
- **The tests decompress the uploaded object and assert it contains real SQL.** Checking that a file of roughly the right size arrived would pass for a gzipped error message. The three end-to-end tests run a genuine `pg_dump` where the binary is available and skip where it is not, so CI exercises the real pipeline; the failure-path tests run everywhere, because a missing binary is itself a case the command has to survive.
- **`docs/deploy/database-backups.md` covers the restore**, including the four things that are supposed to break when prod data lands locally - `APP_KEY` mismatch on encrypted columns, external webhooks pointing at overlabels.com, overlay tokens stored as `sha256(plainToken)`, and EventSub subscriptions registered against the prod callback - so none of them get "fixed" by copying a production secret onto a laptop.
- **One gap is documented rather than papered over.** A backup that fails shouts; a backup that never runs at all is silent. Closing that needs an external pinger, which is another third party, and it was not worth it on day one.

## August 5th, 2026 - docs(reference): Buy Me a Coffee had no tag reference at all

Every other integration has a page under `/help/reference/eventsub-tags` listing the tags you can use in an alert template. Buy Me a Coffee, which emits more tags than any of them, had none. Seventeen tags, nowhere to look them up, which makes writing a BMAC alert a guessing game against whatever a test webhook happens to show you.

- **The new page documents all seventeen, read off the driver rather than off a sample payload.** A test event only shows you the fields that happened to be populated - `commission_name` and `wishlist_title` are empty strings on a plain donation, so a payload-derived list would have quietly omitted the tags that are hardest to guess.
- **`event.message` is not what the supporter wrote, and that needed saying loudly.** Buy Me a Coffee sends its own generated description ("John bought you a coffee") as the message, while the supporter's actual note arrives separately as `event.support_note`. `support_note` is what fills `c:bmac:latest_donation_message`. Anyone writing an alert reaches for `event.message` first and gets boilerplate. The page now has a Message Tags section that exists purely to head that off, including the detail that a supporter marking their note private (`note_hidden`) makes it come through empty on purpose.
- **Ko-fi's page was wrong about `event.source`.** It documented the value as lowercase "kofi"; the driver emits "Ko-fi". So `[[[if:event.source = kofi]]]` never matched, and the failure mode is a conditional that silently renders nothing. `event.is_shop_order` was also missing entirely.
- **Three Ko-fi pages linked each other by title instead of slug.** `[[All Ko-fi Events]]` does not resolve - the renderer looks up `all-ko-fi-events` - and an unresolved wikilink degrades to inline code rather than erroring, so they had been rendering as plain grey text rather than links for as long as they have existed.
- **Throne needed nothing.** Its page already covered all eleven tags accurately, including the minor-units division on `event.amount`. Checked against a real test gift rather than assumed.
- **Two tests now enforce this.** One asserts every `event.*` tag in each donation driver appears somewhere in the reference; verified to fail when the BMAC page is moved away. The other asserts every wikilink in the vault resolves to a real slug, since that class of rot is invisible by design. Obsidian attachment embeds are skipped, being file references rather than page links.
- **Overlabels GPS got its page too**, so the coverage test now has an empty exclusion list. It documents position, speed and device state rather than the donation six, carries a warning that the Android app is still in development and not in the Play Store, and states plainly that `event.latitude`/`event.longitude` put your physical location on stream - with an example that gates the whole block behind a boolean control you can flip mid-stream.
- **Two GPS quirks are documented rather than quietly left to be discovered.** It is the only integration with no `[[[event.type]]]` tag, so `[[[if:event.type = location_update]]]` never matches. And its event tags disagree with its controls on the position keys: `event.latitude`/`event.longitude` versus `c:gps:lat`/`c:gps:lng`.

## August 5th, 2026 - fix(integrations): connecting Ko-fi, BMAC or Throne gave you no controls at all

Three of the five donation integrations never provisioned anything. You connected Throne, the webhook worked, signatures verified, events landed in `external_events` - and there was nothing to read them from. The overlay render payload is built from control rows, so `[[[c:throne:latest_donor_name]]]` resolved to nothing, because the row had never been created. Same for Ko-fi and Buy Me a Coffee. Only Streamlabs, Fourthwall and GPS called `provision()`.

This was found while trying to fix a documentation bug. The generated reference said "Throne provisions 9 controls when you connect it", which was false, and the reason it was false is that the generator reads the driver while the decision lived three layers away in each settings controller. Nothing forced the two to agree.

- **The fix is deletion, not a flag.** The first instinct was a `provisionsOnConnect(): bool` on the driver contract so the generator could tell the truth per service. That is making the lie configurable. The principle is that connecting a service gives you its controls, uniformly, so the honest fix is one call site that every connect flow routes through.
- **`DonationIntegrationController` now owns `show()`, `setTestMode()`, `seedDonationCount()` and `disconnect()`.** Subclasses supply a service key and their connect flow, nothing else. 1,108 lines became 709 including the new base class. The five copies of `seedDonationCount()` were byte-identical between Ko-fi and Streamlabs and spelled the service key three different ways across the rest (`self::SERVICE_KEY`, `self::SERVICE`, a bare literal) - which is the same drift that produced the provisioning gap, just cosmetic instead of load-bearing.
- **`provision()` is called on every connect, not just the first.** It is documented idempotent and does not overwrite existing controls, so the `if ($isNew)` guard bought nothing and cost something: a driver that gains a control later now picks it up on the next reconnect instead of silently never appearing for existing users.
- **Fourthwall rolls controls back with the row.** Its callback deletes a fresh integration if webhook registration fails. Provisioning now happens on the way in, so that rollback had to deprovision too, or a failed first connect would strand six service-managed controls that nothing can write and the user cannot delete.
- **A migration backfills anyone already connected.** The fix only helps people who connect again; existing integrations keep an empty control list otherwise. Locally that was BMAC at 0 controls and Throne at 0. Idempotent, so a user who had added some by hand from the presets modal keeps their values and gains only what was missing.
- **`IntegrationProvisioningTest` is the test that did not exist.** The whole suite passed before and after the fix, which is precisely how three broken integrations stayed broken. Six of its assertions were verified to fail with the `provision()` call commented out. It also asserts structurally that every donation service's show route resolves to a `DonationIntegrationController` subclass, so integration number six cannot reintroduce this with a hand-rolled controller.
- **Provisioning and authoring are different things, and CLAUDE.md had them merged.** It recorded "Ko-fi: NO auto-provision on connect - user explicitly adds from ControlFormModal" as a decision. The modal part is true and is about writing a template; the provisioning part was a bug wearing a decision's clothes. Controls are user-scoped, so once the row exists the tag works in every overlay you own with no per-template setup - the modal only helps you discover which keys exist.

## August 4th, 2026 - docs(readme): the README documented 2 of 5 donation integrations

Opened the README expecting to strip StreamElements out of it and found there was nothing to strip - the External Integrations section never covered it. What it did cover was Ko-fi and StreamLabs, and nothing else. Fourthwall, Buy Me a Coffee and Throne have all shipped since that section was written, so the front door of the repo was describing 2 of the 5 donation services that actually exist.

- **The shared six-key schema is stated once, up front.** Writing out the same six-row table five times would have tripled the section for no information gain. Stating it once and letting each service list only what it adds beyond the six is also the honest shape of the thing - the portability of a template between services *is* the feature, and a repeated table buries it.
- **The auto-provision claim was wrong in the first draft and got caught before commit.** "All five provision six controls on connect" reads plausibly and is false: only StreamLabs and Fourthwall call `provision()` on connect. Ko-fi, BMAC and Throne rely on the user adding controls from presets, and `applyUpdates()` only ever updates controls that already exist - it never creates one. A reader following the old wording would have connected Throne, seen no controls appear, and concluded the integration was broken.
- **BMAC and Throne get their extras documented with the conditional that makes them useful**, rather than as bare table rows: `latest_support_type` driving a per-type alert, and `latest_is_surprise_gift` working with the truthiness form of `[[[if:]]]` because `"0"` is falsy under the rules the README already states two sections earlier.
- **The self-hosting env block now distinguishes OAuth from per-user auth.** It listed only the StreamLabs variables, which implied the others needed something. Fourthwall's five `FW_*` variables were missing, and Ko-fi, BMAC and Throne need no environment configuration at all because they authenticate per-user with a token or a signature. Worth saying explicitly - "nothing to configure" is information.
- **The tip line dropped StreamElements**, which was the one and only mention of it in the file, and the one place the removal actually did reach the README. Ko-fi only now.

## August 4th, 2026 - chore(integrations): StreamElements is gone

Razer, which acquired StreamElements, put up an accept-or-delete-your-account dialog for a new privacy policy. Buried in it is a clause claiming ownership of user-submitted and user-generated content, including the right to hand it to third parties. That is not a thing Overlabels wants to route a streamer's donation data through, so the integration is removed rather than deprecated.

Being honest about what this is: Razer will not notice, and no data is protected by the removal - Overlabels pulled tips *out* of StreamElements, it never sent anything in. It is a statement about what this project is willing to maintain. The reason to make it now rather than later is that it costs nothing now: zero users were connected, so nobody's overlay breaks. In a year it would have broken real people's setups.

- **The whole surface went, not just the connect button.** The driver, the settings controller and page, the five settings routes, the internal integrations endpoint, `streamelements-listener.mjs` with its Dockerfile and GHCR build step, the Kamal accessory, and `STREAMELEMENTS_LISTENER_SECRET` in all four places it was declared. The settings integrations list is built from `ExternalServiceRegistry`, so dropping the registry entry removed the card with no separate edit.
- **A migration purges the data**, and it has to. Service-managed controls answer 403 to `setValue()` and `update()`, so six undeletable, permanently stale controls per connected user would have sat in the dashboard forever with no driver left to write them. The encrypted JWTs go too - keeping the credential while dropping the integration would be the wrong half of the decision. It is deliberately irreversible; `down()` cannot invent a JWT it never had.
- **The reference pages regenerated themselves.** `help:build-integration-controls` reads the registry, so `streamelements.md` was deleted and `all-integration-controls.md` rewritten by running the command, which is exactly the property that was built in last week. The hand-written `streamelements-tip-event-tags.md` and its 301 redirect were removed by hand, redirect included - pointing a preserved URL at a page that no longer exists is worse than letting it 404.
- **Copy that counted the services was recounted.** Six donation services became five (Ko-fi, Streamlabs, Fourthwall, Buy Me a Coffee, Throne), including "six pipes" in the `latest()` section and the three meta descriptions on the homepage. The code sample under it loops `$latestServices`, so it dropped a line on its own.
- **Comparative mentions stay.** "StreamElements-style widget" in `EventFeedAppender`, "other bots (StreamElements, Nightbot, Fossabot)" in the lists help, and the line in the integrations section about every overlay tool being owned by a donation platform are all describing the market, not advertising an integration. The last one arguably reads better now.
- **Twitch is not the same case, before anyone asks.** Twitch is the substrate; there is no product without it. StreamElements was one of six interchangeable donation sources, and declining an optional integration is a choice that exists.

## August 4th, 2026 - fix(templates): Add to OBS is a main tab now, not a code editor tab

The Builder replaces the Code tab wholesale for overlays composed from blocks, which is the point of it. What went unnoticed is that Add to OBS lived physically inside the code editor, as the fifth entry in its vertical HEAD/BODY/CSS/TW3 strip. Overriding the Code tab took the browser-source URL with it, so a Builder overlay could be composed, saved and previewed, and then had no way at all of reaching OBS.

Add to OBS is now the last main tab on both the overlay page and the edit page, alongside Details and Controls, where it no longer depends on which editor is rendering underneath it.

- **The panel is one component now**, `components/templates/AddToObsPanel.vue`. The heading, the prose, the "you are adding an alert directly to OBS" warning and the button existed as two near-identical copies, one in show.vue and one in TemplateCodeEditor.vue. Moving it was the moment to stop having two.

- **Digits still map to tabs, and now they map to all of them.** The edit page runs to ten tabs on an alert overlay, so 1-9 select the first nine and 0 takes the tenth. The old loop stopped at 8 while alerts already had nine tabs, which meant Effects had no key; it does now. The overlay page tops out at seven.

- **Blocks still do not get the tab.** A block is a Builder ingredient, not a standalone overlay, so there is no browser source to add. Same gate as before, moved with the panel. On the overlay page it stays owner-only for the same reason as always: the URL carries a user-scoped token.

- **The button is `type="button"` now.** It sits inside the edit page's form, where a bare button is a submit button, so clicking Add to OBS opened the dialog and saved the overlay at the same time. That was true in its old home too and was easy to miss, because the save happened behind a modal that had just told you to look away from your stream.

- **TemplateCodeEditor lost its `template` and `templateType` props.** They existed only to feed the OBS tab, and create.vue never passed them, which is why the tab was already absent when creating an overlay.

## August 4th, 2026 - feat(moderation): public overlays can be reported

Every public overlay had a share URL, an OpenGraph card and a copy button, and no way at all to tell anyone something was wrong with it. The gallery is user-generated content served from our domain, so the absence of a report path was the gap.

There is now a Report button on every public overlay preview, and a User Reports page in the admin panel where the reports land.

- **The report row renders even when the overlay has no description.** The old block was `v-if="template.description"`, so on a description-less overlay the entire container was absent. Putting the button inside it would have made reporting available only on overlays whose owner happened to write a description. The description is now the optional half of that row, not the thing that gates it, and `ml-auto` rather than `justify-between` keeps the button hard right either way.

- **Logged-out visitors can report.** This is the point of the feature: someone arriving from a shared link is exactly who spots a problem, and they are the least likely to have an account. They give an email address instead of an identity. That email is never verified and is stated as unverified in the admin table, because a typed-in address proves nothing.

- **No captcha.** Three cheap layers instead: a honeypot field, a timing trap, and a tight per-IP throttle (3/hour, 10/day). The timing trap's render timestamp is signed with `Crypt::encryptString`, so a bot cannot back-date the field to look like a slow human. It deliberately never expires - its only job is to prove the timestamp came from us, and expiring it would silently reject a real person who left the tab open. Turnstile stays an option if this ever gets abused; it was not worth an external processor in the privacy policy on day one.

- **Every rejection returns the ordinary success response.** Honeypot, timing trap, forged ticket, duplicate submission: all of them redirect back exactly like a real report, and write nothing. Telling a bot which check it tripped is how it learns to pass the next one. Four tests assert this, which is the only way to keep it from being "fixed" into a helpful error message later.

- **Reports outlive the overlay they are about.** `overlay_template_id` is nullable with `nullOnDelete`, and the slug and name are snapshotted onto the row. Deleting the overlay is often the outcome of acting on the report, so cascading the delete would erase the record of why it happened. The admin table strikes through the name and says "overlay deleted".

- **Deleting a report copies its reason into the audit log first.** The audit log is the append-only record of what admins acted on; a report vanishing from it without a trace would defeat that.

- **One open report per reporter per overlay**, which stops a double-clicked submit button and stops one person padding the queue.

- **Retention is 180 days after an admin marks a report handled**, swept daily. Only reports actually marked as read are swept, so nothing disappears out of the queue unreviewed. That sweep is what caps how long the reporter's email and IP are kept, and section 6 of the privacy policy now commits to it in those words.

The privacy policy gained a "Reporting a public overlay" subsection covering what is stored, that the IP is for spotting mass filers and nothing else, that reports are admin-only and the reported user is never told who filed, and how to have one deleted.

The dialog copy states where the report goes and stops there. It does not promise review, a timeframe, or an outcome.

**The route is named `reports.store`, not `overlay.report`.** The first name shipped broken: `config/ziggy.php` hides `!overlay.*` from every frontend payload, and Ziggy's `filter()` returns `false` the moment a `!` pattern matches, so a negation beats an explicit include. The name could not be added back to any group, and hitting Submit threw `route 'overlay.report' is not in the route list` in the browser. Narrowing `!overlay.*` into per-route negations would have fixed it too, at the cost of turning a blanket deny into an allowlist of denies where the next `overlay.*` route silently ships to every client. Moving the route out of that namespace keeps the deny intact; the URL is still `/overlay/{slug}/report`, and the name now pairs with `admin.reports.*`. A parametrised test asserts all three Ziggy groups can resolve it, and was checked to fail without the guest entry.

## August 4th, 2026 - feat(reference): integration controls are generated from the drivers now, not remembered

The reference is a vault of hand-written markdown, which is the right call for Twitch tags: they change when Twitch changes, which is rarely and loudly. It is the wrong call for integration controls, which are defined in PHP and change whenever a driver does. Left to hand-maintenance, it had converged exactly where you would expect: 4 of 7 services documented, none of them completely, and every one of the four asserting that the shared donation schema covered "all four integrations" when seven drivers provision those same six keys.

Finding them was itself the argument. Ko-fi's page was filed as `ko-fi-auto-provisioned-controls` while its service key is `kofi`, so it did not turn up in a search for the thing it documents. Buy Me a Coffee, Throne and GPS had no page at all.

There is now an `integration-controls` category, emitted from `getAutoProvisionedControls()` on each registered driver. Eight pages: one per service, plus an index.

- **`php artisan help:build-integration-controls`** reads `ExternalServiceRegistry` and writes the markdown. Adding a service to the registry adds its reference pages, its sitemap URLs and its rows in `help-reference-index.json`, with no separate documentation step to forget.
- **The output is committed, not gitignored.** That way the diff appears in review when a driver changes, and - the part that made this cheap - every existing consumer keeps working untouched. The Blade pages, the sitemap, the JSON index and the Alt+R palette's Vite glob all already handle `.md` files. Zero consumers changed.
- **`--check` fails when the committed files no longer match the drivers**, and a test runs it. Verified the way these things have to be verified: renamed one Ko-fi control label, watched it fail and name `kofi.md`, put it back.
- **The shared six are separated from what each service adds**, because flattening them into one table is what makes someone think `latest_item_name` is portable. Ko-fi, StreamLabs, StreamElements and Fourthwall have exactly the six; Buy Me a Coffee adds one; Throne adds three. GPS shares none of them and is labelled as not using the schema at all rather than pretending to extend it.
- **`ExternalServiceRegistry::displayName()`** now owns the service-name map. It was a private method on `IntegrationController` and a second inline copy in `AdminUserController`; the generator needed a third, which is how you end up with "Streamlabs" and "StreamLabs" both being correct somewhere. IntegrationController delegates now. AdminUserController still has its copy and could follow.
- **`llms.txt` carries the shared-schema rule inline** and points at the generated index for exact keys, so a model reading the file alone learns that swapping `c:kofi:` for `c:streamlabs:` is a valid port.

- **The four hand-written pages they replace are gone**, with 301s from their old URLs to the generated ones. They were filed under `eventsub-tags` despite documenting controls rather than EventSub tags, nothing anywhere linked to them, and their central claim had gone stale. The reference is the best-indexed part of the site, so retiring a page there means redirecting it, not deleting the URL.

The Ko-fi, StreamLabs, StreamElements and Fourthwall `*-events` and `*-event-tags` entries are untouched: those document `event.*` payload fields, which is a different thing that is still hand-written for good reason.

## August 4th, 2026 - docs(llms): the homepage now says out loud that machines are welcome

Yesterday's work gave `/llms.txt` a page pointing at it inside the reference. The reference is well indexed, so that was the right first move, but it left the strongest page on the site out of the chain: the homepage is plain Blade, canonical, priority 1.0, and the first thing anything crawls. Its only mention of the file was `<link rel="llms-txt">` in the head, which is exactly the non-link that started this whole problem.

The footer now carries the invitation as visible body copy.

- **"Reading this as a machine? You are welcome here, and this is not a grudging robots.txt allowance."** Four links follow: the file itself, its explainer page, `/help.md` as the crawl entry point, and the JSON tag catalogue.
- **Visible, not hidden.** A hidden keyword block would be a spam signal, and this is a real invitation to a real audience. It sits in the footer where it does not interrupt the pitch above it.
- **The chain is now closed from the top**: homepage (priority 1.0, crawled) to `for-machines/llms-txt` to `/llms.txt`, with the reference's own 140-page footer as a second path in.
- A test pins the homepage anchor, since losing it would silently undo the part of this that matters most.
- **"No rate limit" came back out of all four places it had been written.** The math is not the problem: `llms.txt` is 24KB raw and 9.7KB gzipped, served by Caddy's file server with no PHP process and no database query behind it, so ten thousand requests is about 97MB and a rounding error against the transfer allowance. The problem is the sentence. It advertises the absence of protection, and it is a promise that becomes a lie the day we want to add one. "No login, no API key, nothing to sign up for" says the useful part and commits to nothing. Both machine-facing entries now ask politely for caching instead.

## August 3rd, 2026 - docs(llms): llms.txt now has a page pointing at it, because a meta tag is not a link

Overlabels has published a complete overlay-authoring guide at `/llms.txt` for a while, and every attempt to get an assistant to actually read it ran into the same wall: it would not fetch a URL nothing had indexed, and nothing would index a URL nothing linked to. The `<link rel="llms-txt">` in the document head reads like a link but is not one - crawlers follow anchors, and `llms.txt` is a convention rather than a ratified standard, so no crawler goes looking for it on its own. The file was published and invisible.

It now has a page whose whole subject is the file, on the one part of this site a crawler can actually read.

- **`/help/reference/for-machines/llms-txt`.** The reference vault is plain server-rendered Blade - every `/help/*` prose page is an Inertia shell that hands a fetcher ~27KB of `<head>` and no words. So the reference is the only crawlable HTML documentation on the site, and that is where this belongs. The entry says what the file contains, states in plain language that any model may read it, and includes a copy-pasteable prompt for handing the URL to an assistant.
- **Two neighbours, so the category is not a lonely SEO page.** `markdown-endpoints` documents the "append `.md` to any help URL" convention, and `help-reference-index-json` documents the full tag catalogue as JSON. All three were already described inside `llms.txt` itself, with nowhere on the web to point at.
- **Body copy on `/help/reference` proper**, in the article column above the fold - the highest-priority page in the section, and the first thing in `<main>`. Not a badge, not an icon link. There is a comment in the Blade saying so.
- **A footer on every reference page**, so all 140 of them link to the file rather than just the index.
- **JSON-LD** naming `llms.txt` as a free `DataDownload` with `encodingFormat: text/plain`, which is the machine-readable way to say the thing the prose says.
- **The link is reciprocal now.** `llms.txt` §11 points back at the page that explains it, and `sitemap.xml` moved the file from priority 0.5 to 0.9 - nothing on this site matters more to a machine reader.
- **`@context` is a Blade directive.** Writing the JSON-LD out as literal JSON compiled `"@context"` into a call to the Context facade and swallowed the rest of the template, which took the entire reference down with it. The structured data is built as a PHP array and `json_encode`d, so Blade never sees the `@`-prefixed keys. `@type` and `@id` are not directives today; nothing says they will not be.
- **Eight tests pin the chain** from sitemap to index page to explainer to the file and back, including one that fails if `public/help-reference-index.json` is left unregenerated after a new entry is added.

## August 3rd, 2026 - ui(events): the events feed's empty state now offers the way out instead of describing it

"No events match your filters. Try widening the time range or clearing search." Two problems: the advice was static, so it suggested clearing a search you had not typed and widening a range already set to All Time; and it described an action rather than offering one, while the filter panel it refers to is collapsible, so the search box was often not even on screen.

The empty state now says which of your filters is actually responsible, quotes the search term back at you, and makes clearing it a button.

- **Three branches, each one true.** A search is named and clearable; a narrowed time range is called out on its own; anything else falls back to the plain message. No sentence mentions a filter that is not set.
- **`EmptyState` grew a default slot**, so callers that need markup in the copy can pass it while `message` keeps working. The slot falls back to `message`, and every existing caller passes a plain string or the named `action` slot, so nothing else changed.
- **Clearing cancels the pending debounce** before it applies, so a keystroke still in flight cannot land after the clear and re-filter the list behind you.
- **All three feeds got it**, since the same sentence was copy-pasted across the token-authed feed, `/dashboard/recents` and `/dashboard/events`.
- **`/dashboard/events` also got the echo guard** that Recent Activity received earlier the same day. It had been carrying the identical character-eating search box the whole time - same wholesale replacement of the local filter object on every response, same one-round-trip-stale value written back into the field being typed in. Found while adding the empty state to the same file.
- **Then all of it got deduplicated**, because writing the same guard twice is how the second page came to be missing it in the first place. `useEventFilters` now owns the filter ref, the echo guard, the debounce and the clear for both Inertia feeds; `EventsEmptyState` owns the empty copy for all three. 200 lines out of the three consumers, ~90 back as shared code, and one place left to get it wrong.
- **The token-authed feed deliberately does not use the composable.** Its filters are local state that never round-trips through props, so it has no echo to guard against - wiring it through a watcher that watches nothing would be indirection bought with no bug fixed. It shares the empty state and nothing else.

## August 3rd, 2026 - ui(events): the list-feed card on Recent Activity is collapsed by default

"Send these events to a list" occupied most of the first screen of Recent Activity: a title, an explanatory paragraph, a status block, a three-column configuration grid, an event-type fieldset and a save button, all above the events you came to look at. Since the hash-authed feed route landed, mirroring events into a list is a cool thing to have rather than something you need on the way in, and it was pushing the actual point of the page below the fold.

It is now a disclosure. Collapsed you get the title and a chevron; expanded, everything it had before, unchanged.

- **The heading is the toggle** - `<h3>` wrapping a `<button aria-expanded>`, which is the standard disclosure shape, so it keeps its heading semantics and announces its own state instead of being a div that happens to respond to clicks.
- **One thing survives the collapse: whether a feed is actually running.** The old card deliberately kept "which lists are receiving events" always visible, and hiding that outright would mean a live feed with no sign of itself anywhere. Collapsed, a green dot and "2 lists receiving" sit next to the title - but only when something is on. If you have no feeds, which is the case for everyone this change is for, it really is just the title and the chevron.

## August 3rd, 2026 - fix(events): searching "poll" found nothing, searching "po" found polls

Typing `Po` in the Recent Activity search returned Poll started, Poll updated and Poll ended. Typing `Poll` returned nothing. Being more specific made the results worse, which is the sort of thing that makes you distrust a search box entirely.

The search only ever looked at the stored payload - `event_data::text` for Twitch, `normalized_payload::text` for external services. A poll payload does not contain the word "poll" anywhere; the word lives in the event type (`channel.poll.end`) and in the label the feed renders in the browser, neither of which was being searched. The `Po` results were a coincidence: poll payloads carry a `channel_points_voting` key, and "po" is a substring of "points". Nothing about polls was ever actually matching.

- **The event type is searched alongside the payload now.** That is where the words people actually type live - "poll", "raid", "cheer", "follow", "donation". Both event tables get it, so it works the same either side of the union.
- **The two conditions are grouped, and that matters more than it looks.** An ungrouped `orWhere` would have escaped the surrounding `user_id` scope and the GPS exclusion, turning a search into a way to read every user's events. The same `applyFilters()` also backs the new bulk delete, so the identical mistake would have deleted other people's rows. There is a test on each path that fails if the grouping is ever removed.
- **Still not searchable: the multi-word labels.** The feed renders "Poll updated" client-side while the server's catalogue calls the same event "Poll Progress", so there is no single string to match against. Searching "poll" finds it; searching "poll updated" does not. Worth fixing by making one of those the source of truth rather than by teaching the query about both.

## August 3rd, 2026 - fix(events): the recents search box was eating characters

Typing in the search filter on Recent Activity felt like it was fighting you. Pause for half a second reaching for Shift and the search would fire early; keep typing while it loaded and the letters you typed would vanish a beat later. It read as "the input is disabled while loading and skips keys", but the field was never disabled and never missed a keystroke. It was throwing them away afterwards.

`recents.vue` kept a local copy of the filters and watched the server's copy to stay in sync, replacing the whole object on every response. The search input is bound to that object. So every response wrote the server's `search` value back into the box you were still typing in - and a response can only ever carry what you asked for one round trip ago. Type `test`, pause, request goes out; type `F`; the response for `test` lands and the box snaps back to `test`. The `F` is gone. The slower the response, the more characters it swallows, which is why it looked like loading was the thing blocking input.

- **The watcher now ignores its own echo.** We remember the term we last dispatched, and when a response comes back carrying exactly that, we leave the box alone - local state is by definition at least as current. A value we did not ask for is real news (back/forward, someone else's link) and is still adopted. The dropdowns sync unconditionally, since you cannot be mid-edit in a `<select>`.
- **The debounce went from 300ms to 500ms.** 300 is tuned for a search box that is the whole page. This one filters a table you are reading, and reaching for a modifier key routinely takes longer than that.
- **Search stopped littering browser history.** The filter visit was pushing an entry per keystroke batch, so leaving the page meant pressing back through `t`, `te`, `tes`. It replaces now.
- **A filter change only fetches the feed.** It was re-sending the template list, the facet counts and the user's lists on every keystroke. Those cannot change from a filter, so the visit asks for `recentEvents` and `filters`, and the controller defers the other three behind closures so their queries do not run either. Making the response smaller also shrinks the window the bug above lived in, which is why it was worth doing in the same pass rather than later.

## August 3rd, 2026 - feat(events): select and delete rows from the recent-events feed

Every integration has a test button, and every test event lands in your feed and stays there. Fire a few Twitch CLI events while wiring up an alert and you are left with `testFromUser raid 56171 viewers` sitting in your history forever, next to the real raids.

The obvious fix was to detect test events and hide them, and that turns out to be a trap. The donor name is the only thing the payloads have in common, and it is different for every service: Twitch CLI sends `testFromUser`, Ko-fi sends `Jo Example`, Throne sends `marie_123`, StreamLabs sends `Kevin`, Fourthwall sends `supporter username`, Buy Me a Coffee sends `John`, and StreamElements picks a fresh random name on every payload. Filtering on any of those means the day a real viewer called Kevin tips you, their donation quietly vanishes. There is no name-based rule that is safe.

Test mode looked like the way out, since it already exists per integration and the webhook controller already branches on it - but it does not mean what the name suggests. It only tells the system to stop rejecting repeated UUIDs, so the *first* test event you fire lands identically whether test mode is on or off. Flagging on it would have missed the single test that most people fire, which is exactly the one that causes the clutter.

So no detection. The feed just lets you select rows and delete them.

- **Selection keys are `source:id`, not `id`.** The feed is a `UNION ALL` over `twitch_events` and `external_events`, whose ids both start at 1 and collide freely. A flat id list would have deleted a stranger's Ko-fi tip because it happened to share a number with the follow you picked. A test pins this down specifically.
- **The filter bar is the bulk selector.** Alongside per-row checkboxes and select-all-on-page there is "select all N matching these filters", which re-derives the set server-side from the same `normalizeFilters()` the feed renders with. Filter to StreamLabs, search "Kevin", delete the 47 that match. It sidesteps the identification problem entirely by letting you define what junk means per cleanup, rather than shipping a rule that has to hold for seven services forever.
- **Deleting a row takes its hidden rows with it.** Gift-sub bombs fold N recipient events under the gifter, and a resub hides the bare `channel.subscribe` that Twitch fires alongside it. Those rows exist but are not rendered, so deleting only what you can see would have left them behind to reappear ungrouped on the next load, looking like the delete had failed.
- **`user_id` scoping is the authorization boundary, and it is load-bearing twice over.** `twitch_events.user_id` is nullable for events from broadcasters we do not know, so a delete scoped by id alone would reach rows belonging to nobody. GPS rows are excluded on the way out for the same reason they are excluded on the way in: they never appear in this feed, so they can never be picked from it, spoofed source or not.
- **No delete on the token-authed `/events/feed`.** It shares the same table component, behind a prop the recents page passes and the feed does not. Overlay tokens live in an OBS browser source URL and are write-capable for exactly two actions today; a destructive third is a worse trade than the convenience is worth.
- **Nothing is rolled back.** Deleting a donation event does not decrement `donations_received`, because those controls are running counters rather than a projection of the event tables. The confirm copy says so, and points at nothing, because the per-integration seed actions already exist for that.
- **Deleting while live is allowed.** A lock would not have protected the numbers anyway: session stats are a time-window query computed at read time, so an event deleted thirty seconds after the stream ends moves them exactly as much as one deleted mid-stream. It would only have postponed the same mutation while blocking the case that actually needs it, which is clearing follow-bot spam in the middle of the raid. The confirm says the stats will move and gets out of the way.

## August 1st, 2026 - fix(auth): logging out landed on a 404 instead of the homepage

Account > Log out did log you out, then dropped you on a 404 at `/logout`. Same day, same root cause as the dialog fix below it: `/` is a plain Blade view, and Inertia has no idea what to do with one.

Yesterday's backstop navigates to the URL of the visit that is in flight, because the `httpException` payload is typed as status, data and headers with no URL on it. For a link click those two URLs are the same, which is every case that was tested. Logout is the one flow where they diverge: the visit starts at `POST /logout`, the server redirects to `/`, the XHR follows that redirect itself, and Inertia gets a full HTML document back. The backstop then fired and sent the browser to the URL it had recorded - `/logout` - as a GET. There is no `GET /logout`, so `Route::fallback` served the 404. Logged out, stranded, one route short of home.

- **`Inertia::location()` instead of `redirect('/')`.** It answers the XHR with 409 + `X-Inertia-Location`, which Inertia turns into a real navigation natively, never reaching the backstop. This is already how `RedirectIfUnauthenticated` and the 419 handler send an Inertia request to a non-Inertia page - it is the existing pattern, not a new one. A session teardown wants a fresh document anyway.
- **The backstop now only resumes GET visits.** A non-GET visit that ends on plain HTML got there by a redirect, so the recorded URL is guaranteed to be the wrong target - and re-issuing a write as a GET is a bad outcome even when a route does exist to receive it. One-line guard, closes the class rather than the instance.
- **A duplicate `POST /logout` in `web.php` was deleted.** It had been dead the whole time: `RouteCollection` keys on method+URI, `auth.php` is required further down the same file, and the later registration wins. `route:list` only ever showed one. Worth knowing before debugging a route that looks right and never runs.
- **Logout had no tests at all**, which is why this shipped twice in two forms. It has three now, and the one that matters was confirmed to fail against the old controller.
- **`BotFollowageTest` was failing on the calendar, not on the code.** It asserted "2 years, 2 months, 30 days" off a `now()->subMonths(3)` fixture. `HumanDuration` walks calendar units, so subtracting 3 months from a high day-of-month clamps into a short month and the answer shifts by a whole unit - meaning the test passed or failed depending on the day the suite ran. It now freezes the clock in `beforeEach` exactly like `BotAccountageTest` already did, anchored to the 15th because no month is short enough to overflow it. With `now()` frozen the span is exact, so both duration assertions moved from `toContain` fragments to the full sentence chat actually sees.

## August 1st, 2026 - feat(updates): the updates feed is public

`https://overlabels.com/updates/overlabels-development-highlights-july-2026` bounced anyone who was not logged in. That is the wrong shape for what these posts are: announcements, written to be linked from Twitch, from Discord, from anywhere someone might first hear about the project. A link that only works if you already have an account is not a link.

Both routes moved out of the `auth.redirect` group and now sit with the other public routes, next to `/help`. Nothing else about them changed.

- **No new visibility logic was needed.** `UpdateController` already queried through the `published()` scope on both actions, which is `published_at <= now()`, so a post dated into the future stays a 404 for guests exactly as it did for logged-in visitors. Moving the route did not open a hole, because the gate was never the middleware.
- **`updates.*` joined the `guest` Ziggy group.** The index page calls `route('updates.index')` when you type in the search box or pick a tag, and the guest group is a whitelist, so without this the filters would have thrown for the exact visitors the change is for. Worth remembering for the next public page: making a route public is two edits, not one.
- **Guests get an Updates item in the sidebar.** Logged-out visitors see their own nav list, so the page was reachable but not findable. It now sits under Learn, right below Help.
- **A test pins all of it down**, including the future-dated 404, because "this page is public" is the kind of property that quietly stops being true.
