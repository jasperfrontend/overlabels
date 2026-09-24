## Audit of OL-2609-014 - style(events): redesign the event list into dense kind-tagged rows

**Audited:** 2026-09-24
**Commit:** ff85c9996c838b5385f542c5451a171f16b29fe3
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `resources/js/components/EventsTable.vue:464-471 @ff85c99` - kind span has `font-mono ... uppercase` (465), `who(event)` span has `font-semibold` (469), then `phrase(event)` (470) and `details(event)` (471); no `function label` and no `twitchEventLabels` anywhere in the file @ff85c99 (grep empty); same @HEAD (486, 490) |
| C2 | CONFIRMED | `EventsTable.vue:284-301 @ff85c99` - `twitchRowLabels` has all 16 keys the removed `twitchEventLabels` had (follow, subscribe, subscription.message, subscription.gift, cheer, raid, redemption add/update, stream.online/offline, poll x3, goal x3); `kind()` returns `'hype train'` on `HYPE_TRAIN_PREFIX` (336), level/progress text in `hypeTrainPhrase()` (348-356). @HEAD `twitchRowLabels` also has `channel.chat.notification` (OL-2609-023) |
| C3 | CONFIRMED | `EventsTable.vue:305 @ff85c99` - `donation: 'Ko-fi tip'`; same @HEAD line 315 |
| C4 | CONFIRMED | `EventsTable.vue:6 @ff85c99` imports `eventLabel` from `@/composables/useEventColors`; line 339 falls back to `eventLabel({ eventType: event.event_type, service: event.source })`; `eventLabel` exported at `useEventColors.ts:97 @ff85c99`; same @HEAD line 349 |
| C5 | CONFIRMED | `EventsTable.vue:500-510 @ff85c99` - `<button v-if="canReplay(event)" ... @click.stop="openConfirm(event)">Replay</button>` in the meta column; row click (450) calls the same `openConfirm(event)`; class holds both `md:opacity-0` and `md:group-hover:opacity-100` (503), with `md:group-focus-within:opacity-100` between them. @HEAD the `v-if` is replaced by an `invisible` class on non-replayable rows (526, OL-2609-015 C4) |
| C6 | CONFIRMED | `EventsTable.vue:402-417 @ff85c99` - returns `'now'`, `` `${Math.floor(diff / minute)}m` ``, `h`, `d`, `w`; `fullTime()` returns `toLocaleString()`; time span carries `:title="fullTime(event.created_at)"` (493); same @HEAD (423, 514) |
| C7 | CONFIRMED | `EventsTable.vue:427 @ff85c99` - `flex flex-col divide-y divide-foreground/5`, no `gap-*`; row class at 442-443 contains `collection-row` plus `eventHoverBorderClass(event)`; same @HEAD 448 |
| C8 | CONFIRMED | `EventsTable.vue:459 @ff85c99` - wrapper span has `bg-foreground/[0.06]`; line 462 `ProviderIcon ... :class="eventDotClass(event)"` unconditional; same @HEAD 480, 483 |

### Surface
Complete. (`git show --stat ff85c99`: `EventsTable.vue` plus the exempt claim file and `docs/changelog/changelog-2026-09.md`.)

Unchanged lines checked: `useEventColors.ts` and `providerIcons.ts` are not in the diff; no hunk in ff85c99 touches the bodies of `displayRows`, `isRowSelected`/`toggleRow`/`togglePage`, `replay()`, `replayViaToken()`, `canReplay()`, `who()`, `details()` or the `PopoverContent`; `defineProps`/`defineEmits` (13, 28 @ff85c99) are outside every hunk, and none of the four caller pages is in the diff.

### Findings
- **F1** scope, not disclosed - `EventsTable.vue:434 @ff85c99` changes the selection checkbox's `aria-label` from `label(event)` to `kind(event)`, so its accessible name drops the phrase (a poll begin, progress and end row are all "Select poll"; a hype train row loses its level and progress). No claim, Surface line or Unchanged line mentions it, and the Risk section calls the change purely visual. A follow-up claim should record it, or the label should include `phrase(event)`.

### Notes
- `who()` and `details()`, listed as carried over verbatim, were changed later by OL-2609-015, OL-2609-023 and OL-2609-051, each of which discloses the change in its own Surface.
- `relativeTime()` @ff85c99 computes `Date.now() - created`, so a future timestamp (clock skew) returns `now`; before the change it returned "in N minutes". This fits C6's list of outputs and is not raised as a finding.
- The removed `:id="label(event)"` attribute on the old inner div was referenced nowhere in `resources/js` or `tests` @ff85c99.
- No tests are named in the claim and none were run.
