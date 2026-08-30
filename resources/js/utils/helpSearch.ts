import Fuse, { type IFuseOptions } from 'fuse.js';

/**
 * One search behaviour for the whole documentation site.
 *
 * There are two surfaces - the Alt+R palette inside the app and the search box
 * on every help page - and they must rank identically, or "search the docs"
 * means two different things depending on where you are standing. Both import
 * this.
 */
export interface HelpDoc {
  kind: string;
  kindLabel: string;
  slug: string;
  title: string;
  lead: string;
  url: string;
  body: string;
  /** Reference entries only - the folder under resources/help/reference. Null for pages. */
  category?: string | null;
  categoryLabel?: string | null;
  /**
   * Search terms declared in a page's `keywords:` frontmatter. Reference
   * entries have no frontmatter, so they never carry any.
   */
  keywords?: string[];
}

export const FUSE_OPTIONS: IFuseOptions<HelpDoc> = {
  keys: [
    { name: 'title', weight: 2 },
    { name: 'slug', weight: 2 },
    { name: 'lead', weight: 1 },
    { name: 'kindLabel', weight: 0.3 },
    // Deliberately light. A guide body is ~20 KB of prose that mentions most of
    // the vocabulary in the product, so at weight 1 every long page matched
    // every query weakly and drowned the entries that actually answered it.
    { name: 'body', weight: 0.5 },
  ],
  threshold: 0.35,
  ignoreLocation: true,
  minMatchCharLength: 2,
  includeScore: true,
};

/**
 * Drop coincidental matches.
 *
 * Measured against the real corpus, the scores are strongly bimodal: genuine
 * matches land under 0.15 (0.13 for the tutorial on "latest donator", 0.10 for
 * the hype train entries on "hype train") and coincidence starts at 0.78. There
 * is nothing at all in between, so this sits in the middle of a wide empty band
 * rather than on a cliff edge.
 *
 * This is what makes "Nothing matched" honest. Before it, `chat.0.text` scored
 * 0.996 against the chat tutorial and returned it as though it were an answer.
 */
const SCORE_CUTOFF = 0.5;

/**
 * Prose outranks a reference entry that matched equally well.
 *
 * There are 143 reference entries against 29 prose pages, all drawing on the
 * same vocabulary, so a question phrased in words tends to surface tags. Fuse
 * scores 0 as perfect and 1 as worst, so a multiplier below 1 promotes.
 *
 * This changes 3 of 15 sample queries - "follower" leads with the tutorial
 * instead of the `channel_followers` tag, "controls" surfaces Expression
 * Controls, "raid" surfaces Random Rolls and Counters - and leaves exact tag
 * lookups alone, since those score near 0 and stay near 0 when scaled.
 */
const KIND_WEIGHT: Record<string, number> = {
  tutorial: 0.5,
  guide: 0.7,
  // A deep dive is a long read about one overlay: promoted like a guide, never
  // above the tutorial that answers the question directly.
  'deep-dive': 0.7,
  reference: 1,
};

function rank(fuse: Fuse<HelpDoc>, query: string, limit: number): HelpDoc[] {
  return fuse
    .search(query, { limit: limit * 3 })
    .filter((r) => (r.score ?? 1) <= SCORE_CUTOFF)
    .map((r) => ({
      item: r.item,
      score: (r.score ?? 1) * (KIND_WEIGHT[r.item.kind] ?? 1),
    }))
    .sort((a, b) => a.score - b.score)
    .slice(0, limit)
    .map((r) => r.item);
}

