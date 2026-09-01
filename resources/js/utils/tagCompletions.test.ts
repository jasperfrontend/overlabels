import { describe, expect, it } from 'vitest';
import { bangPrefix, bangSnippets, controlTagKey, foreachScopes, suggest, type CompletionData } from './tagCompletions';

/**
 * The completion source is context-sensitive: which options appear depends on
 * what the cursor sits after, and which template is being edited. These pin
 * the contexts, the per-account data (controls, Lists), and the alert-only
 * gate on `event.*`.
 */

const data: CompletionData = {
  tags: [
    { tag_name: 'followers_total', description: 'Total followers', data_type: 'integer', category: 'Followers' },
    { tag_name: 'channel_title', description: 'Stream title', data_type: 'string', category: 'Channel Information' },
  ],
  eventTags: ['event.user_name', 'event.bits'],
  controls: [
    { key: 'round_timer', label: 'Round timer', source: null, type: 'timer' },
    { key: 'donations_received', label: 'Ko-fi Donations Received', source: 'kofi', type: 'counter' },
  ],
  lists: [{ slug: 'donors', label: 'Donors' }],
  templateType: 'static',
};

function labels(docBefore: string, d: CompletionData = data): string[] {
  return suggest(docBefore, d)?.options.map((o) => o.label) ?? [];
}

describe('context detection', () => {
  it('returns nothing outside an opening [[[', () => {
    expect(suggest('<div class="foo">fol', data)).toBeNull();
    expect(suggest('[[[followers_total]]] and then', data)).toBeNull();
    expect(suggest('[[ fol', data)).toBeNull();
  });

  it('replaces from just after [[[ so the typed prefix is what gets filtered', () => {
    const result = suggest('<p>[[[fol', data);
    expect(result?.kind).toBe('tag');
    expect(result?.from).toBe('<p>[[['.length);
  });

  it('only looks at the current line', () => {
    expect(suggest('[[[fol\n<span>', data)).toBeNull();
    expect(suggest('<span>\n[[[fol', data)?.from).toBe('<span>\n[[['.length);
  });

  it('offers formatters after a pipe', () => {
    const result = suggest('[[[followers_total|nu', data);
    expect(result?.kind).toBe('formatter');
    expect(result?.from).toBe('[[[followers_total|'.length);
    expect(result?.options.map((o) => o.label)).toEqual(expect.arrayContaining(['number', 'currency', 'duration', 'uppercase']));
    expect(result?.options.map((o) => o.label)).not.toContain('followers_total');
  });

  it('offers iterables after foreach:, applied with a proposed alias', () => {
    const result = suggest('[[[foreach:', data);
    expect(result?.kind).toBe('iterable');
    const subs = result?.options.find((o) => o.label === 'subscribers');
    expect(subs?.apply).toBe('subscribers as sub');
    expect(result?.options.map((o) => o.label)).toEqual(expect.arrayContaining(['chat', 'channel_followers', 'goals', 'c:list:donors']));
    expect(result?.options.find((o) => o.label === 'c:list:donors')?.apply).toBe('c:list:donors as item');
  });
});

describe('what a bare [[[ offers', () => {
  it('includes the static catalogue with its category as the section', () => {
    const result = suggest('[[[', data);
    const tag = result?.options.find((o) => o.label === 'followers_total');
    expect(tag?.section).toBe('Followers');
    expect(tag?.detail).toBe('integer');
    expect(tag?.closes).toBe(true);
  });

  it('includes controls by their broadcast key, grouped by service', () => {
    const result = suggest('[[[', data);
    expect(result?.options.find((o) => o.label === 'c:round_timer')?.section).toBe('Controls');
    expect(result?.options.find((o) => o.label === 'c:kofi:donations_received')?.section).toBe('Ko-fi controls');
  });

  it('includes every List projection', () => {
    expect(labels('[[[c:li')).toEqual(
      expect.arrayContaining(['c:list:donors', 'c:list:donors:count', 'c:list:donors:first', 'c:list:donors:last', 'c:list:donors:empty']),
    );
  });

  it('includes block keywords, leaving the cursor inside for the ones that take a condition', () => {
    const result = suggest('[[[', data);
    expect(result?.options.find((o) => o.label === 'if:')?.closes).toBe(false);
    expect(result?.options.find((o) => o.label === 'foreach:')?.closes).toBe(false);
    expect(result?.options.find((o) => o.label === 'endif')?.closes).toBe(true);
    expect(result?.options.find((o) => o.label === 'endforeach')?.closes).toBe(true);
  });

  it('drops block keywords after if: and elseif:, keeping value tags', () => {
    for (const prefix of ['[[[if:', '[[[elseif:fol']) {
      const found = labels(prefix);
      expect(found).not.toContain('endif');
      expect(found).not.toContain('foreach:');
      expect(found).toContain('followers_total');
      expect(found).toContain('c:kofi:donations_received');
    }
    expect(suggest('[[[if:fol', data)?.from).toBe('[[[if:'.length);
  });

  it('hides loop tags outside a foreach body', () => {
    expect(labels('[[[')).not.toContain('loop.index');
    expect(labels('[[[')).not.toContain('raw');
  });
});

