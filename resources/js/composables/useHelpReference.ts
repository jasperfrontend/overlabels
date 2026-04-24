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
    return {
      category,
      categoryLabel: CATEGORY_LABELS[category] ?? humanize(category),
      slug,
      title: extractTitle(body, humanize(slug)),
      body,
      path,
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

const PLACEHOLDER_PREFIX = 'OVHELPTAGPH';

function preprocessMarkdown(md: string): string {
  const placeholders: string[] = [];
  let out = md.replace(/\[\[\[([^\]]+)]]]/g, (_, inner) => {
    const idx = placeholders.length;
    placeholders.push(inner);
    return ` ${PLACEHOLDER_PREFIX}${idx} `;
  });

  out = out.replace(/\[\[([^\]|]+?)(?:\|([^\]]+))?]]/g, (_, slug, label) => {
    const trimmed = slug.trim();
    const category = slugToCategory.get(trimmed);
    const text = (label ?? trimmed).trim();
    if (category) return `[${text}](/help/reference/${category}/${trimmed})`;
    return `\`${text}\``;
  });

  out = out.replace(new RegExp(` ${PLACEHOLDER_PREFIX}(\\d+) `, 'g'), (_, idx) => {
    return `\`[[[${placeholders[Number(idx)]}]]]\``;
  });

  return out;
}

marked.setOptions({ breaks: true, gfm: true });

export function renderHelpMarkdown(md: string): string {
  return marked.parse(preprocessMarkdown(md)) as string;
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
