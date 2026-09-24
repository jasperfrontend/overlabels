import { onMounted } from 'vue';

/**
 * Where Tab starts once a page has rendered.
 *
 * The sidebar comes first in the DOM, so with nothing focused the first Tab
 * lands on the logo and a page's own content sits twenty-odd presses further
 * on. After each page render this moves the sequential focus starting point
 * into the page: onto the element marked `data-tab-start` (a page's primary
 * list, so the next Tab lands on its first row), or onto the main landmark
 * when the page marks nothing.
 *
 * It runs from the app layout's onMounted. Inertia keys the page component on
 * every visit that does not preserve state, so the layout remounts on every
 * real navigation and stays put on a filter refresh - which is exactly when
 * focus must and must not move (a search box being typed in keeps it).
 *
 * Focusing a container, rather than its first row, is the usual route-change
 * pattern: the new page is announced before focus moves on, and the element
 * itself draws no ring (app.css). The target gets `tabindex="-1"` if it has
 * none, which makes it focusable by script without adding a Tab stop.
 */
export const TAB_START_ATTRIBUTE = 'data-tab-start';
export const MAIN_CONTENT_ID = 'main-content';

export function focusStartTarget(root: ParentNode = document): HTMLElement | null {
  return root.querySelector<HTMLElement>(`[${TAB_START_ATTRIBUTE}]`) ?? root.querySelector<HTMLElement>(`#${MAIN_CONTENT_ID}`);
}

export function focusStart(options: { scroll?: boolean } = {}): HTMLElement | null {
  const target = focusStartTarget();
  if (!target) return null;
  if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
  // Inertia restores the scroll position itself; the skip link is the one
  // caller that wants the page to move to what it focused.
  target.focus({ preventScroll: !options.scroll });
  return target;
}

export function useFocusStart(): void {
  onMounted(() => {
    // Something on the page may already have asked for focus (an autofocus
    // field); that wins.
    const active = document.activeElement;
    if (active && active !== document.body) return;
    focusStart();
  });
}
