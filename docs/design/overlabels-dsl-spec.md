# The Overlabels DSL - Specification

> **Status:** first written 2026-07-28. This is *descriptive* before it is prescriptive: sections 1-6
> document what the language actually does today, derived by reading every implementation. Sections
> 7-10 propose the unified grammar and the validator built on it.
>
> **Why it exists.** The DSL grew by accretion over a year of development and was never written down.
> Five separate implementations now match tags independently, and they do not agree with each other.
> Nothing here is a redesign - the language is small and mostly coherent. It just needs an owner.

---

## 1. The vocabulary is closed and small

| Part | Count | Notes |
|---|---:|---|
| Static Twitch tags | 62 | `resources/help/reference/template-tags/all-overlabels-static-template-tags.md` |
| Formatters (pipes) | 11 | `round number currency date duration distance speed uppercase lowercase login mention` |
| Block keywords | 6 | `if elseif else endif foreach endforeach` |
| Comparison operators | 6 | `>= <= > < != =` |
| Operators | 2 | `\|` (pipe), `??` (default) |

**79 closed terms.** Everything else is an *open namespace* - not enumerable at parse time, but
resolvable at validate time against the user's own records:

| Namespace | Resolves against |
|---|---|
| `c:<key>` | user's controls (template-scoped and user-scoped) |
| `c:<service>:<key>` | service-managed controls (kofi, streamlabs, streamelements, fourthwall, gps) |
| `c:list:<slug>` | user's Lists |
| `event.*` | the alert payload in scope |
| `bot:*` | bot invocation context (`bot:args.0`, `bot:from_user`, ...) |
| `<alias>.*`, `loop.*` | foreach-scoped, valid only inside the loop body |

This split is the whole design: **closed terms validate by lookup, open namespaces validate by
resolution.** Expression Controls already do the second half today.

---

## 2. Lexical forms

```
[[[key]]]
[[[key|formatter]]]
[[[key|formatter:args]]]
[[[key ?? default]]]
[[[key|formatter:args ?? default]]]

[[[if:<condition>]]] ... [[[elseif:<condition>]]] ... [[[else]]] ... [[[endif]]]
[[[foreach:<iterable> as <alias>]]] ... [[[endforeach]]]
[[[foreach:<iterable>]]] ... [[[endforeach]]]          (alias defaults to `item`)
[[[raw]]]                                              (inside a foreach body only)
```

`<condition>` is either a bare key (boolean test) or `key <op> value`.

---

## 3. The five implementations (the archaeology)

| # | Location | Role | Pattern |
|---|---|---|---|
| 1 | `resources/js/utils/tagParser.ts:22` | Overlay render substitution | `/\[\[\[([\w.:\-]+)(?:\|([\w.:\- ]+))?(?:\s*\?\?\s*(.*?))?]]]/g` |
| 2 | `app/Services/Bot/BotExpressionResolver.php:54` | Bot expression substitution | identical to #1 |
| 3 | `app/Services/Expressions/AlertExpressionRenderer.php:40` | Alert expression substitution | identical to #1 |
| 4 | `app/Models/OverlayTemplate.php:171` | Tag **extraction** for the data allowlist | `/\[\[\[([a-zA-Z0-9_.][a-zA-Z0-9_.:\-]*?)(?:\|[a-zA-Z0-9_.:% -]+)?(?:\s*\?\?\s*.*?)?]]]/` |
| 5 | `app/Models/UserTemplate.php:89` | Legacy model | `/\[\[\[([^]]+)]]]/` |

Plus three auxiliary matchers:

