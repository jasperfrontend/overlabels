# Claims Guide

Every change that ships gets a claim file: a short, hard, checkable account of what it did.

This exists so an agent can read a claim, resolve the commit it describes, and **scrutinize it** -
confirm each statement against the tree, catch anything in the diff that was not disclosed, and say
plainly where the record and the code disagree. It is not a changelog and it is not written to be
enjoyed. `docs/changelog/changelog-YYYY-MM.md` is still the prose account and still has its own,
higher bar. Same change, two files, two jobs.

Applies from **September 2026**. August and earlier stay exactly as they are - they record what was
true when they were written.

## Layout

```
docs/changelog/
  changelog-2026-09.md                      prose, real features only, /changeblog reads this
  claims/2026/09/OL-2609-001/claim.md       hard, one folder per change
  claims/2026/09/OL-2609-002/claim.md
  claims/2026/09/OL-2609-003/claim.md
```

One folder per shipped commit. **The folder name is the full ID**, redundant against the path on
purpose: one string - `OL-2609-004` - is the folder, the commit trailer, the prose heading and the
grep. Nothing to reconstruct.

It is a folder rather than a bare file because **the audit lands next to the claim**, as `audit.md`
in the same directory. The claim and its scrutiny stay together, and an unaudited change is a folder
with one file in it.

**No index and no manifest.** `ls docs/changelog/claims/2026/09/` is the index. A second source of
truth would drift, which is the thing this whole arrangement exists to prevent.

**Do not consolidate these back into one file per month.** The per-month prose changelog is a 400 KB
file that new entries get inserted into at the top, and entries have landed out of order in it more
than once. A folder per change has no order to get wrong, no merge conflict at the top of the file,
and lets an agent read exactly one change without loading the month.

## Allocating an ID

`OL-<YY><MM>-<NNN>`, sequence resetting each month. The next one is the last line of:

```bash
ls docs/changelog/claims/2026/09/
```

The ID is decided **before** the commit, which is what lets it go in the commit message without an
amend. It appears in four places, all written by hand, all identical:

- the folder name
- the `##` heading inside `claim.md`
- the `Changelog: OL-2609-004` trailer on the commit
- the prose changelog heading, if the change earned a prose entry

The agent then resolves the exact diff with `git log --grep=OL-2609-004`.

## The file

`Surface` and `Claims` are mandatory. `Unchanged` and `Risk` are omitted when there is nothing to
say. An icon swap is four lines and should be:

```markdown
## OL-2609-012 - style(controls): swap the Wrench icon for Toolbox

**Shipped:** 2026-09-03
**Commit:** `git log --grep=OL-2609-012`

### Surface
- `resources/js/components/ControlsManager.vue` - `Wrench` replaced with `Toolbox`

### Claims
- **C1** [code] `ControlsManager.vue` imports `Toolbox` from `lucide-vue-next` and no longer imports `Wrench`.
```

A real change carries its weight:

```markdown
## OL-2609-004 - fix(twitch): subscribe to channel.cheer so real cheers reach the platform

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-004`

### Surface
- `app/Services/UserEventSubManager.php` - `channel.cheer` added to `SUPPORTED_EVENTS`
- `app/Models/User.php` - `bits:read` added to `REQUIRED_SCOPES`
- `resources/js/components/ReconnectBanner.vue` - `bits:read` labelled "Bits"
- `tests/Feature/TriggerPickerCoverageTest.php` - new file

### Claims
- **C1** [code] `UserEventSubManager::SUPPORTED_EVENTS` contains `channel.cheer`, version 1, condition key `broadcaster_user_id`, `required_scope` `bits:read`.
- **C2** [code] `User::REQUIRED_SCOPES` contains `bits:read`.
- **C3** [code] An account lacking `bits:read` skips `channel.cheer` via the pre-existing `required_scope` gate. It is never sent to Twitch and never enters the failed bucket.
- **C4** [test] `TriggerPickerCoverageTest` asserts (a) every event type the trigger picker offers is in `SUPPORTED_EVENTS`, and (b) the same for every event carrying an amount-variant condition.
- **C5** [unverified] Both assertions in C4 were run against the pre-fix tree and failed, each naming `channel.cheer`.
- **C6** [unverified] Twitch requires `bits:read` for `channel.cheer` v1.

### Unchanged
- The handling path for cheers was never the gap, only the subscription was: `EventTemplateMapping::resolveForEvent()` and the events-feed formatter already accept `channel.cheer` payloads and are not in the diff.
- The `latest_cheer*` controls and per-session bits aggregation consume the same payloads once they arrive; neither is in the diff.

