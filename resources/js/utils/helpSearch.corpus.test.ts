import { existsSync, readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';
import { buildHelpSearch, type HelpDoc } from './helpSearch';

/**
 * The real corpus, when it is there.
 *
 * `public/help-index.json` is built by `php artisan help:build-index` and is
 * not committed, so this file runs on every local gate (the index is rebuilt
 * on composer install and by the help tests) and skips in CI, where `npm test`
 * runs before composer. The fixture tests in helpSearch.test.ts are the ones
 * CI relies on; these are the ones that catch a corpus edit quietly undoing a
 * ranking that mattered.
 */
const INDEX = fileURLToPath(new URL('../../../public/help-index.json', import.meta.url));
const present = existsSync(INDEX);

describe.skipIf(!present)('help search against the real corpus', () => {
  const docs: HelpDoc[] = present ? JSON.parse(readFileSync(INDEX, 'utf8')) : [];
  const search = buildHelpSearch(docs);
  const slugsFor = (q: string, limit: number) => search.search(q, limit).map((h) => h.doc.slug);

  it('finds the bot commands page for each of the queries tried on stream', () => {
    // 2026-09-07, live, trying to explain how controls are changed from chat.
    // Every one of these returned either nothing or a list without the page,
    // and "enablecontrols" is the word printed three times on it.
    for (const q of ['controls', 'chat', 'controls chat', 'enablecontrols', 'twitch controls', 'bot controls']) {
      expect(slugsFor(q, 15), `query "${q}"`).toContain('bot/commands');
    }
  });

  it('lands "enablecontrols" on the section that documents it', () => {
    const [hit] = search.search('enablecontrols', 1);

    expect(hit.url).toMatch(/^\/help\/bot\/commands#controls/);
  });

  it('keeps the tuned answers from before the rewrite', () => {
    expect(slugsFor('foreach', 40)).toContain('chat');
    expect(slugsFor('kofi', 5)).toContain('kofi');
    // The old engine also answered "raid" with the Random Rolls guide, and
    // that was recorded as tuned behaviour. The guide never says "raid":
    // it was a one-edit fuzzy match on "rand". Not kept.
    expect(slugsFor('raid', 5)).toContain('raid-received');
    expect(slugsFor('autocomplete', 3)).toContain('editor');
    expect(slugsFor('bang', 1)).toEqual(['editor']);
    expect(slugsFor('latest donator', 1)).toEqual(['tutorials/latest-donator']);
    expect(slugsFor('template tags', 1)).toEqual(['all-overlabels-static-template-tags']);
  });

  it('only links to anchors the page has', () => {
    for (const q of ['controls', 'chat', 'follower', 'title', 'counter']) {
      for (const hit of search.search(q, 40)) {
        if (!hit.section?.id) continue;

        expect(hit.doc.sections.map((s) => s.id)).toContain(hit.section.id);
        expect(hit.url).toBe(`${hit.doc.url}#${hit.section.id}`);
      }
    }
  });
});
