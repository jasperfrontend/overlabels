## OL-2609-049 - feat(products): the flow frame names itself, the banner says what to do, and the next step's control lights up on its page

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-049`

### Surface
- `app/Support/ProductSetup.php` - `STEPS` constant mapping each product wire to a `todo` instruction and a `target` key; `banner()` adds `next.todo` and `next.target`
- `resources/js/composables/useUiMode.ts` - `UiModeSetup.next`, `ResolvedUiMode.target`, `useUiMode()` exposes `target`, new `useProductTarget()`
- `resources/js/composables/useUiMode.test.ts` - existing resolve tests carry `target`
- `resources/js/components/ProductSetupBanner.vue` - "Next:" reads `next.todo`
- `resources/js/components/ProductFlowFrame.vue` - a bottom-left label in the frame's colour
- `resources/css/app.css` - `.product-target` outline and glow, still under reduced motion
- `resources/js/pages/settings/integrations/index.vue` - the Chat bot card binds `product-target` on the `bot-toggle` key
- `resources/js/pages/overlaytokens/index.vue` - the Create token button binds `product-target` on the `token-create` key
- `tests/Feature/ProductSetupFlowTest.php` - the dashboard assertion checks `next.todo` and `next.target`

### Claims
- **C1** [code] `ProductSetup::STEPS` has an entry for each of the eight `product.*` wires; `banner()` sets `next.todo` from it, falling back to the lower-cased label for an unknown key, and `next.target` from it, falling back to null.
- **C2** [code] The three bot wires (`bot_on`, `bot_hears`, `bot_modded`) target `bot-toggle`; `product.token` targets `token-create`; the integration, overlay, list and command wires have no target.
- **C3** [code] `resolveUiMode()` returns `target` = `setup.next.target` while a flow is active and null otherwise; `useProductTarget(key)` is true only when the mode is `product` and the target equals `key`.
- **C4** [code] `.product-target` in `app.css` is a 2px fuchsia outline with a 3px offset and a pulsing glow, and the pulse is off under `prefers-reduced-motion: reduce`; it is bound on exactly two elements, the Chat bot card on the integrations page and the Create token button on the tokens page.
- **C5** [code] `ProductFlowFrame.vue` renders a bottom-left label reading "Product installation mode enabled" while steps remain and "Product installation complete" when ready, in the frame's colour.
- **C6** [code] `ProductSetupBanner.vue` composes "Next: " with `next.todo`; the wire label is no longer used in the banner.
- **C7** [test] `ProductSetupFlowTest` asserts `next.todo` = "make sure the bot is switched on" and `next.target` = `bot-toggle` for a fresh checkin install with the bot off; `useUiMode.test.ts` asserts C3 for a flow with and without a target and for no flow.
- **C8** [unverified] On `overlabels.test` on 2026-09-09 with a flow active and the bot off, `/settings/integrations` showed the banner reading "Next: make sure the bot is switched on.", the Chat bot card with the fuchsia edge and glow, and the frame label bottom left.

### Unchanged
- `WiringCatalog` and `WiringReport` are not in the diff; the instruction and target live beside the wires in `ProductSetup`, not on them, so the wiring page's copy is untouched.
- The other five product wires have no target on purpose: their destinations (the product page, the lists page, the bot commands page) have no single control that is the step.

### Risk
The frame label sits over whatever the app draws in the bottom-left 10px corner, which today is the sidebar footer's build hash.
