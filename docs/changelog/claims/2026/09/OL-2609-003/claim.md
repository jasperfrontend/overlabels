## OL-2609-003 - feat(geo): local GeoNames gazetteer and place resolver

**Shipped:** 2026-09-01
**Commit:** `git log --grep=OL-2609-003`

### Surface
- `database/migrations/2026_09_01_120000_create_geo_places_tables.php` - new: `geo_places`, `geo_place_names`, pg_trgm extension + GIN index (pgsql-only statements)
- `app/Models/GeoPlace.php` - new: model over `geo_places`, timestamps off
- `app/Models/GeoPlaceName.php` - new: model over `geo_place_names`, timestamps off
- `app/Services/Geo/ResolvedPlace.php` - new: readonly DTO with `label()`
- `app/Services/Geo/PlaceResolverService.php` - new: free-text place query -> `ResolvedPlace`
- `app/Services/Geo/GeoNamesImporter.php` - new: streams a cities500 zip into the two tables
- `app/Console/Commands/ImportGeoNames.php` - new: `geo:import` command wrapping the importer
- `tests/Unit/PlaceResolverTest.php` - new file
- `tests/Unit/GeoNamesImporterTest.php` - new file

### Claims
- **C1** [code] The migration creates `geo_place_names` with a unique index on `(geo_place_id, name_normalized)` and, on pgsql only, runs `CREATE EXTENSION IF NOT EXISTS pg_trgm` plus a GIN `gin_trgm_ops` index on `name_normalized`.
- **C2** [code] `GeoNamesImporter::import()` reads the archive with `fgets` on a `zip://` stream handle; no call site loads the whole file into memory.
- **C3** [code] `GeoNamesImporter` stores a searchable name only when `PlaceResolverService::normalize()` output passes `isSearchableName()` (lowercase ASCII, max 100 chars); places upsert on `geonames_id` and names insert via `insertOrIgnore`.
- **C4** [test] `GeoNamesImporterTest` "re-running the import is idempotent" asserts a second import of the same archive inserts 0 names and leaves both row counts unchanged.
- **C5** [code] `PlaceResolverService::resolve()` with a resolvable country hint after the last comma queries only that `country_code`, exact then fuzzy, and returns null rather than falling back to another country.
- **C6** [test] `PlaceResolverTest` "a resolvable country hint never falls back to another country" asserts `Rotterdam, US` resolves to null while a Rotterdam NL row exists.
- **C7** [code] `exactMatch()` orders by `geo_places.population` desc, so an ambiguous bare name resolves to the most populous match.
- **C8** [code] `FUZZY_SIMILARITY_MIN` is `0.55` and `fuzzyMatch()` returns null when the top candidate's `similarity()` score is below it, when the query is shorter than 4 characters, or when the connection driver is not pgsql.
- **C9** [unverified] Threshold calibration against the fully imported local gazetteer (2026-09-01): junk queries top out at 0.5 similarity ("gyat" -> Geita via its stored alias "gya"), while genuine typos measured 0.583-0.75 ("barclona", "pariss", "amsterdamm").
- **C10** [test] `PlaceResolverTest` "garbage input is a miss, not a guess" seeds Geita with the alias "gya" and asserts `gyat` resolves to null.
- **C11** [unverified] A full `geo:import` of the real cities500 dump on the local dev database read 235,607 lines into 234,434 places and 878,034 names, and the spot-check queries in the session resolved Rotterdam/Paris/Barcelona/Tokyo/Sao Paulo/Moscow correctly.

### Unchanged
- Nothing consumes the new tables or services yet: no path outside the nine listed files references `GeoPlace`, `GeoPlaceName`, `PlaceResolverService`, or `GeoNamesImporter`. The checkin integration that will call the resolver is a later change.

### Risk
The gazetteer ships empty: `geo_places` has no rows until `geo:import` is run once per environment
(the dataset is not in the repo), and the resolver returns null for everything until then. The
migration also creates the pg_trgm extension on prod at deploy-time migrate; pg_trgm is trusted
since PG 13, so the app role may create it without superuser.