### Risk
Existing accounts need one re-authorization before cheers arrive. Until then cheers are silently
skipped, not errored.
```

### The fields

**Heading** - `## <ID> - <the conventional-commit subject>`. Same subject as the commit. It is not
the anchor (the trailer is), so a later reword is untidy rather than broken.

**Shipped** - the date of the push, `YYYY-MM-DD`.

**Commit** - literally the grep command. It is there so a reader who is not the audit agent knows how
to find the diff.

**Surface** - **must be complete.** Every path in `git show --stat` appears, each with a few words on
what it does. A path in the diff that is not listed is scope creep, and catching it is most of the
point. Two implicit exceptions, never listed: the claim file itself, and the prose changelog file.

**Claims** - numbered `C1`, `C2`, ... so a report can cite them. One assertion each, one tag each.

**Unchanged** - what a reader might reasonably expect to have moved and deliberately did not. This is
where "I fixed the symptom and left the working code alone" gets recorded, and it is what makes an
appearance in the diff readable as a violation rather than a surprise.

An expectation must be built before it can be denied. The reader was not in the room, so a bare noun
("the eager loads didn't move") relates to nothing they know. Each line names the symbol, says what
ties it to THIS change, then states it is not in the diff - "the filter reads the same relations
`index()` already eager-loads for the list's icons; those eager loads are untouched" hands the
reader both the tether and a check they can run. And no smuggled judgments: "was already correct and
already tested" is an assertion, and assertions live in Claims with a tag or nowhere.

**Risk** - the user-visible consequence: a required re-authorization, a manual step, a migration, a
behaviour that changes for existing data.

## Writing claims

**Write for a reader who is cold on purpose.** The audit agent is deliberately kept uninformed - no
session context, no memory of how the change came to be - because confirmation from context is
worthless. Every line in every section must stand on the tree and the diff alone; a sentence that
only lands if you watched the change happen is unreadable to the only reader that matters.

**One assertion per claim.** "X and Y" is two claims. A compound claim can be half-true, and a report
that has to answer it with one verdict will round it to whichever half it looked at first.

**Name the symbol, not the feeling.** "the gate handles it now" cannot be audited.
"`OverlayControl::setValue()` returns 403 when `source_managed`" can. Every claim should point
somewhere: a class, a constant, a method, a route, a test name, a file.

**A claim states what should be TRUE, so the agent can find it false.** The claims list is not a
summary of the diff written in the past tense. If nothing could disprove a line, it is not a claim -
it is prose, and prose has its own file.

**Nothing about intent, difficulty, or whether it was a good idea.** No "this had been broken for
months", no "the tricky part was", no "much cleaner now". All of that is real and all of it belongs
in `changelog-YYYY-MM.md`.

**Reference other IDs inline** when a change corrects or builds on an earlier one: "corrects
OL-2609-004 C3". No separate field; the audit agent reads backward on its own.

### The three tags

They are a contract about **how a claim can be checked**, and the audit agent's verdict depends on
getting the right one.

| Tag | Means | Checked by |
|-----|-------|------------|
| `[code]` | True of the tree as it stands | Reading the code |
| `[test]` | A named test asserts it | Running that test |
| `[unverified]` | Needs something outside the repo | Nothing; reported as UNVERIFIABLE |

`[unverified]` covers prod observations, third-party API behaviour, and fail-first runs against a
tree that no longer exists. It is not an escape hatch for a claim you could not be bothered to
locate: **an untagged or mistagged claim that turns out to be uncheckable is itself a finding**, so
the honest tag always costs less than the flattering one.

## What /ship checks

Three mechanical checks, no judgment, red gate = no push:

1. A new folder exists under `docs/changelog/claims/YYYY/MM/` containing `claim.md`.
2. That folder's ID is in the commit trailer as `Changelog: <ID>`.
3. Surface covers every path in the diff.

`/ship` never assesses whether a claim is **true**. That is the audit agent's job, and it happens
after the push.

## What the audit agent does

It does not exist yet. When it does, it reads one `claim.md`, resolves the commit, and writes
`audit.md` beside it with a verdict per claim - CONFIRMED, CONTRADICTED or UNVERIFIABLE - plus
findings for anything in the diff that Surface omitted, tests named but absent or failing, scope
beyond what the entry describes, and anything that contradicts a decision recorded in `CLAUDE.md` or
in an earlier claim.

Write claims as though that has already happened, because the whole value of the format is that a
false line is findable.
