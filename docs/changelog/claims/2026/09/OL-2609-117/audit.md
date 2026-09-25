## Audit of OL-2609-117 - fix(products): every slider and color picker in the chat designer saves

**Audited:** 2026-09-25
**Commit:** 5e7309bb5e4842f875ab4fd1c4d451d8fb2b0e5e
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/pages/products/design.vue:73 @5e7309b` - `const saved = reactive<Record<string, string>>(Object.fromEntries(Object.entries(props.controls).map(([key, control]) => [key, control.value])))`; same at `:85 @HEAD` |
| C2 | CONFIRMED | `design.vue:109 @5e7309b` - `if (!control \|\| saved[key] === value) return;`, and the removed line in the diff is `control.value === value`; same at `:140 @HEAD` |
| C3 | CONFIRMED | `design.vue @5e7309b` - `:115` `saved[key] = value` before the `axios.post` at `:118`; `:123` `saved[key] = data.value` inside `typeof data?.value === 'string'`; `:127` `saved[key] = previous` after `control.value = previous` at `:126`. @HEAD at `:146`, `:154`, `:160`; `writeControl()` has since gained `remember(...)` at `:156` (1ffbc595, no claim) and `expectInFrame([key])` at `:157` (OL-2609-120) |
| C4 | CONFIRMED | `design.vue:111 @5e7309b` - `const previous = saved[key] ?? control.value;`; same at `:142 @HEAD` |
| C5 | CONFIRMED | `design.vue:163-164 @5e7309b` - `controls[controlKey].value = value; saved[controlKey] = value;` in the `applyPreset()` loop. @HEAD the loop moved into `adoptValues()` (`:200-201`, OL-2609-121 C15), still assigning both |
| C6 | CONFIRMED | `design.vue @5e7309b` - `writeControlSoon` bound only at `:449` (`type="color"`) and `:468` (`type="range"` in the number branch); skin buttons `:403`, `<select>` `:421`, boolean checkbox `:434` call `writeControl`. @HEAD `writeControlSoon` still only at `:829`/`:848`; the `font` row is now a `FontPicker` calling `writeControl` at `:790` (OL-2609-119) |
| C7 | UNVERIFIABLE | tagged [unverified]; browser network-log observation |
| C8 | UNVERIFIABLE | tagged [unverified]; browser and prod-row observation |

### Surface
Complete.

### Findings
- **F1** scope - the diff changes the template comment `<!-- A colour. -->` to `<!-- A color. -->` (`design.vue:440 @5e7309b`), which no claim, Surface line or Unchanged line accounts for; the Unchanged line on spelling covers only `writeControlSoon()`'s doc comment. Record it in the next claim that touches this file, or leave it as known-trivial.
- **F2** changed without a claim - commit 1ffbc595 ("keep the chat designer's history entry as current as the database", no `Changelog:` trailer) modified both `writeControl()` (adds `remember(...)`, `design.vue:156 @HEAD`) and `applyPreset()`, the functions C2-C5 describe; C2-C5 still hold @HEAD, and the commit touched only a `.vue` file so the path rule did not require a claim. A maintainer should decide whether that change warrants a claim; OL-2609-120 C6 already refers to `remember()` without disclosing where it came from.

### Notes
- No tests were run: every claim is [code] or [unverified], and Unchanged states no test was added (`vitest.config.mts` is `environment: 'node'`, consistent with CLAUDE.md).
- Unchanged line 2 carries untagged observations ("returned 200 for the knobs that did reach it", "the initial values it wrote at mount were correct") that the claims guide says belong in Claims with a tag; the symbols it names (`OverlayControlController`, `ControlValueUpdated`, `OverlayRenderer.vue`, `compileCssBindings`, `useCssCustomProperties`) are not in the diff.
- Unchanged lines 1 and 3 hold @5e7309b: `writeControlSoon()` (`:133-137`) differs only in its doc comment, and `writeWindowSoon()` is not in the diff.
- `applyPreset()` was later refactored into `adoptValues()` by OL-2609-121, which discloses it.
