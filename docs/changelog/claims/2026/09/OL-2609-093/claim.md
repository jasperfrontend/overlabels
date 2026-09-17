## OL-2609-093 - docs(controls): ExpressionDataContext docblock names the key it actually uses

**Shipped:** 2026-09-18
**Commit:** `git log --grep=OL-2609-093`

Closes the note in the OL-2609-060 audit and the Unchanged line of OL-2609-092.

### Surface
- `app/Services/Controls/ExpressionDataContext.php` - class docblock: `"c:<broadcastKey>"` becomes `"c:<tagIdentifier>"`

### Claims
- **C1** [code] The class docblock of `ExpressionDataContext` says the map is keyed `"c:<tagIdentifier>"`, which is what `for()` computes at the `$key = 'c:'.$control->tagIdentifier();` line.
- **C2** [code] No line outside the docblock changed; `git show` for this commit has one hunk.
