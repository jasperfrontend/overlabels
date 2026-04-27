import '../../css/app.css';
import './styles.css';
import Fuse from 'fuse.js';

interface IndexEntry {
    category: string;
    categoryLabel: string;
    slug: string;
    title: string;
    body: string;
}

const SIDEBAR_SCROLL_KEY = 'help-reference-sidebar-scroll';

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.getElementById('help-reference-sidebar');
    const tree = document.getElementById('help-reference-tree');
    const results = document.getElementById('help-reference-results');
    const input = document.getElementById('help-reference-search') as HTMLInputElement | null;
    const clearBtn = document.getElementById('help-reference-search-clear');

    restoreSidebarScroll(sidebar);
    sidebar?.addEventListener('scroll', () => saveSidebarScroll(sidebar, input));

    wireCopyButtons();
    wireBodyTagClicks();
    wireGlobalShortcut(input);

    if (!input || !tree || !results || !clearBtn) return;

    let fuse: Fuse<IndexEntry> | null = null;
    let entries: IndexEntry[] = [];

    fetch('/help-reference-index.json', { cache: 'force-cache' })
        .then((r) => r.json())
        .then((data: IndexEntry[]) => {
            entries = data;
            fuse = new Fuse(entries, {
                keys: [
                    { name: 'title', weight: 2 },
                    { name: 'slug', weight: 2 },
                    { name: 'categoryLabel', weight: 0.3 },
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
            results.innerHTML =
                '<div class="p-4 text-center text-xs text-muted-foreground">Loading...</div>';
            return;
        }

        const matches = fuse.search(q, { limit: 50 }).map((r) => r.item);
        if (matches.length === 0) {
            results.innerHTML =
                '<div class="p-4 text-center text-xs text-red-400">Nothing matched.</div>';
            return;
        }

        const head = `<div class="px-2 pt-1 pb-2 text-[11px] font-medium text-muted-foreground/70 uppercase tracking-wide">${matches.length} results</div>`;
        const body = matches
            .map(
                (e) => `
            <a href="/help/reference/${e.category}/${e.slug}"
               class="flex w-full flex-col items-start gap-0.5 rounded-md px-2 py-1.5 text-left text-sm cursor-pointer hover:bg-accent">
              <span class="font-mono text-xs truncate w-full">${escapeHtml(e.title)}</span>
              <span class="text-[10px] uppercase tracking-wide text-muted-foreground/70">${escapeHtml(e.categoryLabel)}</span>
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
});

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
    return s
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
