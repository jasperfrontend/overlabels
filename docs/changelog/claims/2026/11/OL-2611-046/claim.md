## OL-2611-046 - docs(claims): split the checkable rule half out of OL-2611-045 C9

Remedies OL-2611-045 audit F1.

**Shipped:** 2026-11-27
**Commit:** `git log --grep=OL-2611-046`

### Surface
- `docs/changelog/claims/2026/11/OL-2611-045/remedy.md` - new: remedy of the OL-2611-045 audit

### Claims
- **C1** [code] `OverlayTemplateController::updateTriggers()` validates `twitch.*.condition_value` with `required_with:twitch.*.condition_type`, so a Twitch trigger row carrying a `condition_type` with a null `condition_value` fails validation (corrects OL-2611-045 C9, audit F1).
- **C2** [code] `OverlayTemplateController::updateTriggers()` validates `external.*.condition_value` with `required_with:external.*.condition_type`, the same rule for external trigger rows (corrects OL-2611-045 C9, audit F1).
- **C3** [unverified] What the pre-OL-2611-045 frontend sent when "At least" was chosen on a row whose value was null (`condition_type` `at_least`, `condition_value` null) remains the only unverified half of OL-2611-045 C9; it needs a tree that no longer exists (restates OL-2611-045 C9, audit F1).
