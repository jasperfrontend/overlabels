import type { AppPageProps } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';

/**
 * Product UI mode. ON while a product setup flow is active on the account,
 * OFF otherwise. You are in the flow or you are not; there is no half, and
 * nothing in a URL can start one. The shared `productSetup` prop is the one
 * source: the server recomputes it from live facts on every visit.
 *
 * The URL can still carry a last-mile hint, `?state=product&product=<slug>`,
 * which the green band on a finished product page attaches to its OBS
 * button. The hint opens the Add to OBS tab first and shows the callout with
 * the way back. It never turns the mode on: the frame, the banner and the
 * `product:` styling come from the flow alone.
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
  /** The control the next step points at, matched by useProductTarget(). */
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
 * True when the flow's next step points at this control. A page binds it as
 * `:class="{ 'product-target': isTarget }"` on the one element the step is
 * about, so the ruthless mode reaches the exact toggle or button rather than
 * stopping at the page's front door. Keys live in ProductSetup::STEPS.
 */
export function useProductTarget(key: string) {
  const { mode, target } = useUiMode();

  return computed(() => mode.value === 'product' && target.value === key);
}
