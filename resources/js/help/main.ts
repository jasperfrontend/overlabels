import '../../css/app.css';
import { buildHelpSearch, docLabel, type HelpDoc, type HelpSearch } from '../utils/helpSearch';

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
const SIDEBAR_SCROLL_KEY = 'help-sidebar-scroll';

/**
 * Generous, because naming a section has to return the section. `Template
 * Tags` is 65 entries, and the card on the reference index promises exactly
 * that number. The score cutoff, not this, is what bounds an ordinary query:
 * no fuzzy search over the current corpus keeps more than about fifty.
 */
const SEARCH_LIMIT = 150;

document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('help-sidebar');
  const input = document.getElementById('help-search') as HTMLInputElement | null;

  // Landing on a deep reference entry used to leave the sidebar scrolled to the
  // top, with the highlighted entry hundreds of pixels below the fold - the one
  // piece of "where am I" the nav exists to give you. An active entry wins over
  // the remembered position, since it is the more specific answer.
  if (!scrollActiveIntoView(sidebar)) {
    restoreSidebarScroll(sidebar);
  }
  sidebar?.addEventListener('scroll', () => saveSidebarScroll(sidebar, input));

  wireCopyButtons();
  wireCopyPageButton();
  wireBodyTagClicks();
  wireGlobalShortcut(input);
  wireTableOfContents();
  addCodeBlockCopyButtons();
  void renderMath();
  wireSearch(input);
});

/**
 * Search results open in a panel under the field. They used to replace the
 * sidebar tree, which only worked on pages that had one; the landing page has
 * the search in its hero and no sidebar at all.
 */
function wireSearch(input: HTMLInputElement | null) {
  const results = document.getElementById('help-search-results');
  const clearBtn = document.getElementById('help-search-clear');
  const container = input?.closest('.help-search');
  if (!input || !results || !clearBtn || !container) return;

  let searcher: HelpSearch | null = null;

  fetch('/help-index.json')
    .then((r) => r.json())
    .then((data: HelpDoc[]) => {
      searcher = buildHelpSearch(data);
      if (input.value.trim()) render();
    })
    .catch(() => {
      // Search becomes a no-op if the index 404s; the static tree still
      // works. Don't shout in production logs.
    });

  const close = () => {
    results.classList.add('hidden');
    results.innerHTML = '';
  };

  const render = () => {
    const q = input.value.trim();
    if (!q) {
      close();
      clearBtn.classList.add('hidden');
      return;
    }
    clearBtn.classList.remove('hidden');
    results.classList.remove('hidden');

    if (!searcher) {
      results.innerHTML = '<div class="help-search-empty">Loading...</div>';
      return;
    }

    const matches = searcher.search(q, SEARCH_LIMIT);
    if (matches.length === 0) {
      results.innerHTML = '<div class="help-search-empty">Nothing matched.</div>';
      return;
    }

    const head = `<div class="help-search-head">${matches.length} results</div>`;
    const body = matches
      .map(
        (e) => `
            <a href="${escapeHtml(e.url)}" class="help-search-result" role="option">
              <span class="${e.kind === 'reference' ? 'font-mono ' : ''}truncate text-[13px]">${escapeHtml(e.title)}</span>
              <span class="help-search-result-kind help-pill-text--${escapeHtml(e.kind)}">${escapeHtml(docLabel(e))}</span>
            </a>`,
      )
      .join('');
    results.innerHTML = head + body;
  };

  input.addEventListener('input', render);
  input.addEventListener('focus', () => {
    if (input.value.trim()) render();
  });
  clearBtn.addEventListener('click', () => {
    input.value = '';
    render();
    input.focus();
  });

  // Arrow keys walk the results; Enter follows the focused one, which is a
  // plain link, so the browser does that part. Escape puts the page back.
  const links = () => Array.from(results.querySelectorAll<HTMLAnchorElement>('a.help-search-result'));
  input.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowDown') {
      const first = links()[0];
      if (first) {
        e.preventDefault();
        first.focus();
      }
    } else if (e.key === 'Escape') {
      input.value = '';
      render();
      input.blur();
    }
  });
  results.addEventListener('keydown', (e) => {
    const items = links();
    const index = items.indexOf(document.activeElement as HTMLAnchorElement);
    if (index === -1) return;
    if (e.key === 'ArrowDown' && items[index + 1]) {
      e.preventDefault();
      items[index + 1].focus();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      (items[index - 1] ?? input).focus();
    } else if (e.key === 'Escape') {
      close();
      input.focus();
    }
  });

  document.addEventListener('click', (e) => {
    const target = e.target as HTMLElement | null;
    if (!target) return;

    // Category cards on the reference index fill the search with their label.
    const btn = target.closest('[data-help-search]') as HTMLElement | null;
    if (btn) {
      input.value = btn.getAttribute('data-help-search') ?? '';
      render();
      input.focus();
      input.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }

    if (!container.contains(target)) close();
  });
}

