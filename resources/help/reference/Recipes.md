#Recipes #Bundles #Primer

## Recipes — primer

A primer for the concept, not a spec. Read this first, then we talk more.

## What a Recipe is

A **Recipe** is a pre-wired set of Overlabels primitives, bundled under one name, that a streamer can install in a single click and have it "just work". The recipe author wires the parts together once. Every installer gets their own owned, editable copy of those parts, configured to sane defaults, hidden behind a friendly UI.

A Recipe has:

- A **name**, **description**, **icon/preview**.
- A **manifest** describing which primitives it contains and how they're wired.
- **Default values** for every primitive (so a fresh install boots up working).
- An **install button** that materialises owned instances into the streamer's account.

## The cookbook metaphor

A cookbook recipe lists ingredients and steps. When you cook it, you use *your* ingredients and produce *your* dish. The cookbook itself never changes. Different people cook the same recipe in their own kitchens and end up with their own dishes. Anyone can adapt the recipe and publish their own version.

Same shape here:

| Cookbook       | Overlabels                                                              |
|----------------|-------------------------------------------------------------------------|
| Recipe (book)  | Recipe manifest (read-only schema, shareable, forkable)                 |
| Ingredients    | Primitives the recipe wires together (OptionSets, Pickers, ...)         |
| Steps          | Triggers + control writes (the wiring)                                  |
| Cooked dish    | Your installed instance (your option lists, your settings)              |
| Sharing        | Fork/Copy slug + permanent link (same rail kits and overlays use today) |

## Why Recipes exist (the Johnny vs Pamela story)

Two streamer archetypes:

- **Johnny** wants a Wheel of Fortune on stream by tonight. He never wants to see the underlying Picker, OptionSet, alert template, BotOutbox row, or theme JSON. He wants to type slot labels, click a colour, and go live.
- **Pamela** is a power user. She wants to wire data + logic + output herself. She'll happily hand-write `[[[c:picker:result]]]` and configure five custom alerts. She *also* wants to package what she built and ship it to her audience or sell it.

Recipes serve both:

- Johnny installs Pamela's recipe and never sees the wiring.
- Pamela authors the recipe once, packages it, shares the link.
- Overlabels itself ships the canonical first-party recipes (Wheel of Fortune, Coin Flip, Random Viewer, Lucky Draw, ...).

The DSL underneath stays power-user territory. Most streamers never touch it.

## The load-bearing distinction: schema vs instance

This is the single most important rule and it has to be true from day one:

> A Recipe is a **schema**. An install creates **owned instances**.

When Johnny installs a Coin Flip recipe:

- One row written to `option_sets`, owned by Johnny.
- One row written to `pickers`, owned by Johnny.
- A set of `c:<recipe>:<instance>:*` controls registered, owned by Johnny.

Johnny edits *his* option list and *his* trigger settings. The recipe itself is read-only and shared across all installers. If Pamela publishes a new version, it appears as a *separate installable artifact* and never overwrites Johnny's existing install.

Why this rule matters:

- **Recipes can be deleted, archived, taken down for sale, etc., without breaking anyone's overlay.** Instances are owned by the streamer, not leased from the author.
- **Customisation never collides with sharing.** Johnny renaming a slot doesn't change Pamela's recipe.
- **Power users can crack open an installed recipe.** Once instances exist, they're just normal Overlabels objects. Pamela installs her own recipe, then unbundles and rewires it freely. The recipe was the seed; the garden is hers.

## How Recipes ride existing rails

Overlabels already has fork/copy semantics for **kits** and **overlays**. Recipes get the same:

- Slug-based permanent links.
- "Copy" button (never "Fork" in the UI per the Overlabels naming rule).
- Copy count, author attribution, version field.
- Public vs unlisted vs private states.
- Eventual marketplace, eventual price-tag-attached-to-slug.

If we get the Recipe shape right, the share/copy/discover machinery is largely already built.

## What a Recipe contains

A Recipe is a **server-side data producer**. It runs before any overlay loads and writes values to the controls layer. That's the entire job.

Working list of what fits inside a recipe manifest:

- **OptionSets** — named, reusable lists of values (slot labels, dice faces, viewer pools, ...).
- **Pickers** — RNG / selection engines over an OptionSet.
- **Triggers** — entry points that fire pickers (chat command, dashboard button, EventSub event, scheduled tick).
- **Control exports** — the `c:<recipe>:<instance>:<name>` values the recipe writes when its primitives change.

