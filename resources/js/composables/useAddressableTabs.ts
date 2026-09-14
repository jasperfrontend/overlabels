import { computed, ref, toValue, watch, type MaybeRefOrGetter, type Ref } from 'vue';

/**
 * Tabs you can link to. A page says which tab keys exist, the URL fragment
 * says which one is open, and clicking a tab rewrites the fragment in place.
 *
 * Deliberately knows nothing about products or any other flow. The old
 * arrangement had it the other way round - /templates/show asked "am I in a
 * product install?" and hard-coded "then open Add to OBS" - which meant every
 * future step that wanted a tab opened had to teach another page about product
 * mode. Here the page only declares that its tabs are addressable, and the
 * product banner becomes one caller among many: a help page, a link you paste
 * to someone, the back button.
 *
 * The fragment, not the query string: this is a position within the page, it
 * never needs to reach the server, and the query string is already spoken for
 * by the flow's own `?state=product&product=<slug>`. Same division the overlay
 * token runs on. `tab-` prefixed so one fragment namespace can carry element
 * targets (`el-`) beside tabs without ever colliding.
 */
const TAB_PREFIX = 'tab-';

/** Pure: the tab key a fragment names, or null when it names none of them. */
export function tabKeyFromHash(hash: string, keys: string[]): string | null {
  const raw = hash.startsWith('#') ? hash.slice(1) : hash;

  if (!raw.startsWith(TAB_PREFIX)) return null;

  const key = raw.slice(TAB_PREFIX.length);

  // A tab this page does not have is not an error: /templates/show builds its
  // strip conditionally, so a link to #tab-triggers is honest on an alert and
  // meaningless on a static overlay. Falling through to the default beats
  // rendering a panel that does not exist.
  return keys.includes(key) ? key : null;
}

/** Pure: the same href with its fragment set to this tab, query string intact. */
export function urlWithTab(href: string, key: string): string {
  const [withoutHash] = href.split('#');

  return `${withoutHash}#${TAB_PREFIX}${key}`;
}

/**
 * A `v-model` ref for a TabStrip that reads the fragment on arrival and writes
 * it back on every change.
 *
 * replaceState, never pushState: a tab is a view of one page, not a place you
 * travelled to, so the back button stays an exit from the page rather than an
 * undo log of tab clicks. Nothing is written on arrival either - a visit with
 * no fragment keeps its clean URL until the reader actually picks a tab.
 */
export function useAddressableTabs(keys: MaybeRefOrGetter<string[]>, fallback: string): Ref<string> {
  const initial = typeof window === 'undefined' ? null : tabKeyFromHash(window.location.hash, toValue(keys));
  const active = ref<string>(initial ?? fallback);

  watch(active, (key) => {
    if (typeof window === 'undefined') return;

    window.history.replaceState(window.history.state, '', urlWithTab(window.location.href, key));
  });

  return active;
}

/** Convenience for the common `{ key, ... }[]` shape a TabStrip is given. */
export function tabKeysOf(tabs: MaybeRefOrGetter<{ key: string }[]>): Ref<string[]> {
  return computed(() => toValue(tabs).map((tab) => tab.key));
}
