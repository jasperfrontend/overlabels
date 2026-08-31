# Changelog - September 2026

## OL-2609-001 - September 1st, 2026 - feat(templates): filter alerts by event assignment

The `/templates` filter bar can now answer "which of my alerts are actually wired up". With Type set
to Event alert, a new Assignment dropdown offers All alerts, Assigned and Unassigned - assignment
meaning an event mapping (Twitch or external) belonging to the viewer, the same per-user scoping the
list's event icons already use. Another user's mapping on a public alert does not count as yours.

- Backend is two `when()` clauses in `OverlayTemplateController::index()`; the param is ignored
  entirely unless the type filter is `alert`, so it can never silently narrow overlays or blocks.
- `?type=alert&assignment=assigned` works as a direct link, and switching Type away from alerts
  drops the param again to keep the URL canonical.
- `FilterBar` is now always a five-column grid on desktop, so browsing between Overlays and Alerts
  never resizes the fields as the fifth one appears - pages with fewer fields leave the trailing
  columns empty.
- Pinned by `TemplateIndexAssignmentFilterTest`; the behavior tests were verified to fail against
  the unfiltered query.