That's it. A picker emits a typed event when it lands, which alerts and Bot Expressions can subscribe to from their own UIs.

What is **not** in a recipe:

- **No renderers / Vue components.** The Wheel, Coin, and Dice visuals are normal Overlabels overlay components in the existing component library. Recipes don't ship frontend.
- **No alert templates.** Alerts are an overlay-side concern; they consume control values and recipe events, but live in the existing alert system.
- **No bot messages.** The bot is a separate consumer (see Bot Expressions). It subscribes to recipe events the same way alerts do.
- **No HTML, no theme JSON, no executable code.** Recipes are pure data + logic, not visuals.

A first-party Coin Flip recipe wires: 1 OptionSet + 1 Picker + 2 Triggers (chat command, dashboard button) + 3 control exports. About 45 lines of JSON.

## Build order in time

Recipes are not a v1 thing. They sit on top of primitives. The layering, in order:

1. **Build the primitives.** Picker, OptionSet. Power-user DSL access only. Ugly, fine.
2. **Define the Recipe manifest shape.** JSON schema for "which primitives, how wired, what controls are exported". Version field from day one. Hand-authored, no UI.
3. **Build the install flow.** A `RecipeInstaller` service that reads a manifest and writes owned instance rows. Reuse fork/copy slug logic from kits.
4. **Ship Coin Flip as the first recipe.** Hand-authored manifest, installable from the dashboard. The smallest meaningful recipe and the entire validation surface for the abstraction.
5. **Add a second recipe.** Random Viewer or Dice. The test. If the second recipe needs the manifest shape to change, the abstraction was wrong.
6. **Build the Wheel of Fortune Kit.** Bundles a Wheel-flavoured recipe (same picker primitive) + the existing Wheel overlay component + suggested alert + suggested Bot Expression. The visual personality lives at the Kit layer.
7. **Someday: visual builder.** A node-editor control panel where Pamela drags primitives onto a canvas, wires them, saves as a recipe. Significant scope. Not soon.
8. **Eventually: marketplace.** Discoverability, ratings, paid recipes. Dream layer.

You cannot skip step 5. Without a second recipe to validate the manifest shape, the abstraction is just "Coin Flip with extra steps."

## The risk to flag early

**Once recipes are shared, the manifest shape becomes a contract you cannot break.**

If a streamer installed a recipe yesterday and Overlabels changes the manifest schema today, that streamer's overlay either silently breaks or shows an angry red "this recipe is outdated" banner. Both are bad.

This means:

- **Versioning has to exist on day one.** Manifest carries a `recipe_format_version`. The installer rejects formats it doesn't understand and tells the user why.
- **Migrations need a story.** When the format changes, what happens to existing installs? Auto-upgrade with explicit user consent? Pin to old format forever? Force re-install?
- **Recipe authors need a "this manifest works" check.** A validation step before publishing, so broken recipes don't reach the share link stage.
- **Primitives can refactor freely. Recipes cannot.** Internal primitive APIs are private; the manifest is public. Treat them differently.

This is the bit that deserves more design thought up front than the primitives themselves do.

## Where the Wheel of Fortune sits in this

The Wheel doc describes a specific *Kit*, not a recipe. With the producer-vs-consumer split in place:

- The **Wheel Vue component** is a normal Overlabels overlay component, shipping in the platform's component library.
- A small **Picker recipe** ("Wheel Spin") provides the RNG, options, and chat-command trigger. It writes `c:wheel-spin:default:result` and emits a `landed` event.
- The **Wheel of Fortune Kit** bundles the recipe, an overlay template that uses the Wheel component reading those controls, and suggested defaults for an alert and a Bot Expression.
- Pamela's "Premium Wheel of Fitness" is a third-party Kit. It can ship its own recipe-version + overlay template + suggested defaults, but it cannot ship its own Vue component. The Wheel component is the platform's.

That framing also makes the question "is this an InternalIntegration?" land cleanly: the *primitive layer* (Picker, OptionSet) is the InternalIntegration; the *Recipe* wires it; the *Kit* packages the recipe with its UI.

## Open questions and answers

These were the bits that were not sharp at first draft. Answers were resolved in the 2026-05-09 design conversation and are recorded inline below each question.

