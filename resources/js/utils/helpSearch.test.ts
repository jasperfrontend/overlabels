import { describe, expect, it } from 'vitest';
import { buildHelpSearch, docLabel, sectionMatch, type HelpDoc } from './helpSearch';

/**
 * A stand-in for the real corpus, shaped like it: a handful of reference
 * entries whose titles never contain their folder's name, plus the guides and
 * tutorials they compete with.
 *
 * That shape is the whole point. `chat`, `goals` and `raw` are foreach loops
 * and not one of them says "foreach" anywhere, which is why searching for the
 * section could never work through fuzzy matching alone.
 */
function ref(slug: string, category: string, categoryLabel: string, body = ''): HelpDoc {
  return {
    kind: 'reference',
    kindLabel: 'Reference',
    slug,
    title: slug,
    lead: '',
    url: `/help/reference/${category}/${slug}`,
    body,
    category,
    categoryLabel,
  };
}

function page(slug: string, kind: 'guide' | 'tutorial', title: string, lead = ''): HelpDoc {
  return {
    kind,
    kindLabel: kind === 'guide' ? 'Guide' : 'Tutorial',
    slug,
    title,
    lead,
    url: kind === 'tutorial' ? `/help/tutorials/${slug}` : `/help/${slug}`,
    body: '',
    category: null,
    categoryLabel: null,
  };
}

const DOCS: HelpDoc[] = [
  page('chat', 'guide', 'Chat', 'Show live Twitch chat in an overlay.'),
  page('controls', 'guide', 'Controls', 'Values you can change without editing the template.'),
  ref('chat', 'foreach-loops', 'Foreach Loops'),
  ref('goals', 'foreach-loops', 'Foreach Loops'),
  ref('raw', 'foreach-loops', 'Foreach Loops'),
  ref('channel_name', 'template-tags', 'Template Tags'),
  ref('channel_game', 'template-tags', 'Template Tags'),
  ref('kofi', 'integration-controls', 'Integration Controls'),
  ref('llms-txt', 'for-machines', 'For Machines'),
];

describe('sectionMatch', () => {
  it('returns a whole folder for the word that names it, even though no entry contains that word', () => {
    expect(sectionMatch(DOCS, 'foreach').map((d) => d.slug)).toEqual(['chat', 'goals', 'raw']);
  });

  it('matches the label the reference index puts on its category cards', () => {
    // /help/reference renders a button per category with data-help-search set
    // to exactly this string. If these stop agreeing the cards go dead again.
    expect(sectionMatch(DOCS, 'Foreach Loops')).toHaveLength(3);
    expect(sectionMatch(DOCS, 'Template Tags')).toHaveLength(2);
    expect(sectionMatch(DOCS, 'Integration Controls')).toHaveLength(1);
    expect(sectionMatch(DOCS, 'For Machines')).toHaveLength(1);
  });

  it('ignores punctuation, so the slug and the label are the same query', () => {
    expect(sectionMatch(DOCS, 'foreach-loops')).toHaveLength(3);
    expect(sectionMatch(DOCS, 'template_tags')).toHaveLength(2);
  });

  it('matches a prefix of the name but not a word inside it', () => {
    expect(sectionMatch(DOCS, 'integration')).toHaveLength(1);

    // "controls" and "tags" are questions about controls and tags. The guides
    // answering them have to keep winning, so a bare noun must not drag in
    // every entry of a folder that happens to end with it.
    expect(sectionMatch(DOCS, 'controls')).toEqual([]);
    expect(sectionMatch(DOCS, 'tags')).toEqual([]);
    expect(sectionMatch(DOCS, 'loops')).toEqual([]);
  });

  it('ignores queries too short to be naming a section', () => {
    expect(sectionMatch(DOCS, 'fo')).toEqual([]);
  });

  it('never matches a page, which has no folder', () => {
    expect(sectionMatch(DOCS, 'foreach').every((d) => d.kind === 'reference')).toBe(true);
  });
});

describe('buildHelpSearch', () => {
  const search = buildHelpSearch(DOCS);

  it('answers a section query with the section', () => {
    expect(search.search('foreach', 50).map((d) => d.slug)).toEqual(['chat', 'goals', 'raw']);
  });

  it('keeps ranked matches ahead of the rest of the section', () => {
    // The one entry that genuinely matches by name stays on top of the folder
    // it belongs to, rather than being sorted into the middle of it.
    const hits = search.search('channel_name', 50);
    expect(hits[0].slug).toBe('channel_name');
  });

  it('does not let a section query bury an ordinary match', () => {
    const slugs = search.search('chat', 50).map((d) => d.url);
    expect(slugs).toContain('/help/chat');
  });

  it('returns each document at most once', () => {
    const urls = search.search('template tags', 50).map((d) => d.url);
    expect(new Set(urls).size).toBe(urls.length);
  });

  it('honours the limit', () => {
    expect(search.search('foreach', 2)).toHaveLength(2);
  });

  it('lists the corpus for an empty query', () => {
    expect(search.search('', 3)).toHaveLength(3);
    expect(search.search('   ', 3)).toHaveLength(3);
  });
});

describe('docLabel', () => {
  it('names the folder for a reference entry, not the word Reference', () => {
    expect(docLabel(ref('goals', 'foreach-loops', 'Foreach Loops'))).toBe('Foreach Loops');
  });

  it('falls back to the kind for a page, which has no folder', () => {
    expect(docLabel(page('chat', 'guide', 'Chat'))).toBe('Guide');
    expect(docLabel(page('latest-follower', 'tutorial', 'Latest follower'))).toBe('Tutorial');
  });
});
