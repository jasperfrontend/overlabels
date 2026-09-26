import { describe, expect, it } from 'vitest';
import { emoteHtml, emoteOccurrences, emoteSlots, stableHash, twitchEmoteUrl, withEmoteSlots } from './emoteSlots';
import type { ChatMessage } from './ircParser';

function msg(over: Partial<ChatMessage> = {}): ChatMessage {
  return {
    id: 'm1',
    userId: '1',
    login: 'ana',
    author: 'Ana',
    text: 'hello',
    color: '#ff0000',
    badges: 'subscriber',
    at: 1755273600,
    isMod: false,
    isSubscriber: true,
    isVip: false,
    isBroadcaster: false,
    isFirstMessage: false,
    sourceRoomId: '',
    badgeVersions: [],
    emotes: [],
    ...over,
  };
}

const kappa = { id: '25', begin: 0, end: 4 };

describe('emoteOccurrences', () => {
  it('yields one item per Twitch emote occurrence, in typing order', () => {
    // Five PogChamps are five bubbles. That is the whole product.
    const text = 'PogChamp PogChamp PogChamp';
    const positions = [0, 9, 18].map((begin) => ({ id: '305954156', begin, end: begin + 7 }));

    const found = emoteOccurrences(msg({ id: 'bomb', text, emotes: positions }));

    expect(found.map((e) => e.name)).toEqual(['PogChamp', 'PogChamp', 'PogChamp']);
    expect(found.map((e) => e.n)).toEqual([0, 1, 2]);
    expect(found.map((e) => e.id)).toEqual(['bomb:0', 'bomb:1', 'bomb:2']);
    expect(found[0].url).toBe(twitchEmoteUrl('305954156'));
  });

  it('needs no resolver for Twitch emotes, since they arrive with ids on the wire', () => {
    const found = emoteOccurrences(msg({ text: 'Kappa hi', emotes: [kappa] }));

    expect(found).toHaveLength(1);
    expect(found[0].url).toContain('/emoticons/v2/25/');
  });

  it('offers the text between Twitch emotes to the resolver token by token', () => {
    const resolve = (token: string) => (token === 'KEKW' ? 'https://cdn.example/kekw' : null);

    const found = emoteOccurrences(msg({ text: 'Kappa lol KEKW', emotes: [kappa] }), resolve);

    expect(found.map((e) => e.name)).toEqual(['Kappa', 'KEKW']);
    expect(found[1].url).toBe('https://cdn.example/kekw');
    expect(found[1].n).toBe(1);
  });

  it('yields nothing for a message without emotes', () => {
    expect(emoteOccurrences(msg({ text: 'just words' }), () => null)).toEqual([]);
  });

  it('copies the chatter onto every occurrence', () => {
    const [e] = emoteOccurrences(msg({ text: 'Kappa', emotes: [kappa], author: 'Bo', login: 'bo', color: '#00ff00', sourceRoomId: '9' }));

    expect(e.author).toBe('Bo');
    expect(e.login).toBe('bo');
    expect(e.color).toBe('#00ff00');
    expect(e.at).toBe(1755273600);
    expect(e.sourceRoomId).toBe('9');
    expect(e.messageId).toBe('m1');
  });

  it('gives every occurrence a spawn point that is stable across rebuilds', () => {
    // The renderer rebuilds the slots on every change. A coordinate that came
    // from Math.random would move a bubble mid-flight on every new message.
    const build = () => emoteOccurrences(msg({ id: 'same', text: 'Kappa Kappa', emotes: [kappa, { id: '25', begin: 6, end: 10 }] }));

    const a = build();
    const b = build();

    expect(a.map((e) => [e.x, e.y, e.seed])).toEqual(b.map((e) => [e.x, e.y, e.seed]));
    // ...and different per occurrence, or every bubble of a bomb would stack.
    expect([a[0].x, a[0].y, a[0].seed]).not.toEqual([a[1].x, a[1].y, a[1].seed]);
  });
});

describe('stableHash', () => {
  it('lands in [0, 100)', () => {
    for (let i = 0; i < 500; i++) {
      const h = stableHash(`id-${i}:${i % 7}`, i % 3);
      expect(h).toBeGreaterThanOrEqual(0);
      expect(h).toBeLessThan(100);
      expect(Number.isInteger(h)).toBe(true);
    }
  });

  it('spreads across the range rather than clumping', () => {
    const seen = new Set<number>();
    for (let i = 0; i < 500; i++) seen.add(stableHash(`m${i}:0`, 1));

    expect(seen.size).toBeGreaterThan(80);
  });
});

describe('emoteHtml', () => {
  it('emits one img built from the emote, with nothing from the message in it', () => {
    const [e] = emoteOccurrences(msg({ text: 'Kappa', emotes: [kappa] }));

    expect(emoteHtml(e)).toBe(`<img class="overlay-emote" alt="Kappa" src="${twitchEmoteUrl('25')}">`);
  });

  it('escapes the name and the url anyway', () => {
    const e = { ...emoteOccurrences(msg({ text: 'Kappa', emotes: [kappa] }))[0], name: 'a"b<c', url: 'x"y' };

    expect(emoteHtml(e)).not.toContain('"b<');
    expect(emoteHtml(e)).toContain('a&quot;b&lt;c');
  });
});

describe('emoteSlots', () => {
  it('emits the flat dotted keys resolveIterable synthesises an array from', () => {
    const list = emoteOccurrences(msg({ id: 'a', text: 'Kappa Kappa', emotes: [kappa, { id: '25', begin: 6, end: 10 }] }));

    const slots = emoteSlots(list);

    expect(slots['emotes.count']).toBe('2');
    expect(slots['emotes.0.id']).toBe('a:0');
    expect(slots['emotes.1.id']).toBe('a:1');
    expect(slots['emotes.1.n']).toBe('1');
    expect(slots['emotes.0.name']).toBe('Kappa');
    expect(slots['emotes.0.html']).toContain('<img');
    expect(slots['emotes.0.author']).toBe('Ana');
    expect(slots['emotes.0.at']).toBe('1755273600');
    expect(slots['emotes.0.source_channel']).toBe('');
    expect(Number(slots['emotes.0.x'])).toBeLessThan(100);
  });
});

describe('withEmoteSlots', () => {
  it('drops every previous emotes.* key before merging, so a purge cannot resurrect one', () => {
    const before = withEmoteSlots({ 'c:speed': '4' }, emoteOccurrences(msg({ id: 'a', text: 'Kappa', emotes: [kappa] })));
    expect(before['emotes.0.id']).toBe('a:0');

    const after = withEmoteSlots(before, []);

    expect(after['emotes.count']).toBe('0');
    expect(after['emotes.0.id']).toBeUndefined();
    expect(after['c:speed']).toBe('4');
  });
});
