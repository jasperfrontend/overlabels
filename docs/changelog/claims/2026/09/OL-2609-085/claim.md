## OL-2609-085 - chore(streamlabs): drop the closed-beta banner, the app is approved

**Shipped:** 2026-09-15
**Commit:** `git log --grep=OL-2609-085`

### Surface
- `resources/js/pages/settings/integrations/streamlabs.vue` - the amber "Closed beta" block removed

### Claims
- **C1** [code] `settings/integrations/streamlabs.vue` no longer contains the string "Closed beta", the sentence about the application being under review, or the mailto link for early access.
- **C2** [unverified] Streamlabs approved the Overlabels app on 2026-09-15 ("App has been approved", reviewer escapingsuburbia771), which lifts the ten-user whitelist an unapproved app is limited to.

### Unchanged
- `StreamLabsIntegrationController` and the v2.0 OAuth flow are not in the diff; approval changes nothing about the client id, secret or scopes.
