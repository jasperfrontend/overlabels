import Fuse from 'fuse.js';
import '../../css/app.css';

/**
 * The one script for every help page.
 *
 * It was `help-reference/main.ts` and ran on the reference only; the prose pages
 * were an Inertia app with their own copy of the maths rendering and no search
 * at all. Both halves are Blade now, so this is search, click-to-copy and KaTeX
 * for tutorials, guides and reference entries alike.
 *
 * There is deliberately no framework here. The pages are server-rendered HTML
 * and this is the handful of behaviours that HTML cannot express on its own.
 */
interface IndexEntry {
  kind: string;
  kindLabel: string;
  slug: string;
  title: string;
  lead: string;
  url: string;
  body: string;
}

const SIDEBAR_SCROLL_KEY = 'help-sidebar-scroll';

document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('help-sidebar');
  const tree = document.getElementById('help-nav-tree');
  const results = document.getElementById('help-search-results');
  const input = document.getElementById('help-search') as HTMLInputElement | null;
  const clearBtn = document.getElementById('help-search-clear');

  // Landing on a deep reference entry used to leave the sidebar scrolled to the
  // top, with the highlighted entry hundreds of pixels below the fold - the one
  // piece of "where am I" the nav exists to give you. An active entry wins over
  // the remembered position, since it is the more specific answer.
  if (!scrollActiveIntoView(sidebar)) {
    restoreSidebarScroll(sidebar);
  }
  sidebar?.addEventListener('scroll', () => saveSidebarScroll(sidebar, input));

  wireCopyButtons();
  wireBodyTagClicks();
  wireGlobalShortcut(input);
  void renderMath();

  if (!input || !tree || !results || !clearBtn) return;

  let fuse: Fuse<IndexEntry> | null = null;

  fetch('/help-index.json', { cache: 'force-cache' })
    .then((r) => r.json())
    .then((data: IndexEntry[]) => {
      fuse = new Fuse(data, {
        keys: [
          { name: 'title', weight: 2 },
          { name: 'slug', weight: 2 },
          { name: 'lead', weight: 1 },
          { name: 'kindLabel', weight: 0.3 },
          { name: 'body', weight: 1 },
        ],
        threshold: 0.35,
        ignoreLocation: true,
        minMatchCharLength: 2,
        includeScore: true,
      });
    })
    .catch(() => {
      // Search becomes a no-op if the index 404s; the static tree still
      // works. Don't shout in production logs.
    });

  const renderResults = () => {
    const q = input.value.trim();
    if (!q) {
      tree.classList.remove('hidden');
      results.classList.add('hidden');
      results.innerHTML = '';
      clearBtn.classList.add('hidden');
      return;
    }
    clearBtn.classList.remove('hidden');
    tree.classList.add('hidden');
    results.classList.remove('hidden');

    if (!fuse) {
      results.innerHTML = '<div class="p-4 text-center text-xs text-muted-foreground">Loading...</div>';
      return;
    }

    const matches = fuse.search(q, { limit: 50 }).map((r) => r.item);
    if (matches.length === 0) {
      results.innerHTML = '<div class="p-4 text-center text-xs text-red-400">Nothing matched.</div>';
      return;
    }

    const head = `<div class="px-2 pt-1 pb-2 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">${matches.length} results</div>`;
    const body = matches
      .map(
        (e) => `
            <a href="${escapeHtml(e.url)}"
               class="flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-1.5 text-left text-sm cursor-pointer hover:bg-accent">
              <span class="${e.kind === 'reference' ? 'font-mono ' : ''}text-xs truncate w-full">${escapeHtml(e.title)}</span>
              <span class="text-[10px] uppercase tracking-wide text-muted-foreground/70">${escapeHtml(e.kindLabel)}</span>
            </a>`,
      )
      .join('');
    results.innerHTML = head + body;
  };

  input.addEventListener('input', renderResults);
  clearBtn.addEventListener('click', () => {
    input.value = '';
    renderResults();
    input.focus();
  });

  document.addEventListener('click', (e) => {
    const target = e.target as HTMLElement | null;
    const btn = target?.closest('[data-help-search]') as HTMLElement | null;
    if (!btn) return;
    const term = btn.getAttribute('data-help-search') ?? '';
    input.value = term;
    renderResults();
    input.focus();
  });
});

/**
 * Render any TeX the server left as `.help-math` placeholders. KaTeX is
 * client-side only, so the markdown pipeline stashes the source in a data
 * attribute and we typeset it here. Loaded lazily so the 24 pages without
 * maths never pay for the library.
 */