| Location | Role | Pattern |
|---|---|---|
| `useConditionalTemplates.ts:42` | Block tokens | `/\[\[\[(if:([^\]]+)\|elseif:([^\]]+)\|else\|endif\|foreach:([^\]]+)\|endforeach)]]]/g` |
| `OverlayTemplate::extractConditionalTags` | Condition keys for the allowlist | `/\[\[\[(?:if\|elseif):([a-zA-Z0-9_.][a-zA-Z0-9_.:]*?)(?:\s*(?:>=\|<=\|>\|<\|!=\|=)\s*[^]]+)?]]]/` |
| `OverlayTemplate::detectRequiredServices` | Which integrations a template needs | `/\[\[\[c:([a-zA-Z0-9_]+):[a-zA-Z0-9_]+(?:\|[a-zA-Z0-9_.:% -]+)?]]]/` |

**The good news:** the three *substitution* engines (#1, #2, #3) are byte-identical. The language as
rendered is consistent across overlays, bot replies, and alerts.

**The problem:** #4 is the *extraction* engine - it decides which data gets fetched at all - and it
does not match #1-3. When extraction and substitution disagree, the tag silently resolves to empty
(or to its `??` default), because the value was never fetched.

---

## 4. Divergences found

Each of these is a real, reproducible inconsistency in the shipped language.

**D1 - Pipe args containing `%` render literally.**
Extractor #4 allows `%` in pipe args; renderers #1-3 do not. `[[[x|number:0.0%]]]` is allowlisted
server-side, then fails to match at render time and is emitted to the page as the literal string
`[[[x|number:0.0%]]]`.

**D2 - A key may start with `:` at render time but not at extraction time.**
`[\w.:\-]+` (renderers) accepts a leading `:`; `[a-zA-Z0-9_.]` (extractor) does not. `[[[:foo]]]`
substitutes to empty client-side and never enters the allowlist.

**D3 - `detectRequiredServices` was never taught about `??`.**
Its pattern has no `?? default` branch, so `[[[c:kofi:total ?? 0]]]` does **not** register Ko-fi as a
required service. A template using nothing but defaulted control tags reports zero dependencies and
the "connect this service" warning never fires. This is copy-paste drift from when `??` was added to
the other four patterns.

**D4 - `detectRequiredServices` is two-segment only.**
`c:<svc>:<key>` with `[a-zA-Z0-9_]+` per segment. Three-segment control tags never match, and no
segment may contain a dot or hyphen.

**D5 - Conditions reject hyphens that plain tags accept.**
Condition keys are `[a-zA-Z0-9_.:]` in both implementations - no hyphens - while plain tag keys allow
them. `[[[my-tag]]]` extracts fine; `[[[if:my-tag = 1]]]` does not extract `my-tag`.

**D6 - The legacy `UserTemplate` matcher accepts anything.**
`[^]]+` swallows spaces, pipes and operators as part of the tag *name*. Legacy path; flagged so the
spec doesn't get written against it.

**D7 - A condition cannot contain `]`.**
`[^\]]+` in the block-token regex means `[[[if:x = a]b]]]` truncates. Undocumented and unguarded.

---

## 5. Evaluation semantics (as implemented)

### 5.1 Substitution
- **Single pass, always.** The regex runs exactly once per render. Substituted values are never
  re-scanned. This is the day-one rule that prevents template injection through donor names, chat
  messages, and control values that themselves contain `[[[...]]]`. See the adversarial test in the
  `tagParser.ts` header comment. **Do not add a second pass.**
- Missing is defined as `undefined`, `null`, an object, or the empty string after `String()`.
- Values are HTML-encoded (`& < > " '`) on the HTML path. The CSS path skips encoding because
  `style.textContent` is not HTML-parsed.

### 5.2 The `??` default
- Fires on **absence only**. A present-but-unexpected value never triggers it.
- Emitted **verbatim** - the pipe is *not* applied to a default.
- HTML-encoded like any other value, so it cannot break out of a sink.
- Being part of the authored template rather than substituted data, it inherits single-pass safety.

### 5.3 Pipes
- `parsePipe` splits on the **first** colon: `duration:hh:mm:ss` gives name `duration`, args
  `hh:mm:ss`.
- Unknown formatter names fall through and return the raw value unchanged. **Today this fails
  silently - a typo'd formatter is indistinguishable from no formatter.** Prime validator target.

### 5.4 Conditionals
- Operators `>= <= > < != =`; a single `=` is normalised to `==`.
- If **both** sides parse as numbers, comparison is numeric; otherwise string comparison, with
  `> < >= <=` falling back to lexicographic ordering.
- Quotes are stripped from the right-hand side.
- Bare key = boolean test. Falsy set: `false`, `'false'`, `'0'`, empty, `null`, `undefined`.
- `else`/`elseif` bind to the nearest enclosing `if` by depth tracking (`splitTopLevel`).
- `foreach`/`endforeach` are deliberately *not* depth-tracked during if-splitting; they do not affect
  if/else pairing.

### 5.5 foreach
- `[[[foreach:<iterable> as <alias>]]]`; alias defaults to `item`.
- Twitch payloads arrive as flattened dotted keys (`event.choices.0.title`), so `resolveIterable`
  synthesises an array by scanning the key prefix.
- Iteration is capped at the **highest populated index**, not at `<iterable>.count`, so a
  cap-truncated list does not pad with empty objects. `count` remains readable as
  `[[[<iterable>.count]]]`.
- Each iteration binds `loop.index`, `loop.first`, `loop.last`, `loop.count`, both nested and flat.
- Scoped tokens are substituted *inside* the loop and cannot leak to the outer pass.
- `[[[raw]]]` dumps the current item as pretty JSON, with `[` and `]` entity-escaped so data cannot
  re-enter tag substitution.
- **Per-user caps**, max 50, keys `subscribers goals followers followed` (`User::foreachCaps()`).

### 5.6 Failure modes (all silent today)
- Nesting deeper than **10** logs a console warning and returns the template unprocessed.
- An unmatched `if` or `foreach` aborts processing and leaves raw text on the page.
- A stray `else`/`elseif`/`endif`/`endforeach` is skipped silently.
- **Every one of these is invisible to the author.** This is the single strongest argument for the
  validator: the language already detects malformation, then throws the information away.

### 5.7 CSS fast path
`compileCssBindings` rewrites tags to `var(--ol-*)` custom properties, and **bails to the slow path**
on any of: `[[[if:`, `[[[elseif:`, `[[[foreach:`, a `??` default, or a non-boundary character glued
to the left of a placeholder. Worth surfacing in the editor - authors have no idea a `??` in CSS
silently costs them the fast path.

### 5.8 Structural safety
`HtmlSanitizationService` strips `<script>` and its content, `javascript:` URIs (plain and
entity-encoded), `<form> <button> <input> <textarea> <select> <object> <iframe> <embed>`,
`<meta http-equiv="refresh">` with `javascript:`/`data:` URIs, and `javascript:` inside CSS `url()`.
All by regex, at the character level. It works, but it cannot see structure - it folds in as one pass
of a real parse and gets strictly better for it.

---

## 6. Grammar

```ebnf
template     = { text | tag | if_block | foreach_block } ;

tag          = "[[[" key [ "|" pipe ] [ "??" default ] "]]]" ;
key          = ident { ( "." | ":" ) ident } ;
ident        = alpha_num_us { alpha_num_us | "-" } ;
pipe         = formatter [ ":" args ] ;
formatter    = "round" | "number" | "currency" | "date" | "duration"
             | "distance" | "speed" | "uppercase" | "lowercase"
             | "login" | "mention" ;
default      = { any_char - "]]]" } ;

if_block     = "[[[if:" condition "]]]" template
               { "[[[elseif:" condition "]]]" template }
               [ "[[[else]]]" template ]
               "[[[endif]]]" ;
condition    = key [ operator value ] ;
operator     = ">=" | "<=" | ">" | "<" | "!=" | "=" ;
value        = quoted | bare ;

foreach_block= "[[[foreach:" key [ "as" alias ] "]]]" template "[[[endforeach]]]" ;
alias        = ident ;
```

Two notes where the grammar deliberately differs from today's regexes:
- `key` requires a leading `ident`, closing **D2**, and permits hyphens in every position including
  conditions, closing **D5**.
- `args` should be a permissive run excluding `|` and `]]]`, closing **D1** (and admitting `%`).

---

## 7. The validator

### 7.1 One grammar, three consumers

```
                 ┌─ editor: inline diagnostics + autocomplete (CodeMirror)
grammar + ──────►├─ backend: save gate on head / html / css
vocabulary       └─ sanitizer: structural pass, replacing regex stripping
```

### 7.2 Source of truth (the one real decision)

If the grammar is written twice it diverges inside a month - and a validator that disagrees with
itself is **worse than none**, because the editor greenlights what the save rejects. Sections 3-4 are
the empirical proof: five hand-maintained regexes, seven divergences, zero of them intentional.

Two workable shapes:

- **A - PHP owns it, emits JSON.** Grammar + vocabulary live in PHP; a build step (or a cached
  endpoint) emits a JSON descriptor the frontend consumes. Backend is authoritative, frontend cannot
  drift. Cost: a build step, and the parser itself still exists twice (one per runtime).
- **B - Shared declarative spec.** A single `dsl.json` (keywords, formatters + arity, operators,
  namespace resolvers) that both a PHP and a TS parser read. Both parsers are thin and table-driven;
  the *vocabulary* cannot drift even though the two parsers are separate code.

Recommendation: **B**. The vocabulary is what actually drifts (see: 11 formatters shipped, 8
documented). Grammar changes are rare and reviewable; vocabulary changes happen casually.

### 7.3 Error taxonomy

Errors (block save):
- Unknown formatter name.
- Unmatched `if` / `foreach`; stray `else` / `elseif` / `endif` / `endforeach`.
- Nesting deeper than 10.
- Malformed condition (missing operand, unknown operator).
- Unknown closed-vocabulary tag (a static tag not in the 62).

Warnings (allow save, surface in editor):
- Unresolvable open-namespace reference (`c:` key with no matching control, `c:list:` with no such
  list) - a warning, not an error, because the control may be created later.
- Reference to a service the user has not connected - mirrors the existing permissive overlay-copy
  behaviour.
- Scoped token (`alias.*`, `loop.*`, `raw`) used outside a `foreach` body.
- CSS constructs that force the slow path (5.7).
- `??` on a tag that can never be absent.

### 7.4 Sequencing

1. Write the shared vocabulary spec + emit it. Fixes the doc drift immediately, zero behaviour change.
2. Tokenizer + parser producing an AST, one per runtime, table-driven from step 1.
3. Backend save gate on `head`/`html`/`css`. Errors only; warnings advisory.
4. Editor diagnostics from the same AST.
5. Autocomplete - the AST already knows the cursor's scope, so `alias.*` completion inside a foreach
   body falls out for free.
6. Retire the five ad-hoc regexes onto the parser, one call site at a time.

Steps 1-3 are independently useful. Step 5 is the item folded in from the Bot Expression backlog.

---

## 8. Open decisions

1. **Source of truth: A or B** (7.2). Everything else is downstream of this.
2. **Do the divergences get fixed, or frozen?** D1-D5 are all currently-silent behaviours. Fixing D3
   changes which templates report a missing integration; fixing D1/D2/D5 makes previously-inert tags
   start resolving. Small blast radius, but non-zero - it is a real call.
3. **Is an unknown static tag an error or a warning?** Erroring is more useful and risks breaking
   saves for templates that already contain typos.
4. **Does the validator run on existing rows?** A "your saved templates have N issues" report is
   valuable and also potentially alarming.
5. **Where do the `?? default` semantics get documented for users?** The absence-only rule and the
   pipe-not-applied rule are both surprising and currently only live in code comments.
