# Changelog - September 2026

## OL-2609-007 - September 1st, 2026 - feat(checkin): Chat Checkin - !checkin pins your viewers on a 3D globe

Viewers type `!checkin Rotterdam, NL` in chat and land as a pin on the streamer's overlay - on a
slowly rotating 3D globe whose continents are drawn from the checkin database itself. One command,
one pin per viewer (checking in again moves it), city-level only by construction: places resolve
against a local GeoNames index of ~235,000 cities, so nothing finer than a city can ever reach the
screen. No geocoding API, no per-request cost, no counterparty.

Because it shipped as a full ExternalIntegration, everything Overlabels already does lights up at
once:

- `[[[checkin_globe]]]` drops the globe into any static overlay, styled entirely by CSS custom
  properties and plain CSS on the HTML name labels. The 3D library is a lazy chunk that only
  downloads when a template contains the tag.
- `[[[foreach:checkins as pin]]]` is the raw feed for custom visualizations, newest first, with
  live one-pin delta updates over the existing alerts channel and its own foreach cap.
- Ten provisioned `c:checkin:*` controls: per-stream counters that reset at go-live (checkins,
  unique countries, farthest km) plus persistent latest-pin values - so haversine math lands in
  Expression Controls for free. Set a home city and every pin gets its distance.
- "Chat Checkin" is an alert trigger with a full `event.*` tag set, so a checkin can fire an
  alert, TTS or a chat reply.
- Place resolution is population-ranked with typo tolerance, calibrated against the real index so
  junk misses ("gyat") while typos land ("amsterdamm").

Shipped across OL-2609-003 through OL-2609-007, plus the `!checkin` handler in the bot repo.

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
