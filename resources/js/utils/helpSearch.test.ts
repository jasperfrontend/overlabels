import { describe, expect, it } from 'vitest';
import { buildHelpSearch, docLabel, keywordMatch, sectionMatch, snippet, stem, type HelpDoc, type HelpSection } from './helpSearch';

/**
 * A stand-in for the real corpus, shaped like it.
 *
 * The bot commands page is the case the whole rewrite exists for: the word
 * that answers the question is in a table under a heading, wrapped in
 * backticks, on a page whose title and lead never say it. The reference
 * entries are a handful whose titles never contain their folder's name, plus
 * the guides and tutorials they compete with.
 */
function ref(slug: string, category: string, categoryLabel: string, text = ''): HelpDoc {
  return {
    kind: 'reference',
    kindLabel: 'Reference',
    slug,
    title: slug,
    lead: '',
    url: `/help/reference/${category}/${slug}`,
    sections: [{ id: null, heading: '', text }],
    category,
    categoryLabel,
  };
}

function page(slug: string, kind: 'guide' | 'tutorial', title: string, lead = '', sections: HelpSection[] = [], keywords: string[] = []): HelpDoc {
  return {
    kind,
    kindLabel: kind === 'guide' ? 'Guide' : 'Tutorial',
    slug,
    title,
    lead,
    url: kind === 'tutorial' ? `/help/tutorials/${slug}` : `/help/${slug}`,
    sections: [{ id: null, heading: '', text: '' }, ...sections],
    category: null,
    categoryLabel: null,
    keywords,
  };
}

const COMMANDS_CONTROLS = `Read and write your overlay controls from chat. The bot only ever touches controls you created
yourself. Requires \`!enablecontrols\` on your channel.

| Command | Tier | What it does |
|---|---|---|
| \`!enablecontrols\` | Broadcaster | Open the controls surface on this channel. Default is closed. |
| \`!disablecontrols\` | Broadcaster | Close it again. |
| \`!setcontrol key value\` | Moderator | Write a value. |`;

const DOCS: HelpDoc[] = [
  page('chat', 'guide', 'Chat', 'Show live Twitch chat in an overlay.', [
    { id: 'shared-chat', heading: 'Shared Chat', text: 'Messages from a collab partner carry a source channel.' },
  ]),
  page('bot/commands', 'guide', 'Bot Commands', 'Every chat command the bot understands.', [
    { id: 'controls', heading: 'Controls', text: COMMANDS_CONTROLS },
    { id: 'permission-tiers', heading: 'Permission tiers', text: 'Broadcaster, moderator, VIP, subscriber, everyone.' },
  ]),
  // Shaped like the real editor guide: the words people search for are in the
  // keywords, and nowhere in the title or lead.
  page(
    'editor',
    'guide',
    'The code editor',
    'Type [[[ and the editor offers every tag you can use.',
    [],
    ['autocomplete', 'bang snippets', 'codemirror'],
  ),
  page('controls', 'guide', 'Controls', 'Values you can change without editing the template.', [
    { id: 'creating-a-control', heading: 'Creating a Control', text: 'Open the control panel and add a control.' },
    { id: 'editing-a-control', heading: 'Editing a Control', text: 'A control keeps its key when edited.' },
    { id: 'deleting-a-control', heading: 'Deleting a Control', text: 'Deleting a control removes it from every template.' },
    { id: 'control-types', heading: 'Control types', text: 'Text, number, counter, timer, boolean, datetime, expression.' },
    { id: 'controls-in-css', heading: 'Controls in CSS', text: 'A control value works inside a style block too.' },
  ]),
  page('show-chat-on-screen', 'tutorial', 'Show chat on screen', 'A chat feed in ten lines.', [
    { id: 'styling', heading: 'Styling one kind of chatter', text: 'Subscribers get a class.' },
  ]),
  ref('chat', 'foreach-loops', 'Foreach Loops', 'chat.0.author, chat.0.text: the newest messages in the room.'),
  ref('goals', 'foreach-loops', 'Foreach Loops', 'active goals'),
  ref('raw', 'foreach-loops', 'Foreach Loops', 'the raw payload'),
  ref('channel_name', 'template-tags', 'Template Tags', 'the channel login'),
  ref('channel_game', 'template-tags', 'Template Tags', 'the category being streamed'),
  ref('kofi', 'integration-controls', 'Integration Controls', 'donations_received, latest_donor_name'),
  ref('stream-goes-offline', 'eventsub-events', 'EventSub Events', 'stream.offline'),
];

const search = buildHelpSearch(DOCS);
const urls = (q: string, limit = 10) => search.search(q, limit).map((h) => h.url);