describe('event tags', () => {
  it('are offered on alert templates only', () => {
    expect(labels('[[[ev')).not.toContain('event.user_name');
    expect(labels('[[[ev', { ...data, templateType: 'alert' })).toContain('event.user_name');
  });

  it('gate the event iterables the same way', () => {
    expect(labels('[[[foreach:')).not.toContain('event.choices');
    expect(labels('[[[foreach:', { ...data, templateType: 'alert' })).toContain('event.choices');
  });
});

describe('foreach scope', () => {
  it('tracks open loops, innermost last, and closes them on endforeach', () => {
    const open = '[[[foreach:subscribers as sub]]]\n<div>[[[foreach:chat as msg]]]';
    expect(foreachScopes(open)).toEqual([
      { iterable: 'subscribers', alias: 'sub' },
      { iterable: 'chat', alias: 'msg' },
    ]);
    expect(foreachScopes(open + '[[[endforeach]]]')).toEqual([{ iterable: 'subscribers', alias: 'sub' }]);
    expect(foreachScopes(open + '[[[endforeach]]][[[endforeach]]]')).toEqual([]);
  });

  it('offers alias.field for the iterable in scope, plus loop tags', () => {
    const found = labels('[[[foreach:subscribers as sub]]]\n  <div>[[[su');
    expect(found).toEqual(expect.arrayContaining(['sub.user_name', 'sub.tier', 'sub.is_gift', 'loop.index', 'loop.last', 'raw']));
  });

  it('knows the chat message fields under whatever alias was chosen', () => {
    const found = labels('[[[foreach:chat as m]]][[[');
    expect(found).toEqual(expect.arrayContaining(['m.author', 'm.html', 'm.badge_images', 'm.source_channel']));
  });

  it('offers the bare alias and the item fields for a List loop', () => {
    const found = labels('[[[foreach:c:list:donors as donor]]][[[d');
    expect(found).toEqual(expect.arrayContaining(['donor', 'donor.value', 'donor.added_at', 'donor.id']));
  });

  it('forgets the alias once the loop is closed', () => {
    const found = labels('[[[foreach:subscribers as sub]]][[[sub.user_name]]][[[endforeach]]]\n[[[su');
    expect(found).not.toContain('sub.user_name');
    expect(found).not.toContain('loop.index');
  });
});

