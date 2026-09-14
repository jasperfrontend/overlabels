import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, watch, watchEffect } from 'vue';

/**
 * Product UI mode. ON while a product setup flow is active on the account,
 * OFF otherwise. You are in the flow or you are not; there is no half, and
 * nothing in a URL can start one. The shared `productSetup` prop is the one
 * source: the server recomputes it from live facts on every visit.
 *
 * The URL can still carry a last-mile hint, `?state=product&product=<slug>`,
 * which the green band on a finished product page attaches to its OBS
 * button, and which shows the callout with the way back. Which TAB opens is
 * not its business: the same link carries `#tab-obs`, and any addressable
 * strip obeys that whether a flow is running or not. The hint never turns the
 * mode on either - the frame, the banner and the `product:` styling come from
 * the flow alone.
 *
 * One fact feeds two things: `data-mode` on the document root, so a Tailwind
 * variant (`product:`) can restyle an element in any component, and the
 * `mode` value, so a component can exist only in that mode with a `v-if`.
 */
export type UiMode = 'product';

export interface ParsedUiMode {
  /** The URL carries the last-mile hint. */
  lastMile: boolean;
  /** The product slug the hint points back to, when the URL names one. */
  product: string | null;
}

const SLUG = /^[a-z][a-z0-9_]{0,49}$/;

/** Pure: reads the last-mile hint out of a URL or path-with-query. Unknown values are no hint. */
export function parseUiMode(url: string): ParsedUiMode {
  const query = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
  const params = new URLSearchParams(query.split('#')[0]);
  const lastMile = params.get('state') === 'product';
  const product = params.get('product');

  return {
    lastMile,
    product: lastMile && product && SLUG.test(product) ? product : null,
  };
}

/**
 * Pure: the same href carrying the last-mile hint, fragment intact.
 *
 * The query keys come out in alphabetical order, and that is load-bearing,
 * not tidiness. An Inertia visit keeps a link's `#fragment` only when the URL
 * the server echoes back equals the requested one byte for byte (Inertia's
 * `setHashIfSameUrl`), and Laravel builds that echo from Symfony's normalised
 * query string, which sorts keys. `?state=product&product=x#tab-obs` came back
 * as `?product=x&state=product`, failed the compare, and the tab was lost on
 * a same-tab click while a middle-click, which never goes through Inertia,
 * kept it. Values are encoded the RFC 3986 way for the same reason.
 */
