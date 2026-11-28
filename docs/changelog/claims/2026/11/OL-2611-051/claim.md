## OL-2611-051 - docs(claims): record the products rail sticky block and the plays schema description

Remedies OL-2611-050 audit F1, F2.

**Shipped:** 2026-11-28
**Commit:** `git log --grep=OL-2611-051`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-050/remedy.md` - new file, the remedy of the OL-2611-050 audit

### Claims
- **C1** [code] In `resources/js/layouts/ProductsLayout.vue`, `lg:sticky lg:top-6` sits on a `<div>` wrapping both the `NavigationMenu` and the "Got questions?" paragraph, and no longer on `NavigationMenu`'s own `class`, so from `lg` up the category rail and the Discord line stick as one block (corrects OL-2611-050 Surface, audit F1).
- **C2** [code] In `resources/recipes/recipe-manifest.schema.json`, the `plays` property's `description` reads "What the product does on stream, as exactly three beats, each a picture, a short title and one line." in place of "What chat does, ..."; a description string only, no constraint changed (corrects OL-2611-050 Surface, audit F2).
