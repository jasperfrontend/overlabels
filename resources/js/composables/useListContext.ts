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

// Build a deterministic breadcrumb context from a template's own attributes, for
// when there is no recorded navigation history (direct URL, fresh tab, or
// straight after create - sessionStorage is per-tab and dies with the tab).
// Mirrors the index page's filter labels (ownerMap/typeMap) so the crumb reads
// identically to the filtered list it links to.
export function deriveListContext(template: { type?: string | null; ownedByMe: boolean }): ListContext {
  const owner = template.ownedByMe ? 'My' : 'Public';
  const filterParam = template.ownedByMe ? 'mine' : 'public';
  const typeLabels: Record<string, string> = {
    static: 'static overlays',
    alert: 'event alerts',
    block: 'blocks',
  };
  const type = template.type ?? '';
  const typeLabel = typeLabels[type] ?? 'overlays';

  const params = new URLSearchParams({ filter: filterParam });
  if (type) params.set('type', type);

  return {
    title: `${owner} ${typeLabel}`,
    href: `${route('templates.index')}?${params.toString()}`,
  };
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

// A stored context is only trusted when this template could actually appear in
// the list it points to - a crumb must never claim a list that cannot contain
// the page it sits on. The canonical trap: copy a static overlay as a Block,
// and the new block's show page would freeze the SOURCE's list ("My static
// overlays" - still the freshest index visit at that moment) as its origin,
// then serve that wrong crumb forever. Checking the candidate's filter params
// against the template's own type + ownership rejects impossible contexts and
// self-heals any stale frozen origin already sitting in sessionStorage.
function matchesTemplate(ctx: ListContext, template: { type?: string | null; ownedByMe: boolean }): boolean {
  try {
    const params = new URL(ctx.href, window.location.origin).searchParams;
    const typeParam = params.get('type');
    if (typeParam && template.type && typeParam !== template.type) return false;
    if (params.get('filter') === 'mine' && !template.ownedByMe) return false;
    return true;
  } catch {
    return false;
  }
}

// Called once at mount on show/edit. Returns the context frozen for this
// template, freezing the current global context on first visit. Subsequent
// mounts for the same template (refresh, back/forward) return the frozen value.
//
// Precedence: a previously frozen origin wins, then the live list you navigated
// from (GLOBAL_KEY), then a context derived from the template itself (cold
// direct-paste, fresh tab, straight after create or copy) - but a frozen or
// global candidate is skipped when the template could not appear in that list
// (see matchesTemplate above).
export function captureListContext(templateId: number | string, template: { type?: string | null; ownedByMe: boolean }): ListContext {
  const frozen = read(originKey(templateId));
  if (frozen && matchesTemplate(frozen, template)) return frozen;

  const global = read(GLOBAL_KEY);
  const current = global && matchesTemplate(global, template) ? global : deriveListContext(template);
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
