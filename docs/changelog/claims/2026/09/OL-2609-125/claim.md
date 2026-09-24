## OL-2609-125 - feat(shortcuts): G then a letter jumps to a main navigation page, and the Ctrl+K dialog groups shortcuts into sections

**Shipped:** 2026-09-24
**Commit:** `git log --grep=OL-2609-125`

`useKeyboardShortcuts` matched one keystroke against one key list. It now accepts a chord: a
combination string with whitespace-separated steps (`'g o'`) arms a prefix on the first key and
fires on the second, within a timeout. The sidebar registers one chord per main navigation item,
keyed on a new `shortcut` field beside the item's `href`, under a `group` the Ctrl+K dialog renders
as its own section.

### Surface
- `resources/js/composables/useKeyboardShortcuts.ts` - `Shortcut.keys: string[]` becomes `steps: string[][]`; `parseKeyCombination` returns steps; `formatKeyCombination` joins steps with " then "; new `pressedKeys`, `resolveKeystroke`, `CHORD_TIMEOUT_MS`, `ShortcutListing`; `handleKeyDown` keeps a module-level `pending` prefix with a timer; `register()` gains a `group` option; `getAllShortcuts()` returns `group`
- `resources/js/composables/useKeyboardShortcuts.test.ts` - new file, 13 tests over the exported pure functions
- `resources/js/components/AppSidebar.vue` - `shortcut` letter on each of the twelve items in the three signed-in nav groups; registers `g <letter>` for each in `onMounted`
- `resources/js/components/KeyboardShortcutsDialog.vue` - groups the listing by `group` into sections with a heading, ungrouped first
- `resources/js/types/index.d.ts` - `NavItem.shortcut?: string`

### Claims
- **C1** [code] `parseKeyCombination('g o')` returns `[['g'], ['o']]`, and `parseKeyCombination('ctrl+space')` returns `[['ctrl', ' ']]`, so every pre-existing single-step registration parses to one step with the same keys as before.
- **C2** [code] `register()` with an array combination wraps it as a single step (`[combination]`).
- **C3** [code] `resolveKeystroke()` returns `fire` for a shortcut whose steps equal the armed prefix plus the pressed keys, `prefix` when no such shortcut exists but at least one shortcut continues past this step, and `none` otherwise.
- **C4** [code] In `resolveKeystroke()`, a complete match is returned as soon as it is found, before any longer chord sharing the same start is considered.
- **C5** [code] `handleKeyDown()` returns before doing anything when `event.key` is `Control`, `Alt`, `Shift` or `Meta`.
- **C6** [code] `handleKeyDown()` clears the pending prefix when the target is a text-entry element (`isTextEntryTarget`) or inside `[role="dialog"]`, and a `prefix` resolution in either place returns without arming.
- **C7** [code] A `prefix` resolution outside inputs arms the prefix, calls `event.preventDefault()`, and starts a `setTimeout` of `CHORD_TIMEOUT_MS` (1500) that clears it.
- **C8** [code] When a prefix is armed and the keystroke resolves to `none`, the prefix is cleared and `event.preventDefault()` is called; no callback runs.
- **C9** [code] The modifier rule inside inputs and dialogs is unchanged in effect: a `fire` resolution there runs its callback only when some step of the shortcut contains `ctrl`, `alt` or `meta`.
- **C10** [code] `AppSidebar.vue` registers, in `onMounted`, one shortcut per item of `mainNavItems`, `alertsNavItems` and `learnNavItems` that carries a `shortcut`, with id `go-to-<title lowercased>`, combination `g <shortcut>`, description the item's title, and group `Go to`.
- **C11** [code] The letters are: Overlays `o`, Alerts `a`, Blocks `b`, Lists `l`, Kits `k`, Products `p`, Recent `e`, Streams `s`, Routes `t`, Help `h`, Reference `r`, Updates `d`. No two items share a letter.
- **C12** [code] The chord callback calls `window.open(item.href, '_blank', 'noopener,noreferrer')` when `item.target === '_blank'` (Help and Reference) and `router.visit(item.href)` otherwise.
- **C13** [code] For a signed-out visitor the three computed nav arrays are empty, so `onMounted` registers nothing and `route()` is never called.
- **C14** [code] `KeyboardShortcutsDialog.vue` renders one section per distinct `group` in first-seen order, with the ungrouped section sorted first, and prints a section heading only when there is more than one section; the ungrouped heading reads "Shortcuts".
- **C15** [test] `useKeyboardShortcuts.test.ts` asserts C1 (parse), the " then " formatting, `pressedKeys` ordering, and every branch of C3 and C4, including that `['g']` then `['e']` resolves to `none` while a bare `e` shortcut is registered, and that `['shift', 'o']` does not complete `g o`.
- **C16** [unverified] On overlabels.test in Chrome: G then S from the overlays list navigated to `/dashboard/stream-sessions`; G then O navigated back; typing `go` into the overlay search input filtered the list (`?search=go`) and navigated nowhere; the Ctrl+K dialog on the list page showed a "Shortcuts" section of 5 and a "Go to" section of 12, and on the edit page all 31 entries fit inside a 705 px tall viewport without scrolling.

### Unchanged
- Every existing `register()` call site (`AppLayout`, `CommandPalette`, `ReferencePalette`, `HelpBeacon`, `SidebarProvider`, `TemplateCodeEditor`, `templates/create`, `templates/edit`, `templates/show`, `dashboard/lists/show`) passes a single-step string or array and is not in the diff; C1 and C2 are what keep them matching as before.
- `isTextEntryTarget` is the input guard the chords rely on (C6) and is not in the diff; its `isContentEditable` branch is what keeps CodeMirror covered.
- The sidebar's own rendering (`NavMain.vue`) does not read `shortcut` and is not in the diff; the letter is data on the item only.
- The `Ctrl+K` hint block at the bottom of the sidebar is not in the diff and does not mention the chords.

### Risk
A page that registers a bare `g` shortcut would fire it instead of arming the chord (C4). None
exists today. A stray G on a page swallows the next keystroke within 1.5 s if it completes no chord
(C8), which is the trade for `G then E` not falling through to the overlay page's bare `E`.
