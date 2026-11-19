## Remedy of OL-2611-023 - feat(controls): a knob greys itself out while another holds a value; a Plain look for Emote Bubbles; the control dialog keeps config it does not draw; a per-product sample-chat ceiling

**Remedied:** 2026-11-19
**Claim:** OL-2611-024

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-024 C1 and C2 restate what `ControlConditionsTest` asserts; C3 names `RecipeDesignerBlockTest` as the test for the `=` half of C6 |
| F2 | RECORD | OL-2611-024 C4 restates what `BubblesPlainLookBackfillTest` "runs twice without adding Plain twice" asserts |
| F3 | RECORD | OL-2611-024 C5 restates that `EnabledIfBackfillTest` asserts one value, `bubble_color` on the Emote Bubbles install |
| F4 | RECORD | OL-2611-024 C6 names `ProductDesigner::controls()` as the part with no hunk, not the class |
| F5 | RECORD | OL-2611-024 C7 and C8 cite OL-2609-066 C10 as superseded for the dialog payload and for stored color config |