describe('bang snippets', () => {
  it('always offers the block and loop snippets', () => {
    const labels = bangSnippets({ ...data, controls: [] }).map((s) => s.label);
    expect(labels).toEqual(['!chat', '!subs', '!followers', '!goals', '!checkins', '!followed', '!if', '!ifelse', '!foreach']);
  });

  it('covers every iterable a static overlay can loop over', () => {
    const templates = bangSnippets({ ...data, controls: [] }).map((s) => s.template);
    for (const iterable of ['chat', 'subscribers', 'channel_followers', 'followed_channels', 'goals', 'checkins']) {
      expect(
        templates.some((t) => t.includes(`[[[foreach:${iterable} as `)),
        iterable,
      ).toBe(true);
    }
  });

  it('offers the event loops on alert templates only', () => {
    const onStatic = bangSnippets(data).map((s) => s.label);
    expect(onStatic).not.toContain('!poll');
    expect(onStatic).not.toContain('!prediction');
    expect(onStatic).not.toContain('!hypetrain');

    const onAlert = bangSnippets({ ...data, templateType: 'alert' });
    expect(onAlert.map((s) => s.label)).toEqual(expect.arrayContaining(['!poll', '!prediction', '!hypetrain']));
    expect(onAlert.find((s) => s.label === '!poll')?.template).toContain('[[[foreach:event.choices as choice]]]');
    expect(onAlert.find((s) => s.label === '!prediction')?.template).toContain('[[[foreach:event.outcomes as outcome]]]');
    expect(onAlert.find((s) => s.label === '!hypetrain')?.template).toContain('[[[foreach:event.top_contributions as contribution]]]');
  });

  it('adds a donation bang only for a service whose controls are present', () => {
    expect(bangSnippets(data).map((s) => s.label)).toContain('!kofi');
    expect(bangSnippets(data).map((s) => s.label)).not.toContain('!streamlabs');

    const withStreamlabs = { ...data, controls: [...data.controls, { key: 'donations_received', source: 'streamlabs' }] };
    expect(bangSnippets(withStreamlabs).map((s) => s.label)).toContain('!streamlabs');
  });

  it('expands !chat to a complete chat loop', () => {
    const chat = bangSnippets(data).find((s) => s.label === '!chat')!;
    expect(chat.template).toContain('[[[foreach:chat as msg]]]');
    expect(chat.template).toContain('[[[msg.html]]]');
    expect(chat.template.trim().endsWith('[[[endforeach]]]')).toBe(true);
  });

  it("expands a donation bang using that service's control keys", () => {
    const kofi = bangSnippets(data).find((s) => s.label === '!kofi')!;
    expect(kofi.template).toContain('[[[c:kofi:latest_donor_name]]]');
    expect(kofi.template).toContain('[[[c:kofi:latest_donation_message]]]');
    expect(kofi.template).not.toContain('streamlabs');
  });

  it('keeps every template balanced', () => {
    for (const snippet of bangSnippets({ ...data, controls: DONATION_SOURCES_AS_CONTROLS, templateType: 'alert' })) {
      const opens = snippet.template.split('[[[').length - 1;
      const closes = snippet.template.split(']]]').length - 1;
      expect(opens, snippet.label).toBe(closes);
      const foreachOpens = (snippet.template.match(/\[\[\[foreach:/g) ?? []).length;
      const foreachCloses = (snippet.template.match(/\[\[\[endforeach]]]/g) ?? []).length;
      expect(foreachOpens, snippet.label).toBe(foreachCloses);
      const ifOpens = (snippet.template.match(/\[\[\[if:/g) ?? []).length;
      const ifCloses = (snippet.template.match(/\[\[\[endif]]]/g) ?? []).length;
      expect(ifOpens, snippet.label).toBe(ifCloses);
    }
  });
});

const DONATION_SOURCES_AS_CONTROLS = ['kofi', 'streamlabs', 'fourthwall', 'bmac', 'throne'].map((source) => ({
  key: 'donations_received',
  source,
}));

describe('bangPrefix', () => {
  it('matches a bang with at least one letter, replacing from the !', () => {
    expect(bangPrefix('<div>!ch')).toEqual({ from: 5, text: '!ch' });
    expect(bangPrefix('!chat')).toEqual({ from: 0, text: '!chat' });
  });

  it('stays quiet on a bare exclamation mark unless asked explicitly', () => {
    expect(bangPrefix('Thanks for the follow!')).toBeNull();
    expect(bangPrefix('Thanks for the follow!', true)).toEqual({ from: 21, text: '!' });
  });

  it('ignores exclamation marks that are not the start of a word', () => {
    expect(bangPrefix('[[[if:c:score != 1')).toBeNull();
    expect(bangPrefix('<!-- note')).toBeNull();
    expect(bangPrefix('!chat ')).toBeNull();
  });
});

describe('controlTagKey', () => {
  it('namespaces service controls under their source', () => {
    expect(controlTagKey({ key: 'timer' })).toBe('c:timer');
    expect(controlTagKey({ key: 'timer', source: null })).toBe('c:timer');
    expect(controlTagKey({ key: 'donations_received', source: 'kofi' })).toBe('c:kofi:donations_received');
  });
});
