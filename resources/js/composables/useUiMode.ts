import { usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';

/**
 * A UI mode carried by the URL: `?state=product&product=<slug>` on any app
 * page means "this visit is the last mile of a product install". It is a
 * fact, not a click, so a reload, a bookmark or a trip to OBS and back keep
 * it. Navigating anywhere without it drops it.
 *
 * One fact feeds two things: `data-mode` on the document root, so a Tailwind
 * variant (`product:`) can restyle an element in any component, and the
 * `mode` value, so a component can exist only in that mode with a `v-if`.
 */
export type UiMode = 'product';

export interface ParsedUiMode {
  mode: UiMode | null;
  /** The product slug the mode points back to, when the URL names one. */
  product: string | null;
}

const MODES: readonly UiMode[] = ['product'];
const SLUG = /^[a-z][a-z0-9_]{0,49}$/;

/** Pure: reads the mode out of a URL or path-with-query. Unknown values are no mode. */
export function parseUiMode(url: string): ParsedUiMode {
  const query = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
  const params = new URLSearchParams(query.split('#')[0]);
  const state = params.get('state');
  const mode = MODES.includes(state as UiMode) ? (state as UiMode) : null;
  const product = params.get('product');

  return {
    mode,
    product: mode && product && SLUG.test(product) ? product : null,
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

export function useUiMode(options: { apply?: boolean } = {}) {
  const page = usePage();
  const parsed = computed(() => parseUiMode(page.url));
  const mode = computed(() => parsed.value.mode);
  const product = computed(() => parsed.value.product);

  // The layout applies it once for the whole app; components only read it.
  if (options.apply) {
    watchEffect(() => applyUiMode(mode.value));
  }

  return { mode, product };
}