describe('ranking', () => {
  it('finds a section by a word that appears only in its body, inside inline code', () => {
    // The stream-night case. Nothing but the table under the Controls heading
    // says this word, and it says it wrapped in backticks.
    expect(urls('enablecontrols')[0]).toBe('/help/bot/commands#controls');
  });

  it('treats a leading bang as punctuation, not as part of the word', () => {
    expect(urls('!enablecontrols')[0]).toBe('/help/bot/commands#controls');
  });

  it('requires every word of a multi-word query to match', () => {
    // Only one section says both. The chat guide never says "control" and the
    // controls guide never says "chat", and neither may appear.
    const hits = urls('controls chat');

    expect(hits[0]).toBe('/help/bot/commands#controls');
    expect(hits).not.toContain('/help/chat');
    expect(hits).not.toContain('/help/controls');
  });

  it('falls back to any word when nothing says all of them', () => {
    const hits = urls('controls zebra');

    expect(hits.length).toBeGreaterThan(0);
    expect(hits).toContain('/help/controls');
  });

  it('lets filler words neither decide nor empty a query', () => {
    // With the filler kept, no section says "how" and "do" and "i", the
    // all-words pass finds nothing, and the any-word pass hands the lead to
    // whichever page says "from" and "in" most. Without it the query is
    // "enable controls chat", and one section says all three.
    expect(urls('how do I enable controls in chat')[0]).toBe('/help/bot/commands#controls');
  });

  it('folds plural and singular into one word', () => {
    expect(urls('control')).toContain('/help/bot/commands#controls');
    expect(urls('command')).toContain('/help/bot/commands');
  });

  it('survives a typo of a letter or two in a long word', () => {
    expect(urls('enabelcontrols')[0]).toBe('/help/bot/commands#controls');
  });

  it('answers a dotted tag name with the loop it belongs to', () => {
    // Someone pasting `chat.0.text` from a template is asking about the loop.
    // The tokenizer splits on the dots, so the root is a term like any other.
    expect(urls('chat.0.text')).toContain('/help/reference/foreach-loops/chat');
  });

  it('links a section result to its anchor and a page result to the page', () => {
    const [section] = search.search('enablecontrols', 1);
    const [intro] = search.search('code editor', 1);

    expect(section.section?.id).toBe('controls');
    expect(section.url).toBe('/help/bot/commands#controls');
    expect(intro.doc.slug).toBe('editor');
    expect(intro.section?.id ?? null).toBeNull();
    expect(intro.url).toBe('/help/editor');
  });

  it('lets one page occupy at most three slots', () => {
    // Five sections of the controls guide say "control". The third-best of
    // them is worth less than the best section of the next page.
    const hits = search.search('control', 20).filter((h) => h.doc.slug === 'controls');

    expect(hits.length).toBeLessThanOrEqual(3);
  });

  it('puts a page whose title is the query ahead of pages that mention it', () => {
    expect(urls('controls')[0]).toBe('/help/controls');
  });

  it('returns each url at most once', () => {
    const hits = urls('chat', 50);

    expect(new Set(hits).size).toBe(hits.length);
  });

  it('honours the limit', () => {
    expect(search.search('control', 2)).toHaveLength(2);
  });

  it('lists the corpus for an empty query', () => {
    expect(search.search('', 3).map((h) => h.doc.slug)).toEqual(['chat', 'bot/commands', 'editor']);
  });
});

describe('stem', () => {
  it('drops a plural s and turns ies into y', () => {
    expect(stem('controls')).toBe('control');
    expect(stem('commands')).toBe('command');
    expect(stem('entries')).toBe('entry');
  });

  it('leaves words that only look plural alone', () => {
    expect(stem('bonus')).toBe('bonus');
    expect(stem('class')).toBe('class');
    expect(stem('this')).toBe('this');
    expect(stem('bus')).toBe('bus');
  });
});

describe('sectionMatch', () => {
  it('returns a whole folder for the word that names it, even though no entry contains that word', () => {
    expect(sectionMatch(DOCS, 'foreach').map((d) => d.slug)).toEqual(['chat', 'goals', 'raw']);
  });

  it('matches the label the reference index puts on its category cards', () => {
    expect(sectionMatch(DOCS, 'Foreach Loops').map((d) => d.slug)).toEqual(['chat', 'goals', 'raw']);
    expect(sectionMatch(DOCS, 'EventSub Events').map((d) => d.slug)).toEqual(['stream-goes-offline']);
  });

  it('ignores punctuation, so the slug and the label are the same query', () => {
    expect(sectionMatch(DOCS, 'foreach-loops')).toEqual(sectionMatch(DOCS, 'Foreach Loops'));
  });

  it('matches a prefix of the name but not a word inside it', () => {
    expect(sectionMatch(DOCS, 'integ').map((d) => d.slug)).toEqual(['kofi']);
    // "tags" and "controls" are questions about tags and controls, not a
    // request for a folder. The guides answering them must keep winning.
    expect(sectionMatch(DOCS, 'tags')).toEqual([]);
    expect(sectionMatch(DOCS, 'controls')).toEqual([]);
  });

  it('ignores queries too short to be naming a section', () => {
    expect(sectionMatch(DOCS, 'fo')).toEqual([]);
  });

  it('never matches a page, which has no folder', () => {
    expect(sectionMatch(DOCS, 'chat').map((d) => d.kind)).not.toContain('guide');
  });
});

