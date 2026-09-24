## Audit of OL-2609-003 - feat(geo): local GeoNames gazetteer and place resolver

**Audited:** 2026-09-25
**Commit:** a990db633ca27f041a6e477a43282c69005cafb8
**Verdict:** FINDINGS

### Claims
| Claim | Verdict | Evidence |
|-------|---------|----------|
| C1 | CONFIRMED | `database/migrations/2026_09_01_120000_create_geo_places_tables.php @a990db63` - `geo_place_names` has `$table->unique(['geo_place_id', 'name_normalized'])`; inside `if (DB::connection()->getDriverName() === 'pgsql')` it runs `CREATE EXTENSION IF NOT EXISTS pg_trgm` and `CREATE INDEX geo_place_names_name_normalized_trgm ON geo_place_names USING gin (name_normalized gin_trgm_ops)`; file unchanged @HEAD |
| C2 | CONFIRMED | `app/Services/Geo/GeoNamesImporter.php @a990db63` - `import()` opens `fopen('zip://'.$zipPath.'#'.self::INNER_FILE, 'r')` and loops `while (($line = fgets($handle)) !== false)`, flushing every `BATCH_SIZE` (500); no `file()`/`file_get_contents()`/`ZipArchive` read anywhere in the file; `download()` streams to disk via the `sink` option; unchanged @HEAD |
| C3 | CONFIRMED | `app/Services/Geo/GeoNamesImporter.php @a990db63` - `parseLine()` keeps a candidate only if `isSearchableName(PlaceResolverService::normalize($candidate))`, which requires non-empty, `strlen <= 100`, and `/^[a-z0-9][a-z0-9 .'-]*$/`; `flush()` calls `upsert(..., ['geonames_id'], ...)` and `insertOrIgnore($nameRows)`; unchanged @HEAD |
| C4 | UNVERIFIABLE (not run) | `tests/Unit/GeoNamesImporterTest.php @a990db63` test "re-running the import is idempotent" asserts `GeoPlace::count()` and `GeoPlaceName::count()` unchanged and `$second['names']` toBe 0 - matches the claim; NOT run: `php artisan test tests/Unit/PlaceResolverTest.php tests/Unit/GeoNamesImporterTest.php` failed before booting with "Failed opening required ... vendor/autoload.php" (the audit worktree has no `vendor/` and no `.env`); file unchanged @HEAD |
| C5 | CONFIRMED | `app/Services/Geo/PlaceResolverService.php @a990db63` - in `resolve()`, when `splitCountryHint()` yields a non-null code it returns `$this->exactMatch($head, $countryCode) ?? $this->fuzzyMatch($head, $countryCode)` mapped or null, with no further lookup; both methods apply `where('geo_places.country_code', $countryCode)`; unchanged @HEAD |
| C6 | UNVERIFIABLE (not run) | `tests/Unit/PlaceResolverTest.php @a990db63` - `beforeEach` seeds Rotterdam NL; test "a resolvable country hint never falls back to another country" asserts `resolve('Rotterdam, US')` toBeNull - matches the claim; NOT run, same `vendor/autoload.php` failure as C4; unchanged @HEAD |
| C7 | CONFIRMED | `app/Services/Geo/PlaceResolverService.php @a990db63` - `exactMatch()` has `->orderByDesc('geo_places.population')` before `->first()`; unchanged @HEAD |
| C8 | CONFIRMED | `app/Services/Geo/PlaceResolverService.php @a990db63` - `private const float FUZZY_SIMILARITY_MIN = 0.55`; `fuzzyMatch()` returns null if `strlen($name) < self::FUZZY_MIN_LENGTH` (4) or driver `!== 'pgsql'`, and if `(float) similarity_score < self::FUZZY_SIMILARITY_MIN`; unchanged @HEAD |
| C9 | UNVERIFIABLE | tagged [unverified]; depends on the fully imported gazetteer, which is not in the repo |
| C10 | UNVERIFIABLE (not run) | `tests/Unit/PlaceResolverTest.php @a990db63` test "garbage input is a miss, not a guess" seeds Geita (TZ) with names `Geita`, `gya` and asserts `resolve('gyat')` toBeNull - matches the claim; NOT run, same failure as C4; unchanged @HEAD |
| C11 | UNVERIFIABLE | tagged [unverified]; a local run against the real dump, not reproducible in-repo |

### Surface
Complete.

### Findings
- **F1** audit incomplete - the three [test] claims C4, C6 and C10 were not run: the audit worktree has no `vendor/` or `.env`, so `php artisan test` died on `vendor/autoload.php` before loading any test; each test exists and asserts what its claim says, but a passing run is unconfirmed. Run `php artisan test tests/Unit/PlaceResolverTest.php tests/Unit/GeoNamesImporterTest.php` in a full checkout against the pgsql test database (C10 and the fuzzy typo test depend on pgsql, which `phpunit.xml` sets).

### Notes
- Unchanged line confirmed @a990db63: `git grep` for `GeoPlace`, `GeoPlaceName`, `PlaceResolverService`, `GeoNamesImporter` outside the nine Surface files hits only this claim.md. It has since been superseded as intended by OL-2609-004 (C5, `BotCheckinController::store()` calls `PlaceResolverService::resolve()`) and OL-2609-007 (C5, `landmask.ts` generated from `geo_places`).
- No commit after a990db63 touches any of the nine Surface files (`git log a990db63..HEAD -- <paths>` is empty), so every @HEAD reading equals the shipped revision.
- No contradiction found in `CLAUDE.md`: the migration uses `Schema`/`DB::statement` only and references no Eloquent model.
