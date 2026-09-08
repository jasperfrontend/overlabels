import MiniSearch, { type SearchOptions } from 'minisearch';

/**
 * One search behaviour for the whole documentation site.
 *
 * There are two surfaces - the Alt+R palette inside the app and the search box
 * on every help page - and they must rank identically, or "search the docs"
 * means two different things depending on where you are standing. Both import
 * this.
 *
 * The engine is MiniSearch (BM25 over tokenised terms) and the unit it indexes
 * is a SECTION, not a page. Both were decided on the same night, for the same
 * reason: on stream, "controls", "chat", "controls chat", "enablecontrols",
 * "twitch controls" and "bot controls" all failed to find the bot commands
 * page, which says `!enablecontrols` three times under a heading called
 * Controls. The previous engine was a fuzzy string matcher run over each page
 * as one 20 KB blob, so it could not split a query into words, and a term
 * buried in a long body scored like coincidence however many times it appeared.
 * A section is a few hundred words about one thing, its heading is the best
 * summary of it anyone will write, and the result can link straight to it.
 */
export interface HelpSection {
  /** The heading's anchor on the page. Null for the text above the first heading. */
  id: string | null;
  heading: string;
  /** Raw markdown under the heading, frontmatter and heading line excluded. */
  text: string;
}

export interface HelpDoc {
  kind: string;
  kindLabel: string;
  slug: string;
  title: string;
  lead: string;
  url: string;
  sections: HelpSection[];
  /** Reference entries only - the folder under resources/help/reference. Null for pages. */
  category?: string | null;
  categoryLabel?: string | null;
  /**
   * Search terms declared in a page's `keywords:` frontmatter. Reference
   * entries have no frontmatter, so they never carry any.
   */
  keywords?: string[];
}

/** One result: a document, the section that matched (if any), and the url to open. */
export interface HelpHit {
  doc: HelpDoc;
  section: HelpSection | null;
  url: string;
}

/** What goes into the engine: one record per section, carrying its page's identity. */
interface SectionRecord {
  id: number;
  doc: number;
  sec: number;
  kind: string;
  /** The page title, on the intro record only. */
  title: string;
  /** The page title again, on every record, at low weight: context, not subject. */
  page: string;
  heading: string;
  lead: string;
  text: string;
  slug: string;
  keywords: string;
}

/**
 * Prose outranks a reference entry that matched equally well.
 *
 * There are ~145 reference entries against ~40 prose pages, all drawing on the
 * same vocabulary, so a question phrased in words tends to surface tags. A
 * tutorial answers the question directly and leads; a deep dive is a long read
 * about one overlay and sits between a guide and the reference.
 */
const KIND_BOOST: Record<string, number> = {
  tutorial: 1.5,
  guide: 1.25,
  'deep-dive': 1.1,
  reference: 1,
};

/**
 * Field boosts. A word in a title or a heading is what the section IS about;
 * a word in the text is something it mentions. Keywords are the author's own
 * statement of what a page is about, so they weigh like a title.
 *
 * `page` is the title repeated on every section at text weight. It has to be
 * there so "twitch controls" can reach a section of the Controls guide that
 * never says "controls" itself, and it has to be light: at title weight the
 * 45 sections of that guide were the entire answer to "controls" and the bot
 * commands page's own "Controls" heading ranked twelfth.
 */
const FIELD_BOOST: Record<string, number> = {
  title: 3,
  keywords: 3,
  heading: 2.5,
  slug: 2,
  lead: 1.5,
  text: 1,
  page: 1,
};

/**
 * The most sections one page may occupy in a result list. A 45-section guide
 * that says "control" in every one of them must not be the entire answer to
 * "controls" - the third-best section of it is worth less than the best
 * section of the next page.
 */
const MAX_HITS_PER_DOC = 3;

/**
 * Light English stemming, applied to the index and the query alike, so
 * "controls" finds "control" and "commands" finds "command". Deliberately
 * tiny: plurals are the one inflection that shows up in queries, and a full
 * stemmer would fold tag names into each other.
 */
export function stem(term: string): string {
  if (term.length > 4 && term.endsWith('ies')) return term.slice(0, -3) + 'y';
  if (term.length > 3 && term.endsWith('s') && !/(ss|us|is)$/.test(term)) return term.slice(0, -1);

  return term;
}

/**
 * Words that carry no meaning on their own and would otherwise decide a
 * query. "how do mods change controls" must mean "mods change controls": with
 * the filler kept, the all-words pass finds no section saying "how" and "do"
 * and "mods" and falls through to any-word, where "the" and "a" match
 * everything. Dropped from the index and the query alike.
 */
const STOP_WORDS = new Set([
  'a',
  'an',
  'and',
  'are',
  'as',
  'at',
  'be',
  'but',
  'by',
  'can',
  'do',
  'does',
  'for',
  'from',
  'how',
  'i',
  'in',
  'into',
  'is',
  'it',
  'its',
  'my',
  'of',
  'on',
  'or',
  'that',
  'the',
  'this',
  'to',
  'what',
  'when',
  'where',
  'which',
  'with',
  'you',
  'your',
]);

