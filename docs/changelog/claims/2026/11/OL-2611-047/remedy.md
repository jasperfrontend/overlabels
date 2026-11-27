## Remedy of OL-2611-047 - feat(checkin): hexagon globe, mixed land tones, bold text and sample checkins in the designer

**Remedied:** 2026-11-28
**Claim:** OL-2611-048

| Finding | Outcome | What |
|---------|---------|------|
| F1 | RECORD | OL-2611-048 C1 discloses the `design.vue` preview caption sentence under `has('sample_checkins')` |
| F2 | RECORD | OL-2611-048 C2-C4 restate OL-2609-134 C5: `checkinGlobe.ts:397` writes inline opacity on every label every frame and never clears it; `is-hidden` follows the name fade |
