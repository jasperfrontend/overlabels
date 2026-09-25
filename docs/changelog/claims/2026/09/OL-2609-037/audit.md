## Audit of OL-2609-037 - fix(dashboard): following a row's call to action clears it from the What's New card

**Audited:** 2026-09-25
**Commit:** 1afebb56a9857992eaaf934ee8dbb71d61ca4c06
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/WhatsNewCard.vue:89,94 @1afebb5` - both branches call `router.delete(route('dashboard.whats-new.dismiss', item.id), ...)`; `dismiss()` at `:49 @1afebb5` calls the same route. `git show --stat 1afebb5` lists only `WhatsNewCard.vue` and the claim file, so no route or controller method is in the diff. Route `routes/web.php:211-213 @1afebb5` (`:253 @HEAD`). Component unchanged since 1afebb5 @HEAD (f28c5be) |
| C2 | CONFIRMED | `WhatsNewCard.vue:93-97 @1afebb5` - `event.preventDefault()`, then `router.delete(..., { preserveScroll: true, onFinish: () => window.location.assign(href) })` with `href = item.cta.href` (`:85`); same @HEAD |
| C3 | CONFIRMED | `WhatsNewCard.vue:86-91 @1afebb5` - `modified = event.ctrlKey \|\| event.metaKey \|\| event.shiftKey \|\| event.altKey`; that branch calls `router.delete(..., { preserveScroll: true })` and returns before `preventDefault` and with no navigation; same @HEAD |
| C4 | CONFIRMED | `WhatsNewCard.vue:83 @1afebb5` - `if (!item.cta) return;`; the anchor at `:145-149 @1afebb5` carries `v-if="item.cta"` and `@click="followCta($event, item)"`; same @HEAD |
| C5 | UNVERIFIABLE | tagged [unverified]; a manual browser observation on a local install, not checkable in-repo |
| C6 | CONFIRMED | `WhatsNewCard.vue:64-73 @1afebb5` - `opened()` is outside the diff hunks (the diff adds only `:75-98` and `:149`), calls `router.replaceProp('whatsNew', ...)` and no dismiss route; `<Link ... @click.capture="opened(item)">` at `:136`. `app/Http/Controllers/UpdateController.php:59 @1afebb5` - `show()` calls `$this->markSeen($request, $update)`; same @HEAD |

### Surface
Complete.

### Findings
None.

### Notes
- Unchanged line 1: `WhatsNewController::dismiss()` (`app/Http/Controllers/WhatsNewController.php:86-94 @1afebb5`, `updateOrCreate` with `dismissed_at`, `return back()`) is not in the diff. Unchanged line 2: `UpdateController.php` is not in the diff; `MarkWhatsNewVisited` was deleted in e262dc0 (OL-2609-036).
- This change reverses OL-2609-036 Unchanged line "The row's CTA link in `WhatsNewCard.vue` is still a plain `<a>` and no longer has a click handler" (`OL-2609-036/claim.md:42`). 037 cites OL-2609-036 inline in its Unchanged section, so it is not a finding, but the citation does not name that line as the one reversed; the 036 audit already records the supersession.
- OL-2609-071 C16 removed the `WhatsNewCard` call site from `dashboard/index.vue`; @HEAD `git grep WhatsNewCard HEAD -- resources/js` returns nothing, so `followCta()` is shipped code with no mounted caller today.
- No `[test]` claims; no tests were run.