function processTerm(term: string): string | null {
  const t = term.toLowerCase();

  return STOP_WORDS.has(t) ? null : stem(t);
}

/**
 * Letters and digits are words; everything else is a boundary. The default
 * tokenizer splits on a fixed list of ASCII punctuation that leaves backticks
 * attached, so a word closing an inline code span was a different term from
 * the same word in prose and was unfindable. This also splits `latest_donor_name`
 * and `c:kofi:total` into their words, which is what a query types.
 */
function tokenize(text: string): string[] {
  return text.split(/[^\p{L}\p{N}]+/u).filter(Boolean);
}

/**
 * Typo tolerance for words long enough to have typos in. Short terms are tag
 * fragments and section names, where one edit is a different word entirely.
 */
function fuzzy(term: string): number | false {
  return term.length > 4 ? 0.2 : false;
}

function buildEngine(docs: HelpDoc[]): MiniSearch<SectionRecord> {
  const engine = new MiniSearch<SectionRecord>({
    fields: ['title', 'page', 'heading', 'lead', 'text', 'slug', 'keywords'],
    storeFields: ['doc', 'sec', 'kind'],
    tokenize,
    processTerm,
    searchOptions: {
      // Length normalisation turned down from the 0.7 default. BM25 rewards
      // a short document for containing the term at all, and a reference
      // entry's three-line "Controls" section was beating the bot commands
      // page's "Controls" section, the one with the table of commands in it.
      // Half-strength keeps short sections competitive without letting
      // brevity outrank substance.
      bm25: { k: 1.2, b: 0.35, d: 0.5 },
      prefix: true,
      fuzzy,
      boost: FIELD_BOOST,
      boostDocument: (_id, _term, stored) => KIND_BOOST[(stored?.kind as string) ?? ''] ?? 1,
    },
  });

  const records: SectionRecord[] = [];
  let id = 0;

  docs.forEach((doc, di) => {
    const sections = doc.sections.length > 0 ? doc.sections : [{ id: null, heading: '', text: '' }];

    sections.forEach((section, si) => {
      const intro = section.id === null;

      records.push({
        id: id++,
        doc: di,
        sec: si,
        kind: doc.kind,
        page: doc.title,
        heading: section.heading,
        // Page-level fields ride on the intro record only, so a page with
        // forty sections is not forty matches for its own title.
        title: intro ? doc.title : '',
        lead: intro ? doc.lead : '',
        slug: intro ? doc.slug : '',
        keywords: intro ? (doc.keywords ?? []).join(' ') : '',
        text: section.text,
      });
    });
  });

  engine.addAll(records);

  return engine;
}

function toHit(doc: HelpDoc, section: HelpSection | null): HelpHit {
  return {
    doc,
    section,
    url: section?.id ? `${doc.url}#${section.id}` : doc.url,
  };
}

function pageHit(doc: HelpDoc): HelpHit {
  return toHit(doc, null);
}

/**
 * Every word must match, then any word.
 *
 * "controls chat" is a question about one thing and the section answering it
 * says both words. Only when nothing does is the query treated as a bag of
 * alternatives, so a stray word never empties the list but never dilutes it
 * either.
 */
function rank(engine: MiniSearch<SectionRecord>, docs: HelpDoc[], query: string, limit: number): HelpHit[] {
  const options: SearchOptions[] = [{ combineWith: 'AND' }, { combineWith: 'OR' }];
  const perDoc = new Map<number, number>();
  const hits: HelpHit[] = [];

  for (const option of options) {
    for (const r of engine.search(query, option)) {
      const di = r.doc as number;
      const seen = perDoc.get(di) ?? 0;
      if (seen >= MAX_HITS_PER_DOC) continue;
      perDoc.set(di, seen + 1);

      const doc = docs[di];
      const section = doc.sections[r.sec as number] ?? null;
      hits.push(toHit(doc, section));

      if (hits.length >= limit) return hits;
    }

    if (hits.length > 0) return hits;
  }

  return hits;
}

/**
 * The shortest query that may name a section. Two characters is `id`, `at`,
 * `to` - words that happen to prefix a folder name are not a request for it.
 */
const MIN_SECTION_QUERY = 3;

