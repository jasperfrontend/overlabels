## OL-2609-014 - style(events): redesign the event list into dense kind-tagged rows

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-014`

### Surface
- `resources/js/components/EventsTable.vue` - row layout, typography and label copy rewritten; replay, selection and grouping logic untouched

### Claims
- **C1** [code] Each row renders `kind(event)` as a monospace uppercase tag, then `who(event)` in `font-semibold`, then `phrase(event)` and `details(event)`; the previous single-string `label()` function and `twitchEventLabels` map no longer exist in the file.
- **C2** [code] `twitchRowLabels` maps every Twitch event type the old `twitchEventLabels` covered to a `{ kind, phrase }` pair, and hype train rows get their kind from the `HYPE_TRAIN_PREFIX` branch with the level/progress sentence moved into `hypeTrainPhrase()`.
- **C3** [code] `externalEventLabels.kofi.donation` reads `'Ko-fi tip'`, not `'Ko tip-fi'`.
- **C4** [code] External events from a service missing in `externalEventLabels` fall back to `eventLabel({ eventType, service: source })`, newly imported from `@/composables/useEventColors`, instead of the raw `` `${source}: ${event_type}` `` string.
- **C5** [code] Every replayable row (`canReplay(event)`) renders a "Replay" pill button in its meta column that calls the same `openConfirm()` as the row click, with `md:opacity-0 md:group-hover:opacity-100` so it is hover-revealed from `md` up and always visible below.
- **C6** [code] `relativeTime()` returns compact single-unit ages (`now`, `Nm`, `Nh`, `Nd`, `Nw`) and the full locale timestamp is available on the time element's `title` attribute via `fullTime()`.
- **C7** [code] Rows sit in a `divide-y divide-foreground/5` container with no vertical gap between them, and each row keeps the `collection-row` skin plus `eventHoverBorderClass(event)`.
- **C8** [code] `ProviderIcon` renders inside a `bg-foreground/[0.06]` badge and carries `eventDotClass(event)` at rest, not only on hover.

### Unchanged
- The Claude Design mock this implements shows the icon monochrome at rest and tinted on hover; C8 deliberately keeps the at-rest event-type color, because the shape-plus-color pairing on provider icons is a recorded decision in `CLAUDE.md` (Provider Icons). Nothing in `useEventColors.ts` or `providerIcons.ts` is in the diff.
- `displayRows` (gift-sub grouping and resub dedup), the selection functions, `replay()`, `replayViaToken()`, `canReplay()`, `who()`, `details()` and the confirm `Popover` flow are carried over verbatim; the diff touches presentation around them, not their bodies.
- The component's contract (`events`, `token`, `selectable`, `selection` props; `replay-result`, `update:selection` emits) is unchanged, so none of the four caller pages (`dashboard/index`, `dashboard/events`, `dashboard/recents`, `events-feed/EventsFeed`) is in the diff.

### Risk
Purely visual, but on every surface at once: the dashboard card, /dashboard/events, /dashboard/recents and the token-authed events feed all show the new rows, and relative times now read "2m" instead of "2 minutes ago".