export function withLastMileHint(href: string, slug: string): string {
  const hashAt = href.indexOf('#');
  const hash = hashAt === -1 ? '' : href.slice(hashAt);
  const beforeHash = hashAt === -1 ? href : href.slice(0, hashAt);
  const queryAt = beforeHash.indexOf('?');
  const path = queryAt === -1 ? beforeHash : beforeHash.slice(0, queryAt);
  const params = new URLSearchParams(queryAt === -1 ? '' : beforeHash.slice(queryAt + 1));

  params.set('product', slug);
  params.set('state', 'product');
  params.sort();

  const query = [...params.entries()].map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`).join('&');

  return `${path}?${query}${hash}`;
}

/** Writes the mode onto <html data-mode> so CSS can see it, or removes it. */
export function applyUiMode(mode: UiMode | null, root: HTMLElement | null = typeof document === 'undefined' ? null : document.documentElement): void {
  if (!root) return;
  if (mode) {
    root.dataset.mode = mode;
  } else {
    delete root.dataset.mode;
  }
}

/** The slice of the shared `productSetup` prop the mode needs. */
export interface UiModeSetup {
  slug: string;
  ready: boolean;
  /** The next step's target control, when the flow has one. */
  next?: { target: string | null } | null;
}

export interface ResolvedUiMode extends ParsedUiMode {
  /** 'product' while a flow is active, null otherwise. Never set by the URL. */
  mode: UiMode | null;
  /** True when the flow the mode belongs to has nothing left to do. */
  ready: boolean;
  /** The control the next step points at, matched by its data-product-target. */
  target: string | null;
}

/**
 * Pure. The URL's product slug wins when both name one, and the flow's slug
 * fills in when the URL names none, so the way back is always known.
 */
export function resolveUiMode(parsed: ParsedUiMode, setup: UiModeSetup | null | undefined): ResolvedUiMode {
  if (setup) {
    return {
      mode: 'product',
      lastMile: parsed.lastMile,
      product: parsed.product ?? setup.slug,
      ready: setup.ready,
      target: setup.next?.target ?? null,
    };
  }

  return { mode: null, lastMile: parsed.lastMile, product: parsed.product, ready: false, target: null };
}

export function useUiMode(options: { apply?: boolean } = {}) {
  const page = usePage<AppPageProps>();
  const resolved = computed(() => resolveUiMode(parseUiMode(page.url), page.props.productSetup));
  const mode = computed(() => resolved.value.mode);
  const lastMile = computed(() => resolved.value.lastMile);
  const product = computed(() => resolved.value.product);
  const ready = computed(() => resolved.value.ready);
  const target = computed(() => resolved.value.target);

  // The layout applies it once for the whole app; components only read it.
  if (options.apply) {
    watchEffect(() => applyUiMode(mode.value));
  }

  return { mode, lastMile, product, ready, target };
}

/**
 * The control a step is about marks itself `data-product-target="<key>"` and
 * is done. No import, no composable call, no per-page opt-in - which is why
 * only two pages ever supported the old `useProductTarget` binding.
 *
 * An attribute rather than an id or a class: ids are a page-global namespace
 * and `streamlabs` is a word that will appear again, and a class is styling,
 * so a restyle deletes it without anyone noticing it was load-bearing. This
 * one names its own purpose, and a single grep finds every participant.
 *
 * `el-` prefixed in the fragment so it shares a namespace with `tab-` (see
 * useAddressableTabs) without either ever matching the other.
 */
const EL_PREFIX = 'el-';

/** Pure: the element key a fragment names, or null when it names none. */
export function elementKeyFromHash(hash: string): string | null {
  const raw = hash.startsWith('#') ? hash.slice(1) : hash;

  if (!raw.startsWith(EL_PREFIX)) return null;

  const key = raw.slice(EL_PREFIX.length);

  // The key reaches an attribute selector, so it is checked rather than
  // escaped. Everything the server emits is snake or kebab case.
  return /^[a-z0-9_-]+$/i.test(key) ? key : null;
}

/** An element may be behind a fetch or a v-if, so look again for about a second. */
function whenPresent(selector: string, found: (el: Element) => void, frames = 60): void {
  if (typeof document === 'undefined') return;

  const el = document.querySelector(selector);
  if (el) {
    found(el);

    return;
  }

  if (frames <= 0) return;

  requestAnimationFrame(() => whenPresent(selector, found, frames - 1));
}

/**
 * Lights up the control the flow's next step is about, and scrolls to it when
 * the reader arrived by following a link that named it.
 *
 * Two different kinds of thing, deliberately driven by two different sources.
 * The glow is a STATE - this is what you need, whenever you happen to be
 * looking at this page - so it follows the flow. The scroll is an EVENT, so it
 * follows the fragment: driven by state it would yank the viewport every time
 * someone opened that page while a flow happened to be active.
 *
 * Mounted once by AppLayout; components only mark themselves.
 */
export function useProductFocus(): void {
  const { mode, target } = useUiMode();
  const page = usePage<AppPageProps>();

  let lit: Element | null = null;

  const apply = () => {
    if (typeof window === 'undefined') return;

    if (lit) {
      lit.removeAttribute('data-product-focus');
      lit = null;
    }

    const key = mode.value === 'product' ? target.value : null;
    if (!key || !/^[a-z0-9_-]+$/i.test(key)) return;

    whenPresent(`[data-product-target="${key}"]`, (el) => {
      el.setAttribute('data-product-focus', '');
      lit = el;

      if (elementKeyFromHash(window.location.hash) !== key) return;

      const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
      el.scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' });
    });
  };

  onMounted(apply);
  watch(() => page.url, apply);
  watch(target, apply);
}