export function rankedSearch(fuse: Fuse<HelpDoc>, query: string, limit: number): HelpDoc[] {
  const hits = rank(fuse, query, limit);

  if (hits.length > 0) return hits;

  /*
   * Fall back to the root of a dotted name.
   *
   * Tag names are hierarchical and the reference documents the root: there is
   * an entry for `chat`, none for `chat.0.text`. Someone pasting a tag they saw
   * in a template is asking about the loop it belongs to, and answering
   * "Nothing matched" to a tag that plainly exists reads as the search being
   * broken rather than the query being too specific.
   *
   * Only on an empty result, so it can never displace a real match.
   */
  const root = query.trim().split('.')[0];

  return root && root !== query.trim() ? rank(fuse, root, limit) : hits;
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
 * Everything filed under a section the query names.
 *
 * Fuzzy search cannot answer "show me the foreach loops". The nine entries in
 * that folder are named `chat`, `goals`, `raw`, `subscribers` and so on: not one
 * of them contains the word, so a search for it scored 0.55 against the closest
 * thing in the corpus and the cutoff correctly threw it away. The sidebar knows
 * these nine belong together and search did not.
 *
 * Deliberately NOT solved by adding `category` to FUSE_OPTIONS. Fuse normalises
 * a document's score across all its keys, so a sixth key moves every score in
 * the corpus: measured against the real index it dropped `bot/random-and-counters`
 * from "raid" and `all-ko-fi-events` from "kofi", both of which are the tuned
 * behaviour the weights above exist to produce. This runs alongside the fuzzy
 * pass instead of inside it, so ranking is bit-for-bit what it was.
 *
 * Prefix match, on the whole name rather than its words: `foreach`, `eventsub`
 * and `integration` name a section, `tags` and `controls` do not - those are
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
 * This exists because fuzzy search cannot see into a long body. Fuse applies a
 * field norm, so an identical exact match scores 0.0 in a short field and 0.89
 * in a 20KB one - above the cutoff that exists to throw coincidence away. The
 * editor guide says `autocomplete` five times, has it as a heading, and scored
 * 0.98 for that word: the search answered "Nothing matched" about a page that
 * is largely about it. Weighting `body` higher cannot fix that, because the
 * norm scales with length no matter what the weight is.
 *
 * Deliberately NOT a sixth key in FUSE_OPTIONS, for the same reason `category`
 * is not one. Fuse normalises a document's score across all its keys, so an
 * extra key moves every score in the corpus: measured against the real index,
 * adding one at weight 1.5 dropped `all-ko-fi-events` from "kofi" and
 * `bot/random-and-counters` from "raid" - the exact regressions the weights
 * above were tuned to avoid. This runs alongside the fuzzy pass, so ranking is
 * bit-for-bit what it was and a page without keywords behaves identically.
 *
 * Split into two tiers because they are different claims. An exact match is the
 * author saying this page IS about that word, which beats a fuzzy match on a
 * word that merely looks similar - "bang" should open the editor guide, not
 * `user_offline_banner`. A prefix is a hint, so it appends instead.
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
 * "Reference". 146 of the 175 documents are reference entries, so "Reference"
 * distinguishes nothing, and after searching for a section it is the one thing
 * confirming the results ARE the section you asked for. The reference sidebar
 * showed this before the three help surfaces were merged onto one search.
 */
export function docLabel(doc: HelpDoc): string {
  return doc.categoryLabel || doc.kindLabel;
}

export interface HelpSearch {
  /** Every document, in corpus order. */
  all: HelpDoc[];
  search(query: string, limit: number): HelpDoc[];
}

/** First occurrence of each url wins, so earlier passes keep their position. */
function dedupe(docs: HelpDoc[]): HelpDoc[] {
  const seen = new Set<string>();

  return docs.filter((d) => {
    if (seen.has(d.url)) return false;
    seen.add(d.url);

    return true;
  });
}

/**
 * Declared keywords, then ranked matches, then partial keywords, then the rest
 * of any section the query named.
 *
 * That order matters at both ends. A page whose author declared the query as a
 * keyword leads, because that is a deliberate statement about what the page is
 * and it beats a fuzzy match on a word that merely looks similar. At the other
 * end, "template tags" has one genuine hit - the page listing all of them - and
 * it must stay on top of the 64 individual entries that follow it.
 *
 * A partial keyword sits behind the fuzzy hits rather than in front: it is a
 * hint, not a claim, and it must never displace something that matched outright.
 */
export function buildHelpSearch(docs: HelpDoc[]): HelpSearch {
  const fuse = new Fuse(docs, FUSE_OPTIONS);

  return {
    all: docs,
    search(query: string, limit: number): HelpDoc[] {
      const q = query.trim();

      if (!q) return docs.slice(0, limit);

      const keywords = keywordMatch(docs, q);

      return dedupe([...keywords.exact, ...rankedSearch(fuse, q, limit), ...keywords.partial, ...sectionMatch(docs, q)]).slice(0, limit);
    },
  };
}