1. **Naming of recipe primitives.** Picker, OptionSet, Renderer — are these the right words? Are there clearer names that read well to Johnny when (if?) they leak into UI?
2. **What does "wiring" look like in the manifest?** Free-form JSON references, or a strict graph schema? Free-form is easier to author by hand, harder to validate. Strict is the inverse.
3. **Recipe scope creep.** What's the line between a recipe and a full overlay kit? Could a recipe ship an overlay template too? Or do we keep the boundary "recipes are control logic, kits are visual layouts"?
	- A recipe exists before an overlay (kit) is rendered. The recipe shapes the overlay in a sense that it brings in more custom `[[[recipe-related]]]` tags to the actual overlay that may end up in a Kit. The Kit is the shippable result of hard work in getting all the events, all the alerts, all the static data and all the recipes data in a package - that later can be sold.
	- A recipe can ship custom controls that render whatever you gave into that recipe. A single number? A whole wheel? just some maths without any output? I don't care and neither should the backend. A recipe can ship logic and outcome, which may render in a control. this needs a few changes in Controls or we should accept a custom Recipe Control, because currently Controls do NOT accept HTML whatsoever. Not sure about this one yet.
4. **Multi-instance.** Can Johnny install the Wheel recipe twice with different option sets? If yes, the install flow needs to handle naming collisions cleanly.
	- I see how only allowing 1 instance of a recipe is an issue for Johnny because he has no clue how to create a new OptionSet etc. so yeah, each instance should have its own specific name, even if Johnny calls both of his Recipes "LOLWHEEL" 😣
5. **Update semantics.** When Pamela updates a recipe, what does "update available" actually do? Re-run the installer? Diff the manifest? Show changelog?
	- I'm actually not a fan of a "WordPress-like" update available route. Since we already agreed that a Recipe and its version is its own thing and we can't really offer an easy upgrade path because Johnny may have made changes to his final template, a new version of a Recipe that contain breaking changes should create a copy of the existing Recipe owned by Johnny so that he can check (or ask the dev) what broke and how to fix it, without breaking his existing implementation. Then when he finally found the solution, he can just create a new static overlay to render it in, how Overlabels works. 
6. **Permissions on recipes that touch the chat bot.** A recipe shipping a BotOutbox template runs in the streamer's name. What does the install consent flow look like? Does Johnny need to acknowledge "this recipe will speak in your chat"?
	- A recipe shipping a BotOutbox template runs over @overlabels, not the user's account. By adding a "this ingredient will make whatever you put in it speak in your chat through @overlabels - be sure to either VIP or MOD this account" seems good enough.
7. **Dependency on external integrations.** A "Ko-fi Wheel" recipe assumes Ko-fi is connected. Does the recipe declare its dependencies, and does the install flow refuse / prompt-to-connect if missing?
	- great question. when a user currently tries to fork an overlay that has source-managed controls in the overlay NOT connected by the user who forks the overlay, the system warns you for that, but it still allows the user to copy the overlay anyhow. If they later connect the service that is shipped in the overlay, it all just works fine. This same permissive logic should exist for Recipes I feel - but they should also warn the user properly that this Recipe won't work without connecting all the services needed to make the Recipe work.
8. **Pricing surface.** Ignore for now, but: at what layer does a price tag attach? Per-recipe? Per-author? Subscription? This affects how recipes are stored more than you'd think.
	- Per account, a user can have multiple instance of the same Recipe and it will only charge the user once. They buy the Recipe, not the instances. Of course a new instance requires more compute etc, so we will have to limit instances of the same recipe to like... 10. That needs to be a configurable option in the admin panel. 
9. **First-party vs third-party trust.** Overlabels-shipped recipes are vetted. User-shared ones are not. Is there a curation / verification surface, or is everything open and caveat-emptor?
	- All shipped Recipes should at least pass basic tests, but I feel especially since this is all new that any new Recipe requires manual verification. Literal matter on hand: feed the code into a Claude Code instance and have it run it against expected tests and see if the Recipe is safe.
10. **What's the smallest shippable recipe?** Probably "Coin Flip". One OptionSet, one picker, one tiny renderer, one chat message. If the recipe abstraction can't ship that cleanly, it's wrong.
	- Coin flip is definitely the best first use-case because it's either heads or tails. Then if that works, feed the system a list of 2000 items (just for test) to see how it holds up. sane limit per list is probably 100. this should also be a configurable option in the admin panel.

## What crystallised from the answers

The answers above lock in five hard constraints that most of the manifest design now follows from:

1. **Manifests are 100% declarative, no executable code.** Required for Claude-assisted verification; arbitrary JS is intractable to audit.
2. **Renderers are platform-shipped, not recipe-shipped.** Recipes can configure existing renderers (Wheel, Coin, Dice, ...) but cannot ship new Vue components. Theme / config JSON only.
3. **Strict graph schema for wiring.** Free-form JSON makes verification too loose; a typed node graph keeps the validator honest.
4. **A new version is a new installable artifact, not a destructive update.** No WordPress-style "update available" overwrite. Versions coexist; Johnny opts in by installing the new one alongside.
5. **Multi-instance with explicit instance slugs.** Bare tags resolve to "most recently used" instance; qualified tags `c:<recipe>:<instance>:<name>` always win.

These together push complexity that Pamela might want (custom Vue, custom logic) out of v1 recipes and into a far-future "Components SDK" layer that may never ship. That's a feature, not a limitation.

## The architecture: controls as the bus

Recipes don't ship frontends or chat templates because they don't need to. They sit on the **producer** side of the controls layer; everything visible to the streamer sits on the **consumer** side.

```
[Recipes]       ──writes──▶                ──reads──▶  [Bot Expressions]
[Twitch Helix]  ──writes──▶  [Controls]    ──reads──▶  [Alert Templates]
[Integrations]  ──writes──▶                ──reads──▶  [Overlay Templates]
```

Producers write values into the controls layer. Consumers read from it. Every Overlabels feature lives on one side or the other. A new producer (a new recipe, a new integration) becomes useful to all consumers automatically. A new consumer (Bot Expressions, a future Stream Deck plugin, ...) gets all existing data sources for free.

What this means in practice for recipes:

- A Coin Flip recipe writes `c:coin-flip:default:result` when it lands. Done.
- A user who wants the bot to announce the result writes a Bot Expression `!flip` whose template is `The coin shows: [[[c:coin-flip:default:result]]]`. The recipe doesn't ship that expression.
- A user who wants an on-screen alert writes an Alert Template that triggers on the recipe's `landed` event. The recipe doesn't ship that template either.
- A Kit can pre-fill any of the above as defaults at install time, but they end up as normal Overlabels objects, edited in their normal UIs.

The Bot Expression layer is documented separately in `Bot-Expressions.md` (the consumer-side spec).

## The manifest shape

Strawman for the smallest shippable recipe (Coin Flip), to give the format something concrete to argue with.

```json
{
  "recipe_format_version": 1,

  "slug": "coin-flip",
  "name": "Coin Flip",
  "version": 1,
  "description": "Heads or tails. Triggered from chat or dashboard.",
  "author": { "name": "Overlabels", "twitch_login": "overlabels" },
  "icon_url": "https://res.cloudinary.com/.../coin-flip.png",
  "changelog": "Initial release.",

  "min_overlabels_version": 1,
  "requires_integrations": [],
  "max_instances_per_user": 5,

  "primitives": {
    "option_sets": [
      {
        "ref": "coin",
        "label": "Coin sides",
        "items": ["Heads", "Tails"],
        "user_editable": true,
        "min_items": 2,
        "max_items": 2
      }
    ],
    "pickers": [
      {
        "ref": "flipper",
        "label": "The flipper",
        "option_set_ref": "coin",
        "consume_on_pick": false,
        "concurrency": "reject_if_running",
        "user_editable": false
      }
    ]
  },

  "control_exports": [
    { "name": "result",    "from": "pickers.flipper.result" },
    { "name": "result_at", "from": "pickers.flipper.result_at" },
    { "name": "running",   "from": "pickers.flipper.running" }
  ],

  "triggers": [
    {
      "kind": "chat_command",
      "command": "!flip",
      "permissions": "everyone",
      "cooldown_seconds": 10,
      "fires": "pickers.flipper"
    },
    {
      "kind": "dashboard_button",
      "label": "Flip the coin",
      "fires": "pickers.flipper"
    }
  ]
}
```

About 45 lines of pure data. No executable code, every value is a primitive, an enum, a reference, a number, a string, or an array of those.

Each picker implicitly emits one event when it lands, named `<recipe_slug>.<picker_ref>.landed`, carrying `{result, result_at, instance_id, instance_slug, recipe_slug}`. Alerts and Bot Expressions subscribe to that event. No `events` section is needed in the manifest because there is nothing to declare beyond the picker itself.

### What was deliberately cut

An earlier draft of this manifest included `renderers`, `alert_templates`, `bot_messages`, and a `bot_consent_required` flag. They were removed when the architecture clarified to "recipes write controls + emit events; everything visual or chat-bound is a separate consumer" (see "The architecture: controls as the bus" above). Reasons, briefly:

- **Renderers** belong in the platform's overlay component library, not in a recipe. A Wheel component is reused across many recipes.
- **Alert templates** belong in the existing alerts system, which can subscribe to recipe events the same way it subscribes to Twitch events.
- **Bot messages** belong in Bot Expressions, which read from the same controls layer.
- **`bot_consent_required`** is moot because recipes don't speak to chat. If a Kit ships a Bot Expression default that does, consent lives at the Bot Expression install step, not at the recipe install step.

This is recorded so a future reader doesn't reintroduce these fields without remembering why they were removed.

### Choices baked into the strawman

**Sectioned object, not flat node list.** `primitives` is keyed by primitive type (`option_sets`, `pickers`). A flat array with `type` discriminators was considered but the sectioned shape is dramatically easier to read and almost no recipe will be big enough to benefit from the flat shape. References between sections are explicit (`option_set_ref: "coin"` points at `primitives.option_sets[ref=coin]`).

**`ref` is local to the manifest, not a slug.** It's an internal identifier that nodes use to point at each other. The install flow translates `refs` into real database row IDs and per-instance slugs. The author picks short readable names; no slug rules, no global uniqueness.

**`version` is an integer, not semver.** Per the "new version = new installable" decision, semver implies in-place upgrades and we explicitly rejected that. v1, v2, v3, done. No patch / minor / major distinction needed because every published version is a separate installable artifact.

**`control_exports` declares names, not full tags.** The manifest says `result`, the install flow emits `c:<recipe_slug>:<instance_slug>:result`. That keeps multi-instance routing out of the manifest. Bare-tag resolution to "most recently used" is install-flow logic, not manifest logic.

**`user_editable` is per-primitive, with optional `user_editable_fields` whitelist for partial editing.** Option items are Johnny's; picker logic stays locked. This protects the recipe author from "Johnny set cooldown to 0s and now blames me".

**`triggers` is an array of typed objects.** Allows multiple chat commands or multiple dashboard buttons in the same recipe later without restructuring.

### Manifest design choices still to resolve

A short list now that the renderer and HTML branches collapsed.

1. **Trigger `permissions` enum.** The strawman has `"everyone"`. Likely needs `"streamer_only"`, `"mods"`, `"vips"`, `"subs"`, `"everyone"`. Reuse an existing Overlabels enum if one exists, else define here. (Should align with the Bot Expression permissions enum so users see one consistent vocabulary.)
2. **Recipe-to-recipe wiring.** Out of scope for v1. Confirm and say so explicitly in the spec.
3. **`min_overlabels_version` semantics.** Integer that increments on any breaking change to the recipe format. Probably no need for two dials now that renderers are out.
4. **Deliberately omitted from v1 manifest.** Multi-step pickers, conditional logic between primitives, scheduled triggers. Coin Flip doesn't need any of those; decide whether they appear in v2 or stay outside recipes entirely.
5. **Tag syntax: colons or dots?** Settled on colons (`[[[c:recipe:instance:name]]]`) per current production code, but issue #104 still uses dots. The Bot Expressions spec has to agree with whichever the recipe install flow emits. Decide once, document loudly.

## What this is not

- **Not Flows.** Flows is the bigger reactive engine vision in your memory. Recipes are smaller, contained, and shippable years before Flows.
- **Not a visual programming environment.** A visual builder is a step 6 luxury. Recipes are useful as hand-authored JSON manifests long before that exists.
- **Not the same as Kits.** Kits ship visual templates; recipes ship behaviour. They overlap conceptually and may merge later, but treat them separately for now.
- **Not a v1 feature.** Primitives ship first. Recipes are the layer that makes primitives accessible to non-power-users, not a substitute for building them.

## Next conversation

The shape is sharp, the strawman is small, and the producer-vs-consumer split makes the rest of the architecture organise itself.

Remaining work before code:

- Resolve the five manifest design choices above, especially the tag-syntax pin-down (colons vs dots) since it's shared with Bot Expressions.
- Decide whether scheduled triggers ship in v1 (e.g. "fire this picker every 30 minutes") or wait for v2.
- Build order in time has Bot Expressions ahead of Recipes (see step ordering above) because Bot Expressions are useful with no recipe machinery and exercise the consumer-side parser/permission/cooldown infrastructure that recipes will lean on.
- Once the tag-syntax and permission-enum choices are nailed down, this doc graduates from primer to spec. At that point split: keep Recipes.md as the conceptual primer, add `Recipes-Manifest-Spec.md` as the formal contract.