/**
 * Highlight the table-of-contents entry for the section under the reader.
 * The last heading that has scrolled past the top of the viewport wins, so
 * the active entry is the section currently being read, not the next one.
 */
function wireTableOfContents() {
  const links = Array.from(document.querySelectorAll<HTMLAnchorElement>('#help-toc .help-toc-link'));
  const headings = Array.from(document.querySelectorAll<HTMLElement>('.help-prose h2[id]'));
  if (!links.length || !headings.length) return;

  let ticking = false;
  const update = () => {
    ticking = false;
    let current = headings[0].id;
    for (const h of headings) {
      if (h.getBoundingClientRect().top < 120) current = h.id;
    }
    links.forEach((l) => l.classList.toggle('is-active', l.getAttribute('href') === `#${current}`));
  };

  window.addEventListener(
    'scroll',
    () => {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(update);
    },
    { passive: true },
  );
  update();
}

/**
 * "Copy page as Markdown" fetches the page's .md twin and copies that - the
 * source file byte for byte, which is what an assistant wants pasted in.
 * Copying the rendered text would lose the frontmatter and the fences.
 */
function wireCopyPageButton() {
  const button = document.querySelector<HTMLButtonElement>('[data-help-copy-page]');
  if (!button) return;

  const url = button.getAttribute('data-help-copy-page') ?? '';
  const label = button.textContent ?? '';

  button.addEventListener('click', async () => {
    try {
      const response = await fetch(url);
      if (!response.ok) throw new Error(String(response.status));
      const markdown = await response.text();
      await navigator.clipboard.writeText(markdown);
      button.textContent = 'Copied as Markdown';
      button.classList.add('is-copied');
      showToast('Copied the page as Markdown');
    } catch {
      showToast('Could not copy this page');
    }
    window.setTimeout(() => {
      button.textContent = label;
      button.classList.remove('is-copied');
    }, 1500);
  });
}

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

/**
 * Give every fenced code block a Copy button.
 *
 * The tutorials are built around "paste this in and you have a chat feed", and
 * until this existed the only thing you could copy was one tag at a time - so
 * the page that says copy the whole block made you select it by hand.
 *
 * Added client-side rather than in the markdown pipeline because it is a pure
 * affordance: it needs JS to do anything, and baking it into the HTML would put
 * the word "Copy" inside every code sample a crawler or an LLM reads.
 *
 * `textContent` is what gets copied, which is exactly right even though the
 * block is full of `.ov-tag` widgets - each one renders its own tag as its text,
 * so the result is the raw snippet the author wrote.
 */
function addCodeBlockCopyButtons() {
  document.querySelectorAll<HTMLPreElement>('.help-prose pre').forEach((pre) => {
    const source = pre.textContent ?? '';
    if (!source.trim()) return;

    // The wrapper owns the positioning context. Putting it on the <pre> itself
    // would scroll the button out of sight with a wide line.
    const wrapper = document.createElement('div');
    wrapper.className = 'help-code';
    pre.parentNode?.insertBefore(wrapper, pre);
    wrapper.appendChild(pre);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'help-code-copy';
    button.textContent = 'Copy';
    // Always visible, not hover-revealed: a touch device has no hover, and this
    // is the primary action on a tutorial page rather than a secondary one.
    button.addEventListener('click', async () => {
      const lines = source.trimEnd().split('\n').length;
      await copyToClipboard(source, undefined, `Copied ${lines} line${lines === 1 ? '' : 's'} to clipboard`);
      button.textContent = 'Copied!';
      button.classList.add('is-copied');
      window.setTimeout(() => {
        button.textContent = 'Copy';
        button.classList.remove('is-copied');
      }, 1200);
    });

    wrapper.appendChild(button);
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

/**
 * `message` exists because a whole code block is not something you can echo
 * back: quoting a single tag confirms what you got, quoting forty lines just
 * fills the screen with what you already copied.
 */
async function copyToClipboard(text: string, flashTarget?: HTMLElement, message?: string) {
  if (!text) return;
  try {
    await navigator.clipboard.writeText(text);
    if (flashTarget) flashCopied(flashTarget);
    showToast(message ?? `Copied ${text} to clipboard`);
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
    'fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-md border border-sidebar-border bg-popover px-3 py-2 text-sm text-foreground shadow-lg';
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
