## OL-2609-075 - test(products): pin that every setup step target has an element to land on

**Shipped:** 2026-09-14
**Commit:** `git log --grep=OL-2609-075`

### Surface
- `tests/Feature/ProductTargetCoverageTest.php` - new file

### Claims
- **C1** [test] `ProductTargetCoverageTest` asserts every non-null `target` in `ProductSetup::STEPS` appears in a file under `resources/js` as the literal attribute `data-product-target="<target>"`.
- **C2** [test] It asserts some `.vue` file binds `data-product-target` on a line also containing `integration-`, which is the only way a statically-read source can see the per-service targets `ProductSetup::targetFor()` emits.
- **C3** [test] It asserts every statically written `data-product-target="..."` value in `resources/js` is a target `ProductSetup::STEPS` declares, so an attribute outliving the step that pointed at it is a failure.
- **C4** [unverified] All three were run against a tree with `data-product-target="token-create"` misspelled in `overlaytokens/index.vue` and the `integration-` binding deleted from `settings/integrations/index.vue`; all three failed, and all three passed again once both were restored.

### Unchanged
- `useProductFocus()` still gives up silently when it finds no element, after about a second of retries. The test exists because that silence is correct behaviour at runtime and useless as a signal in review; nothing about the runtime handling changed.
- `ProductSetup::STEPS` and `targetFor()` are not in the diff. This commit adds no target and renames none.
