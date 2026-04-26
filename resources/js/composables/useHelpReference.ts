import Fuse from 'fuse.js';
import { marked } from 'marked';
import { computed } from 'vue';

export interface HelpEntry {
  category: string;
  categoryLabel: string;
  slug: string;
  title: string;
  body: string;
  path: string;
  tag: string;
}

const CATEGORY_LABELS: Record<string, string> = {
  'template-tags': 'Template Tags',
  'eventsub-events': 'EventSub Events',
  'eventsub-tags': 'EventSub Tags',
  'foreach-loops': 'Foreach Loops',
};

const CATEGORY_ORDER = ['template-tags', 'eventsub-tags', 'eventsub-events', 'foreach-loops'];

const modules = import.meta.glob('/resources/help/reference/**/*.md', {
  query: '?raw',
  eager: true,
  import: 'default',
}) as Record<string, string>;

function humanize(slug: string): string {
  return slug.replace(/[-_]/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function extractTitle(body: string, fallback: string): string {
  const match = body.match(/^#\s+(.+)$/m);
  if (match) return match[1].trim();
  return fallback;
}

const entries: HelpEntry[] = Object.entries(modules)
  .map(([path, raw]) => {
    const match = path.match(/\/resources\/help\/reference\/([^/]+)\/([^/]+)\.md$/);
    if (!match) return null;
    const [, category, slug] = match;
    const body = raw.trim();
    // const tag = `[[[${slug}]]]`;
    const tag = slug.includes("-") ? '' : `[[[${slug}]]]`;
    return {
      category,
      categoryLabel: CATEGORY_LABELS[category] ?? humanize(category),
      slug,
      title: extractTitle(body, humanize(slug)),
      body,
      path,
      tag,
    } satisfies HelpEntry;
  })
  .filter((e): e is HelpEntry => e !== null)
  .sort((a, b) => {
    const ai = CATEGORY_ORDER.indexOf(a.category);
    const bi = CATEGORY_ORDER.indexOf(b.category);
    if (ai !== bi) return (ai === -1 ? 999 : ai) - (bi === -1 ? 999 : bi);
    return a.title.localeCompare(b.title);
  });

const bySlug = new Map<string, HelpEntry>();
for (const e of entries) bySlug.set(`${e.category}/${e.slug}`, e);

const slugToCategory = new Map<string, string>();
for (const e of entries) {
  if (!slugToCategory.has(e.slug)) slugToCategory.set(e.slug, e.category);
}

const fuse = new Fuse(entries, {
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

// Rewrite Obsidian-style [[slug]] wikilinks to real help-reference URLs.
// Operates on segments that are NOT inside a fenced code block and NOT inside
// an inline code span, so backticked content (e.g. `[[[raw]]]`) is untouched.
// The `(?<!\[)...(?!])` guards make sure we don't eat the inner `[[` of a
// triple-bracket template tag like [[[foo]]].
const WIKILINK_RE = /(?<!\[)\[\[([^\]|[]+?)(?:\|([^\]]+))?]](?!])/g;

function rewriteWikilinks(text: string): string {
  return text.replace(WIKILINK_RE, (_, slug, label) => {
    const trimmed = slug.trim();
    const category = slugToCategory.get(trimmed);
    const displayText = (label ?? trimmed).trim();
    if (category) return `[${displayText}](/help/reference/${category}/${trimmed})`;
    return `\`${displayText}\``;
  });
}

function preprocessMarkdown(md: string): string {
  // Split by fenced code blocks (```...```). Even indexes are prose, odd are
  // code blocks we leave alone.
  const fenceParts = md.split(/(```[\s\S]*?```)/g);
  return fenceParts
    .map((part, i) => {
      if (i % 2 === 1) return part; // fenced code block, preserve
      // In prose, also preserve inline-code spans. Split on backticks, even
      // indexes are normal prose, odd are inline code content (kept as-is).
      const inlineParts = part.split(/(`[^`\n]*`)/g);
      return inlineParts
        .map((seg, j) => (j % 2 === 1 ? seg : rewriteWikilinks(seg)))
        .join('');
    })
    .join('');
}

marked.setOptions({ breaks: true, gfm: true });

function escapeAttr(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Post-render pass: make every [[[tag]]] a click-to-copy <code> element.
// Runs AFTER marked so that brackets are never ambiguous with markdown syntax.
// If marked already wrapped a bare [[[tag]]] in <code>...</code> (from an
// author typing `[[[tag]]]`), we unwrap first so we don't produce nested
// <code><code>...</code></code>.
function enhanceTagsInHtml(html: string): string {
  // Unwrap any single-tag inline <code>[[[...]]]</code>.
  let out = html.replace(/<code>\s*(\[\[\[[^\[\]<>]+]]])\s*<\/code>/g, '$1');

  // Wrap every remaining [[[tag]]] in a clickable <code>.
  out = out.replace(/\[\[\[([^\[\]<>]+?)]]]/g, (_, inner) => {
    const tag = `[[[${inner}]]]`;
    return `<code class="ov-tag" role="button" tabindex="0" data-tag="${escapeAttr(tag)}" title="Click to copy">${tag}</code>`;
  });

  return out;
}

export function renderHelpMarkdown(md: string): string {
  return enhanceTagsInHtml(marked.parse(preprocessMarkdown(md)) as string);
}

export function useHelpReference() {
  const allEntries = computed(() => entries);

  function search(query: string, limit = 30): HelpEntry[] {
    const q = query.trim();
    if (!q) return entries.slice(0, limit);
    return fuse.search(q, { limit }).map((r) => r.item);
  }

  function get(category: string, slug: string): HelpEntry | undefined {
    return bySlug.get(`${category}/${slug}`);
  }

  function grouped(): { category: string; categoryLabel: string; items: HelpEntry[] }[] {
    const map = new Map<string, HelpEntry[]>();
    for (const e of entries) {
      if (!map.has(e.category)) map.set(e.category, []);
      map.get(e.category)!.push(e);
    }
    return Array.from(map.entries()).map(([category, items]) => ({
      category,
      categoryLabel: CATEGORY_LABELS[category] ?? humanize(category),
      items,
    }));
  }

  return { allEntries, search, get, grouped };
}
