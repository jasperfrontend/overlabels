## OL-2609-045 - feat(products): a URL-carried product mode turns the overlay page's Add to OBS tab green, with a last-step callout

**Shipped:** 2026-09-09
**Commit:** `git log --grep=OL-2609-045`

### Surface
- `resources/js/composables/useUiMode.ts` - new: `parseUiMode()`, `applyUiMode()`, `useUiMode()`
- `resources/js/composables/useUiMode.test.ts` - new file, 5 Vitest tests
- `resources/css/app.css` - `@custom-variant product` keyed on `[data-mode='product']`
- `resources/js/layouts/AppLayout.vue` - calls `useUiMode({ apply: true })`, the one writer of `data-mode` on the document root
- `resources/js/components/ProductLastStep.vue` - new: the green callout with the badge and a link back to the product
- `resources/js/pages/templates/show.vue` - opens the Add to OBS tab first in product mode, gives that tab `product:` green classes, mounts `ProductLastStep` in the OBS panel only in product mode
- `resources/js/pages/products/show.vue` - the finished band's OBS button links with `?state=product&product=<slug>`

### Claims
- **C1** [code] `parseUiMode()` returns `mode: 'product'` only when the query's `state` is exactly `product`, and `product` only when the mode is set and the `product` value matches `^[a-z][a-z0-9_]{0,49}$`; anything else yields nulls.
- **C2** [code] `useUiMode({ apply: true })` runs a `watchEffect` that sets `document.documentElement.dataset.mode` to the mode or deletes it; it is called once, in `AppLayout.vue`, and nowhere else.
- **C3** [code] `app.css` declares `@custom-variant product (&:is([data-mode='product'] *))`, so a `product:` utility applies only inside a root carrying that attribute.
- **C4** [code] In `templates/show.vue`, `mainTab` initialises to `obs` when the mode is `product` and to `overview` otherwise; the `obs` tab button carries `product:border-t-green-400 product:bg-green-600 product:text-white product:hover:bg-green-700`; `ProductLastStep` is rendered with `v-if="uiMode === 'product'"` inside the OBS panel, so it is absent from the DOM outside the mode.
- **C5** [code] `products/show.vue` appends `?state=product&product=<slug>` only to the OBS button in the finished band; the overlay row link under "Your overlay" is unchanged.
- **C6** [test] `useUiMode.test.ts` asserts C1 for a path, an absolute URL with a fragment, a missing parameter, an unknown value, a product without the mode, a non-slug product, and `applyUiMode()` setting and removing the attribute on a given root and tolerating a null root.
- **C7** [unverified] On `overlabels.test` on 2026-09-09, opening the installed Chat Checkin globe from the green band landed on the overlay page with the Add to OBS tab open and green and the callout above the panel; the same page without the query showed the Details tab and no callout.

### Unchanged
- `AddToObsPanel.vue` is not in the diff; the callout sits above it in the same panel.
- No other page reads the mode. `HelpContext` ignores undeclared query parameters, so `state` and `product` on a URL change no help match.

### Risk
`data-mode` on the document root is a new global attribute; nothing else in the app sets or reads it. The mode drops on any navigation whose URL lacks the query, which is the intended lifetime.
