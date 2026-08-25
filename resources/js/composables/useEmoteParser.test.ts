import { describe, expect, it } from 'vitest';
import { splitByEmotePositions } from './useEmoteParser';

/*
 * Only the pure part is covered here. The composable itself fetches emote
 * manifests and needs a DOM, and the suite runs in node by deliberate choice -
 * see the testing note in CLAUDE.md.
 */

const emote = (text: string, id: string) => ({ kind: 'emote' as const, text, id });
const run = (text: string) => ({ kind: 'text' as const, text });

describe('splitByEmotePositions', () => {
  it('splits a plain message around its emotes', () => {
    expect(
      splitByEmotePositions('hi Kappa there Kappa', [
        { id: '25', begin: 3, end: 7 },
        { id: '25', begin: 15, end: 19 },
      ]),
    ).toEqual([run('hi '), emote('Kappa', '25'), run(' there '), emote('Kappa', '25')]);
  });

  it('applies the ranges as code points, not UTF-16 units', () => {
    // THE case. Twitch counts emote positions in code points; an emoji outside
    // the BMP is one code point but two UTF-16 units. Slicing by string index
    // landed one unit early for every such character in front of the emote,
    // rendering alt=" Kapp" and leaking the trailing "a" as text. Found via the
    // bot's channel-point reply, which opens with a pinata.
    const text = '\u{1FA85} @Jasper redeemed one for test KEKW Kappa OhMyDog';
    const cps = Array.from(text);
    const kappa = cps.findIndex((_, i) => cps.slice(i, i + 5).join('') === 'Kappa');
    const dog = cps.findIndex((_, i) => cps.slice(i, i + 7).join('') === 'OhMyDog');

    expect(
      splitByEmotePositions(text, [
        { id: '25', begin: kappa, end: kappa + 4 },
        { id: '81103', begin: dog, end: dog + 6 },
      ]),
    ).toEqual([run('\u{1FA85} @Jasper redeemed one for test KEKW '), emote('Kappa', '25'), run(' '), emote('OhMyDog', '81103')]);
  });

  it('shifts by one unit per astral character, so several emoji compound', () => {
    const text = '\u{1F602}\u{1F602}\u{1F602} Kappa';
    expect(splitByEmotePositions(text, [{ id: '25', begin: 4, end: 8 }])).toEqual([run('\u{1F602}\u{1F602}\u{1F602} '), emote('Kappa', '25')]);
  });

  it('handles an emote at the very start and the very end', () => {
    expect(splitByEmotePositions('Kappa', [{ id: '25', begin: 0, end: 4 }])).toEqual([emote('Kappa', '25')]);
  });

  it('drops a range that runs past the text instead of slicing nonsense', () => {
    // The tag is attacker-influenced wire data and must never take the overlay down.
    expect(splitByEmotePositions('hi', [{ id: '25', begin: 0, end: 40 }])).toEqual([run('hi')]);
    expect(splitByEmotePositions('hi', [{ id: '25', begin: 10, end: 14 }])).toEqual([run('hi')]);
  });

  it('ignores an overlapping range rather than emitting text twice', () => {
    expect(
      splitByEmotePositions('Kappa', [
        { id: '25', begin: 0, end: 4 },
        { id: '26', begin: 2, end: 4 },
      ]),
    ).toEqual([emote('Kappa', '25')]);
  });

  it('returns the whole message as one text run when there are no positions', () => {
    expect(splitByEmotePositions('just words', [])).toEqual([run('just words')]);
  });
});
