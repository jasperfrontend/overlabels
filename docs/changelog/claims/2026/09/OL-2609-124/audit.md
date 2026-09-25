## Audit of OL-2609-124 - fix(products): the chat designer sweeps only expired preview tokens, so two sessions no longer kill each other's frame

**Audited:** 2026-09-25
**Commit:** 0295427949abd4dbcd55567a6efc54c74baad20f
**Verdict:** CLEAN

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `app/Support/ChatDesigner.php:230-233 @0295427` - `where('user_id', $user->id)`, `where('metadata->purpose', self::TOKEN_PURPOSE)`, `where(fn ($query) => $query->where('expires_at', '<=', now())->orWhere('is_active', false))`; `ChatDesigner.php` identical @HEAD (`git diff 0295427 HEAD` empty) |
| C2 | CONFIRMED | `ChatDesigner.php:220-228 @0295427` - reuse branch logic identical to parent (diff adds only a two-line comment at 221-222); `app/Models/OverlayAccessToken.php:119-132 @0295427` `findByToken()` returns null when `! $token->isValid()`, and `isValid()` at 145 returns false when `expires_at->isPast()`; no later commit touches either file |
| C3 | CONFIRMED | `tests/Feature/ProductChatDesignerTest.php:237-261 @0295427` - flushes session, asserts `$second` not `$first` (250), `findByToken($firstToken)` not null (251), purpose-scoped count 2 (252); after expiring the first and a further flushed mint, `findByToken($firstToken)` null (259) and count 2 (260). `php artisan test --filter=ProductChatDesignerTest`: 17 passed |
| C4 | CONFIRMED | `ProductChatDesignerTest.php:220-235 @0295427` - expires the token, flushes session, asserts new URL and user token count 1 (233-234); passed in the same run |
| C5 | CONFIRMED | `ProductChatDesignerTest.php:199-218 @0295427` - two renders, same URL (208), `toHaveCount(1)` (211); passed in the same run |
| C6 | CONFIRMED | `ChatDesigner.php:246 @0295427` - `'expires_at' => now()->addDay()` is a context line, not in the diff; sweep at 230-238 deletes expired rows only on a mint |
| C7 | UNVERIFIABLE | tagged [unverified] (fail-first run against a tree that no longer exists) |

### Surface
Complete.

### Findings
None.

### Notes
- C3's "a further mint removes it" (the first token specifically) is inferred from the count: three mints, count 2, one row gone. Line 259's `findByToken()` would also return null for an expired row that was never deleted. No assertion checks that the first row itself is gone.
- The diff adds a two-line comment inside the reuse branch (`ChatDesigner.php:221-222 @0295427`). Surface mentions only "docblock updated". The comment does not change behaviour.
- A comment in the pre-existing test at `ProductChatDesignerTest.php:227 @0295427` still lists "a new browser" as something that ends a token. After this change that is no longer true, and the diff does not touch that line.
- The claims are numbered out of order in the file (C7 comes before C6).
- This supersedes the Unchanged line 1 of OL-2609-122, which it cites inline, and the unrecorded all-token sweep that the OL-2609-109 audit raised as F10. OL-2609-109 C31 and C33 still hold.
