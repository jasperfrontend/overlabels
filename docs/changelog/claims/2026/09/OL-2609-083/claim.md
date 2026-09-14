## OL-2609-083 - fix(products): the trigger-collision refusal names the product's rule, not the person's pick

**Shipped:** 2026-09-15
**Commit:** `git log --grep=OL-2609-083`

### Surface
- `app/Services/Recipes/RecipeInstaller.php` - the sentence `assertNoAlertTriggerCollisions()` throws, plus a comment
- `tests/Feature/ProductDonationAlertTest.php` - the refusal test asserts the new sentence against a switched-off trigger

### Claims
- **C1** [code] `RecipeInstaller::assertNoAlertTriggerCollisions()` throws `"{product name} fires on {label} too, and your alert '{alert name}' already does. Delete that trigger on its Triggers tab, switching it off is not enough, then install again."`, the product name read from `$manifest['name']`; the queries and the loop are unchanged.
- **C2** [code] The check matches a mapping row regardless of its `enabled` column, as before, which is what the sentence's "switching it off is not enough" states.
- **C3** [test] `ProductDonationAlertTest` creates a Throne donation trigger with `enabled` false on the person's own alert, installs picking Ko-fi, and asserts the exact new sentence with nothing created.
- **C4** [unverified] On prod, 2026-09-15, picking Ko-fi produced the old sentence naming a Streamlabs alert, which read as the pick being ignored. Reported by Jasper.

### Unchanged
- Which installs are refused. The all-five-services collision from OL-2609-082 stands by decision; only the words changed.
