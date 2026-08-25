import { describe, expect, it } from 'vitest';
import { FUSE_OPTIONS, buildHelpSearch, docLabel, keywordMatch, sectionMatch, type HelpDoc } from './helpSearch';

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

function page(slug: string, kind: 'guide' | 'tutorial', title: string, lead = '', keywords: string[] = []): HelpDoc {
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
    keywords,
  };
}

const DOCS: HelpDoc[] = [
  page('chat', 'guide', 'Chat', 'Show live Twitch chat in an overlay.'),
  // Shaped like the real editor guide: the words people search for are in the
  // body, which fuzzy search cannot see into, and nowhere in the title or lead.
  page('editor', 'guide', 'The code editor', 'Type [[[ and the editor offers every tag you can use.', [
    'autocomplete',
    'bang snippets',
    'codemirror',
  ]),
  page('controls', 'guide', 'Controls', 'Values you can change without editing the template.'),
  ref('chat', 'foreach-loops', 'Foreach Loops'),
  ref('goals', 'foreach-loops', 'Foreach Loops'),
  ref('raw', 'foreach-loops', 'Foreach Loops'),
  ref('channel_name', 'template-tags', 'Template Tags'),
  ref('channel_game', 'template-tags', 'Template Tags'),
  ref('kofi', 'integration-controls', 'Integration Controls'),
  ref('stream-goes-offline', 'eventsub-events', 'EventSub Events'),
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
    expect(sectionMatch(DOCS, 'EventSub Events')).toHaveLength(1);
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

describe('keywordMatch', () => {
  it('finds a page by a word that only its keywords declare', () => {
    // The motivating bug. "autocomplete" appears five times in the editor guide
    // and as one of its headings, and searching for it returned nothing at all:
    // Fuse's field norm scores an exact match in a 20KB body at 0.98, which the
    // cutoff correctly throws away as coincidence.
    expect(keywordMatch(DOCS, 'autocomplete').exact.map((d) => d.slug)).toEqual(['editor']);
  });

  it('matches a partial of a keyword, and separately from an exact one', () => {
    expect(keywordMatch(DOCS, 'autocom').partial.map((d) => d.slug)).toEqual(['editor']);
    expect(keywordMatch(DOCS, 'autocom').exact).toEqual([]);
  });

  it('matches a word inside a multi-word keyword', () => {
    // `bang snippets` is one keyword, not two, but someone typing either half
    // of it is asking for the same page.
    expect(keywordMatch(DOCS, 'snip').partial.map((d) => d.slug)).toEqual(['editor']);
    expect(keywordMatch(DOCS, 'bang').partial.map((d) => d.slug)).toEqual(['editor']);
  });

  it('prefixes only, so a fragment from the middle of a keyword matches nothing', () => {
    // Substring matching would make every short query noise: `ang` would pull
    // in `bang`, `ode` would pull in `codemirror`.
    expect(keywordMatch(DOCS, 'ang').partial).toEqual([]);
    expect(keywordMatch(DOCS, 'ode').partial).toEqual([]);
  });

  it('ignores a partial too short to be asking for anything', () => {
    expect(keywordMatch(DOCS, 'co').partial).toEqual([]);
  });

  it('still honours an exact match at any length, since exact is unambiguous', () => {
    expect(keywordMatch([page('x', 'guide', 'X', '', ['if'])], 'if').exact).toHaveLength(1);
  });

  it('ignores punctuation and case, like the section pass', () => {
    expect(keywordMatch(DOCS, 'AutoComplete').exact.map((d) => d.slug)).toEqual(['editor']);
    expect(keywordMatch(DOCS, 'bang-snippets').exact.map((d) => d.slug)).toEqual(['editor']);
  });

  it('never matches a document with no keywords declared', () => {
    expect(keywordMatch(DOCS, 'chat').exact).toEqual([]);
    expect(keywordMatch(DOCS, 'chat').partial).toEqual([]);
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

  it('answers a keyword query that fuzzy search alone returns nothing for', () => {
    expect(search.search('autocomplete', 50).map((d) => d.slug)).toContain('editor');
    expect(search.search('codemirror', 50).map((d) => d.slug)).toContain('editor');
  });

  it('puts an exact keyword ahead of a fuzzy match on a word that merely looks like it', () => {
    // `bang` is a declared keyword of the editor guide. Nothing else in the
    // corpus is about it, so a lookalike must not come first.
    expect(search.search('bang snippets', 50)[0].slug).toBe('editor');
  });

  it('keeps a partial keyword behind anything that matched outright', () => {
    // `chat` is a page and a reference entry by name. `chat` is not a keyword
    // of the editor guide, but this pins the ordering rule the other way round:
    // a hint never displaces a real match.
    const hits = search.search('controls', 50);
    expect(hits[0].slug).toBe('controls');
  });

  it('keeps keywords out of the fuzzy index entirely', () => {
    // This is the actual invariant. Fuse normalises a document's score across
    // all its keys, so a sixth key moves every score in the corpus - measured
    // against the real index it dropped `all-ko-fi-events` from "kofi" and
    // `bot/random-and-counters` from "raid", the tuned behaviour the weights
    // exist to produce. Keywords have to stay a pass alongside, not a key.
    const keys = (FUSE_OPTIONS.keys ?? []).map((k) => (typeof k === 'string' ? k : (k as { name: string }).name));
    expect(keys).not.toContain('keywords');
  });

  it('leaves ranking untouched for a corpus with no keywords at all', () => {
    // The whole reason keywords are a separate pass: adding a sixth Fuse key
    // renormalises every score in the corpus. Measured against the real index
    // that dropped `all-ko-fi-events` from "kofi" and `bot/random-and-counters`
    // from "raid". Strip the keywords and every result must be identical.
    const stripped = buildHelpSearch(DOCS.map((d) => ({ ...d, keywords: [] })));

    for (const q of ['foreach', 'chat', 'channel_name', 'template tags', 'controls', 'kofi']) {
      expect(stripped.search(q, 50).map((d) => d.url)).toEqual(search.search(q, 50).map((d) => d.url));
    }
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
