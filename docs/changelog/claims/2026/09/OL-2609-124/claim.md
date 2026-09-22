## OL-2609-124 - fix(products): the chat designer sweeps only expired preview tokens, so two sessions no longer kill each other's frame

**Shipped:** 2026-09-22
**Commit:** `git log --grep=OL-2609-124`

`ChatDesigner::previewToken()` swept every preview token on the account whenever it minted one, so
the designer open in a second browser invalidated the first browser's frame on every page load.
OL-2609-122 made the losing frame recover; this stops it losing. The sweep now takes only tokens
that have expired or been deactivated.

### Surface
- `app/Support/ChatDesigner.php` - the `$stale` query in `previewToken()` gains a `where` on `expires_at <= now()` or `is_active = false`; docblock updated
- `tests/Feature/ProductChatDesignerTest.php` - one test added

### Claims
- **C1** [code] The sweep in `previewToken()` selects preview tokens (`metadata->purpose = chat_designer`) of the caller that are expired (`expires_at <= now()`) or inactive (`is_active = false`), and nothing else.
- **C2** [code] The reuse branch is unchanged and is safe because `OverlayAccessToken::findByToken()` returns null for a row whose `isValid()` fails, which includes a past `expires_at`; a held-but-expired token therefore falls through to a mint rather than being handed to the frame.
- **C3** [test] `ProductChatDesignerTest` "lets a second session mint its own token without killing the first one": a second session (session flushed, same account) gets a different `preview_url`, the first token still resolves through `findByToken()`, and two preview tokens exist. After the first is expired, a further mint removes it and the count is two again.
- **C4** [test] The pre-existing "mints a fresh one, and drops the stale one, when the held token is gone" still passes: an expired token is swept and the count returns to one.
- **C5** [test] The pre-existing "keeps one preview token, marked and short-lived, across renders" still passes: one session across two renders still holds exactly one token.
- **C7** [unverified] C3's test was run against the tree with `ChatDesigner.php` stashed to its previous content and failed on the `findByToken($firstToken)` assertion ("Expecting null not to be null"), then passed with the change restored.
- **C6** [code] Preview tokens still expire a day after minting (`expires_at => now()->addDay()` is not in the diff), so the most an account can accumulate is one row per session opened within a day, each removed by the first mint after it expires.

### Unchanged
- The recovery from OL-2609-122 is not in the diff and still runs; it now covers a token that expired after a day or was revoked from the tokens page, rather than a second browser.
- The OBS token path was never part of this: the sweep has always been scoped to `metadata->purpose = chat_designer`, and that scope is unchanged.

### Risk
An account that opens the designer from several browsers within a day sees that many "Chat designer
preview" rows on its tokens page until they expire. Before, it saw one and had a broken preview.
