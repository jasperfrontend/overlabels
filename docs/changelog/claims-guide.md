# Claims Guide

A change that touches logic gets a claim file: a short, hard, checkable account of what it did.
Which changes qualify is a path rule, below. From 2026-09-01 to 2026-09-17 every change got one,
icon swaps included; 91 folders in 17 days showed that most of them recorded nothing a diff did not
already say, so the bar moved.

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
in the same directory, and the remedy of that audit as `remedy.md` beside it. The claim, its scrutiny
and what was done about it stay together: an unaudited change is a folder with one file in it, an
audit with open findings is a folder with two.

**No index and no manifest.** `ls docs/changelog/claims/2026/09/` is the index. A second source of
truth would drift, which is the thing this whole arrangement exists to prevent.

**Do not consolidate these back into one file per month.** The per-month prose changelog is a 400 KB
file that new entries get inserted into at the top, and entries have landed out of order in it more
than once. A folder per change has no order to get wrong, no merge conflict at the top of the file,
and lets an agent read exactly one change without loading the month.

## When a claim is required

The rule is path-based so `/ship` can apply it without judgment. Run `git diff --cached
--name-only`; a claim is required if ANY path matches:

- `app/**`, `database/**`, `routes/**`, `config/**`, `bootstrap/**`
- `resources/recipes/**` (products), `resources/js/overlay/**`, any `OverlayRenderer.vue`
- `resources/js/**/*.ts`, `*.mts`, `*.mjs`, `*.js` - except `*.test.ts`
- `.github/**`, `docker/**`, `Dockerfile`, `vite.config.mts`, `package.json`, `composer.json`

A diff made only of `.vue`, `.css`, `.blade.php`, `resources/help/**`, `docs/**`, `public/**`,
`tests/**` or `.md` files needs no claim. A `.vue` file can hold real logic, and that is a known
edge accepted for the sake of a rule with no judgment in it; when it matters, write one anyway.

Two overrides: a change that earns a prose changelog entry always gets a claim, and Jasper can ask
for one on anything. Nothing forbids a claim on an exempt change.

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
say. A small change is four lines and should be (this one would be exempt today; it shows the shape):

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
point. Two implicit exceptions, never listed: the claim file itself, and the prose changelog file. A
`remedy.md` written into an EARLIER ID's folder is a path like any other and IS listed.

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

Four mechanical checks, no judgment, red gate = no push:

1. Whether the staged paths match the rule above. If not, steps 2 to 4 are skipped.
2. A new folder exists under `docs/changelog/claims/YYYY/MM/` containing `claim.md`.
3. That folder's ID is in the commit trailer as `Changelog: <ID>`.
4. Surface covers every path in the diff.

`/ship` never assesses whether a claim is **true**. That is the audit agent's job, and it happens
after the push.

## What the audit agent does

**Scrutinize Sally is advisory. She never gates a deploy** (decided 2026-09-17). A gate would block
prod on an LLM verdict, an API outage or a rate limit, to guard against a failure the diff review
already catches; and the claims she can actually check, `[code]` and `[test]`, are the mechanical
half. She inspects the building and files a report. The sale goes through regardless.

When she runs, she reads one `claim.md` cold - no session, no memory - resolves the commit from the
trailer, and writes `audit.md` beside the claim with a verdict per claim (CONFIRMED, CONTRADICTED,
UNVERIFIABLE) plus findings for anything in the diff that Surface omitted, tests named but absent
or failing, scope beyond what the entry describes, and anything contradicting a decision recorded in
`CLAUDE.md` or an earlier claim. Folders with a `claim.md` and no `audit.md` are her queue; there is
no other state.

### Bringing her to life, in order

1. **A local `/sally <ID>` skill first** (built 2026-09-17). Sally herself is the `sally` agent in
   `.claude/agents/sally.md`, in this repo; the skill is the bookkeeping around a run. It spawns her
   fresh with only the claim and the checkout, and commits `audit.md`. Never run it in the session that wrote the claim. Run it on
   four or five real September claims that carried weight (a product, an integration migration, a
   cross-repo rename) and read the audits: the prompt is calibrated against real entries, not the
   worked example above.
2. **Fix the prompt until a finding is worth reading.** The interesting output is Surface omissions,
   named tests that do not exist, and `[unverified]` used as an escape hatch. If five audits produce
   nothing but CONFIRMED, tighten what she is asked to look for before spending on automation.
3. **Move her to GitHub as her own workflow**, `scrutinize.yml`, on push to `main`, separate from
   `deploy.yml` and never a `needs:` of it. It runs the official Claude Code action on every commit
   in the push range that carries a `Changelog:` trailer, commits `audit.md` with `[skip ci]` (the
   `lint.yml` auto-commit loop is the lesson), and opens an issue titled with the ID only when a
   claim is CONTRADICTED or Surface missed a path. Quiet otherwise.
4. **After a month, read the issues.** If one of them would have stopped a real regression, that is
   the evidence to discuss a gate. Until then she stays advisory.

Write claims as though she already exists, because the whole value of the format is that a false
line is findable.

## What the remedy agent does

**Remy** (`.claude/agents/remy.md`, run through `/remy <ID>`) reads Sally's `audit.md` cold and
resolves each finding to one of three outcomes, recorded in `remedy.md` beside the audit:

- **FIXED** - code or tests changed, always with a test that failed before the fix and passed after.
- **RECORD** - the code was right and the record was wrong; the truth is restated in the new claim.
- **SKIPPED** - could not or must not be resolved, with the reason in one sentence.

**Shipped `claim.md` and `audit.md` files are never edited.** Every correction is a NEW claim whose
lines cite the old one inline (`corrects OL-2609-060 C7, audit F1`), so the record only grows. The
spawning session commits his work locally; it reaches prod only through `/ship`, and his new claim
is unaudited until `/sally <NEW-ID>` runs in a fresh session. Sally, Remy, Sally: that is the loop.
