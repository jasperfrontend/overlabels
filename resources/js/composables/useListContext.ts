// Single source of truth for the "list context" used by the breadcrumb and the
// post-delete redirect on template show/edit pages.
//
// The templates index page records the filtered list it is rendering (e.g. "My
// static overlays" -> /templates?filter=mine&type=static). When you open a
// template, show/edit FREEZE that context into a per-template key the first time
// they mount. Freezing per template id means:
//   - re-filtering the index later can't change where THIS page returns to,
//   - Inertia re-running setup() on browser back/forward reads the frozen value
//     instead of whatever the index was last filtered to,
//   - the breadcrumb and the delete redirect read the exact same value, so they
//     can never disagree (the old bug: breadcrumb said "My static overlays" but
//     delete dumped you on /templates).

export type ListContext = { title: string; href: string };

const GLOBAL_KEY = 'templates_list_context';
const originKey = (templateId: number | string) => `template_origin:${templateId}`;

function fallback(): ListContext {
  return { title: 'My overlays', href: route('templates.index') };
}

function read(key: string): ListContext | null {
  try {
    const stored = sessionStorage.getItem(key);
    if (stored) {
      const parsed = JSON.parse(stored);
      if (parsed && typeof parsed.title === 'string' && typeof parsed.href === 'string') {
        return parsed;
      }
    }
  } catch {
    /* ignore unavailable/corrupt sessionStorage */
  }
  return null;
}

function write(key: string, ctx: ListContext): void {
  try {
    sessionStorage.setItem(key, JSON.stringify(ctx));
  } catch {
    /* ignore unavailable sessionStorage (private mode, quota) */
  }
}

// Called by the templates index page whenever its filters change.
export function recordListContext(ctx: ListContext): void {
  write(GLOBAL_KEY, ctx);
}

// Called once at mount on show/edit. Returns the context frozen for this
// template, freezing the current global context on first visit. Subsequent
// mounts for the same template (refresh, back/forward) return the frozen value.
export function captureListContext(templateId: number | string): ListContext {
  const frozen = read(originKey(templateId));
  if (frozen) return frozen;

  const current = read(GLOBAL_KEY) ?? fallback();
  write(originKey(templateId), current);
  return current;
}

// Called after a template is deleted - its frozen origin is now dead weight.
export function clearListContext(templateId: number | string): void {
  try {
    sessionStorage.removeItem(originKey(templateId));
  } catch {
    /* ignore */
  }
}