describe('keywordMatch', () => {
  it('finds a page by a word that only its keywords declare', () => {
    expect(keywordMatch(DOCS, 'autocomplete').exact.map((d) => d.slug)).toEqual(['editor']);
  });

  it('matches a partial of a keyword, and separately from an exact one', () => {
    expect(keywordMatch(DOCS, 'autocom')).toEqual({ exact: [], partial: [DOCS[2]] });
  });

  it('matches a word inside a multi-word keyword', () => {
    expect(keywordMatch(DOCS, 'snip').partial.map((d) => d.slug)).toEqual(['editor']);
  });

  it('prefixes only, so a fragment from the middle of a keyword matches nothing', () => {
    expect(keywordMatch(DOCS, 'ang')).toEqual({ exact: [], partial: [] });
  });

  it('ignores a partial too short to be asking for anything', () => {
    expect(keywordMatch(DOCS, 'co').partial).toEqual([]);
  });

  it('still honours an exact match at any length, since exact is unambiguous', () => {
    const docs = [page('conditionals', 'guide', 'Conditionals', '', [], ['if'])];

    expect(keywordMatch(docs, 'if').exact.map((d) => d.slug)).toEqual(['conditionals']);
  });

  it('ignores punctuation and case, like the section pass', () => {
    expect(keywordMatch(DOCS, 'Bang-Snippets').exact.map((d) => d.slug)).toEqual(['editor']);
  });

  it('never matches a document with no keywords declared', () => {
    expect(keywordMatch(DOCS, 'chat')).toEqual({ exact: [], partial: [] });
  });
});

describe('composition', () => {
  it('answers a section query with the section', () => {
    expect(urls('foreach')).toEqual([
      '/help/reference/foreach-loops/chat',
      '/help/reference/foreach-loops/goals',
      '/help/reference/foreach-loops/raw',
    ]);
  });

  it('keeps ranked matches ahead of the rest of the section', () => {
    // The chat entry matches "eventsub events"? No. But "eventsub" ranks the
    // folder alone; a query that also matches a page keeps the page first.
    const hits = urls('event');

    expect(hits[hits.length - 1]).toBe('/help/reference/eventsub-events/stream-goes-offline');
  });

  it('puts an exact keyword ahead of anything the engine ranked', () => {
    // "bang" is declared on the editor guide. Nothing else says it, but if a
    // heading somewhere started with "bang" the declared page still leads.
    expect(urls('bang')[0]).toBe('/help/editor');
  });

  it('keeps a partial keyword behind anything that matched outright', () => {
    // "code" matches the editor's title outright and prefixes "codemirror".
    // The intro hit comes from ranking; the partial pass must not double it
    // or lift it above a better-ranked page.
    const hits = urls('code');

    expect(hits).toContain('/help/editor');
    expect(new Set(hits).size).toBe(hits.length);
  });

  it('answers a keyword query the engine also finds, once', () => {
    expect(urls('autocomplete')).toEqual(['/help/editor']);
  });
});

describe('snippet', () => {
  it('previews the matched section, cut around the first occurrence of the query', () => {
    const [hit] = search.search('enablecontrols', 1);
    const text = snippet(hit, 'enablecontrols');

    expect(text).toContain('!enablecontrols');
    expect(text.startsWith('...')).toBe(true);
    expect(text).not.toContain('|');
    expect(text).not.toContain('`');
  });

  it('falls back to the lead for a page hit', () => {
    const [hit] = search.search('code editor', 1);

    expect(snippet(hit, 'code editor')).toBe('Type [[[ and the editor offers every tag you can use.');
  });

  it('drops a markdown table rule line', () => {
    const [hit] = search.search('enablecontrols', 1);

    expect(snippet(hit, 'zzz', 400)).not.toContain('---');
  });
});

describe('docLabel', () => {
  it('names the folder for a reference entry, not the word Reference', () => {
    expect(docLabel(DOCS[5])).toBe('Foreach Loops');
  });

  it('falls back to the kind for a page, which has no folder', () => {
    expect(docLabel(DOCS[0])).toBe('Guide');
  });
});