/** Lowercase, punctuation collapsed to single spaces. `Foreach-Loops` -> `foreach loops`. */
function normalize(value: string | null | undefined): string {
  return (value ?? '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

/**
 * Everything filed under a reference folder the query names.
 *
 * Term search cannot answer "show me the foreach loops". The nine entries in
 * that folder are named `chat`, `goals`, `raw`, `subscribers` and so on: not one
 * of them contains the word. The sidebar knows these nine belong together and
 * search did not.
 *
 * Prefix match, on the whole name rather than its words: `foreach`, `eventsub`
 * and `integration` name a folder, `tags` and `controls` do not - those are
 * questions about tags and controls, and the guides answering them must keep
 * winning. Punctuation is ignored, so the `Foreach Loops` button on the
 * reference index and someone typing `foreach-loops` land in the same place.
 */
export function sectionMatch(docs: HelpDoc[], query: string): HelpDoc[] {
  const q = normalize(query);

  if (q.length < MIN_SECTION_QUERY) return [];

  return docs.filter((d) => d.category != null && (normalize(d.categoryLabel).startsWith(q) || normalize(d.category).startsWith(q)));
}

/**
 * The shortest query that may be a partial keyword. Two characters prefixes far
 * too much to be a request for anything - `co` opens both `controls` and
 * `codemirror`. An exact match is exempt: if someone declared `if` as a
 * keyword, typing `if` is unambiguous.
 */
const MIN_KEYWORD_PREFIX = 3;

export interface KeywordMatches {
  /** The query IS one of the declared keywords. */
  exact: HelpDoc[];
  /** The query prefixes a declared keyword, or a word inside one. */
  partial: HelpDoc[];
}

/**
 * Pages whose author declared this query as a keyword.
 *
 * Keywords are also an indexed field, so they take part in ordinary ranking
 * and in multi-word queries. This pass is the stronger claim on top of that:
 * an exact match is the author saying this page IS about that word, and it
 * leads the list ahead of anything the engine ranked - "bang" opens the editor
 * guide, not `user_offline_banner`. A prefix is a hint, so it appends instead.
 *
 * Prefix, not substring, and on word boundaries: `autocom` finds `autocomplete`
 * and `snip` finds `bang snippets`, while `ang` matches neither. Mid-word
 * matching turns every short query into noise.
 */
export function keywordMatch(docs: HelpDoc[], query: string): KeywordMatches {
  const q = normalize(query);
  const exact: HelpDoc[] = [];
  const partial: HelpDoc[] = [];

  if (!q) return { exact, partial };

  for (const doc of docs) {
    const terms = (doc.keywords ?? []).map(normalize).filter(Boolean);

    if (terms.length === 0) continue;

    if (terms.includes(q)) {
      exact.push(doc);
      continue;
    }

    if (q.length < MIN_KEYWORD_PREFIX) continue;

    if (terms.some((t) => t.startsWith(q) || t.split(' ').some((w) => w.startsWith(q)))) {
      partial.push(doc);
    }
  }

  return { exact, partial };
}

/**
 * The pile a result belongs to, as shown beside its title.
 *
 * A reference entry names its folder - "Foreach Loops", not the word
 * "Reference". Most of the corpus is reference entries, so "Reference"
 * distinguishes nothing, and after searching for a folder it is the one thing
 * confirming the results ARE the folder you asked for.
 */
export function docLabel(doc: HelpDoc): string {
  return doc.categoryLabel || doc.kindLabel;
}

/**
 * The text to preview under a result: the matched section, biased towards the
 * first occurrence of the query, or the page's lead when nothing narrower
 * matched. Markdown noise that reads badly in one line is stripped.
 */
export function snippet(hit: HelpHit, query: string, length = 140): string {
  const source = hit.section?.text || hit.doc.lead || hit.doc.sections[0]?.text || '';
  const text = source
    .replace(/^#+\s+.*$/gm, '')
    .replace(/^\s*\|?\s*-{3,}.*$/gm, '')
    .replace(/[`*|>]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  const q = query.trim().toLowerCase();
  if (q.length >= 2) {
    const idx = text.toLowerCase().indexOf(q);
    if (idx > 30) {
      const start = Math.max(0, idx - 30);

      return '...' + text.slice(start, start + length) + (text.length > start + length ? '...' : '');
    }
  }

  return text.slice(0, length) + (text.length > length ? '...' : '');
}

export interface HelpSearch {
  /** Every document, in corpus order. */
  all: HelpDoc[];
  search(query: string, limit: number): HelpHit[];
}

/** First occurrence of each url wins, so earlier passes keep their position. */
function dedupe(hits: HelpHit[]): HelpHit[] {
  const seen = new Set<string>();

  return hits.filter((h) => {
    if (seen.has(h.url)) return false;
    seen.add(h.url);

    return true;
  });
}

/**
 * Declared keywords, then ranked sections, then partial keywords, then the
 * rest of any reference folder the query named.
 *
 * That order matters at both ends. A page whose author declared the query as a
 * keyword leads, because that is a deliberate statement about what the page is.
 * At the other end, "template tags" has one genuine hit - the page listing all
 * of them - and it must stay on top of the 64 individual entries that follow.
 *
 * A partial keyword sits behind the ranked hits rather than in front: it is a
 * hint, not a claim, and it must never displace something that matched outright.
 */
export function buildHelpSearch(docs: HelpDoc[]): HelpSearch {
  const engine = buildEngine(docs);

  return {
    all: docs,
    search(query: string, limit: number): HelpHit[] {
      const q = query.trim();

      if (!q) return docs.slice(0, limit).map(pageHit);

      const keywords = keywordMatch(docs, q);

      return dedupe([
        ...keywords.exact.map(pageHit),
        ...rank(engine, docs, q, limit),
        ...keywords.partial.map(pageHit),
        ...sectionMatch(docs, q).map(pageHit),
      ]).slice(0, limit);
    },
  };
}