async function renderMath() {
  const nodes = document.querySelectorAll<HTMLElement>('.help-math:not([data-rendered])');
  if (!nodes.length) return;

  const [{ default: katex }] = await Promise.all([import('katex'), import('katex/dist/katex.min.css')]);

  nodes.forEach((node) => {
    const tex = node.dataset.tex ?? '';
    try {
      katex.render(tex, node, {
        displayMode: node.dataset.display === '1',
        throwOnError: false,
        output: 'html',
      });
    } catch {
      node.textContent = tex;
    }
    node.dataset.rendered = '1';
  });
}

function wireCopyButtons() {
  document.addEventListener('click', (e) => {
    const target = e.target as HTMLElement | null;
    const btn = target?.closest('[data-help-copy]') as HTMLElement | null;
    if (!btn) return;
    const code = btn.getAttribute('data-help-copy') ?? '';
    copyToClipboard(code);
  });
}

function wireBodyTagClicks() {
  const handler = (e: Event) => {
    const target = e.target as HTMLElement | null;
    const tagEl = target?.closest('.ov-tag') as HTMLElement | null;
    if (tagEl) {
      e.preventDefault();
      const tag = tagEl.getAttribute('data-tag') ?? tagEl.textContent ?? '';
      copyToClipboard(tag, tagEl);
      return;
    }

    const anchor = target?.closest('.help-prose a') as HTMLAnchorElement | null;
    if (!anchor) return;
    const href = anchor.getAttribute('href');
    if (href && /^https?:\/\//.test(href)) {
      anchor.setAttribute('target', '_blank');
      anchor.setAttribute('rel', 'noopener noreferrer');
    }
  };
  document.addEventListener('click', handler);
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const target = e.target as HTMLElement | null;
    if (!target?.closest('.ov-tag')) return;
    e.preventDefault();
    handler(e);
  });
}

function wireGlobalShortcut(input: HTMLInputElement | null) {
  if (!input) return;
  document.addEventListener('keydown', (e) => {
    if (e.altKey && (e.key === 'r' || e.key === 'R')) {
      e.preventDefault();
      input.focus();
      input.select();
    }
  });
}

async function copyToClipboard(text: string, flashTarget?: HTMLElement) {
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    if (flashTarget) flashCopied(flashTarget);
    showToast(`Copied ${text} to clipboard`);
  } catch {
    // clipboard blocked; ignore
  }
}

function flashCopied(el: HTMLElement) {
  const width = el.getBoundingClientRect().width;
  if (el.getAttribute('data-original') === null) {
    el.setAttribute('data-original', el.textContent ?? '');
  }
  el.style.width = `${width}px`;
  el.style.display = 'inline-block';
  el.style.textAlign = 'center';
  el.classList.add('ov-tag-copied');
  el.textContent = 'Copied!';
  window.setTimeout(() => {
    const original = el.getAttribute('data-original');
    if (original !== null) el.textContent = original;
    el.classList.remove('ov-tag-copied');
    el.removeAttribute('data-original');
    el.style.width = '';
    el.style.display = '';
    el.style.textAlign = '';
  }, 900);
}

function showToast(message: string) {
  const root = document.getElementById('help-toast-root');
  if (!root) return;
  const el = document.createElement('div');
  el.className =
    'fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-md border border-sidebar-border bg-card px-3 py-2 text-sm text-foreground shadow-lg';
  el.textContent = message;
  root.appendChild(el);
  window.setTimeout(() => {
    el.style.transition = 'opacity 0.3s';
    el.style.opacity = '0';
    window.setTimeout(() => el.remove(), 320);
  }, 1400);
}

function saveSidebarScroll(sidebar: HTMLElement, input: HTMLInputElement | null) {
  // Don't save scroll while in search mode - the list is a different set.
  if (input && input.value.trim().length > 0) return;
  try {
    localStorage.setItem(SIDEBAR_SCROLL_KEY, String(sidebar.scrollTop));
  } catch {
    // storage blocked; ignore
  }
}

/**
 * Centre the current page's sidebar entry, if it has one. Returns whether it
 * did anything, so the caller can fall back to the remembered scroll position.
 */
function scrollActiveIntoView(sidebar: HTMLElement | null): boolean {
  const active = sidebar?.querySelector<HTMLElement>('[data-help-active]');
  if (!sidebar || !active) return false;

  sidebar.scrollTop = Math.max(0, active.offsetTop - sidebar.clientHeight / 2);
  return true;
}

function restoreSidebarScroll(sidebar: HTMLElement | null) {
  if (!sidebar) return;
  try {
    const raw = localStorage.getItem(SIDEBAR_SCROLL_KEY);
    if (raw === null) return;
    const n = Number(raw);
    if (Number.isFinite(n)) sidebar.scrollTop = n;
  } catch {
    // storage blocked; ignore
  }
}

function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
