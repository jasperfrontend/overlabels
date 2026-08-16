import { describe, expect, it } from 'vitest';
import { EMPTY_BADGE_MANIFEST, badgeImages, hasBadgeArt, toBadgeManifest, type BadgeManifest } from './badgeRenderer';
import type { ChatMessage } from './ircParser';

const CDN = 'https://static-cdn.jtvnw.net/badges/v1';

function msg(over: Partial<ChatMessage> = {}): ChatMessage {
  return {
    id: 'm1',
    userId: '1',
    login: 'ana',
    author: 'Ana',
    text: 'hello',
    color: '#ff0000',
    badges: 'moderator',
    at: 1755273600,
    isMod: true,
    isSubscriber: false,
    isVip: false,
    isBroadcaster: false,
    isFirstMessage: false,
    sourceRoomId: '',
    badgeVersions: ['moderator/1'],
    emotes: [],
    ...over,
  };
}

function manifest(over: Partial<BadgeManifest> = {}): BadgeManifest {
  return {
    global: { 'moderator/1': { url: `${CDN}/mod.png`, title: 'Moderator' } },
    channel: {
      'moderator/1': { url: `${CDN}/mod.png`, title: 'Moderator' },
      'subscriber/12': { url: `${CDN}/ours-12.png`, title: 'Subscriber (12 months)' },
    },
    ...over,
  };
}

describe('toBadgeManifest', () => {
  it('reads the shape the endpoint returns', () => {
    const parsed = toBadgeManifest({
      global: { 'moderator/1': { url: `${CDN}/mod.png`, title: 'Moderator' } },
      channel: { 'subscriber/1': { url: `${CDN}/sub.png`, title: 'Subscriber' } },
    });

    expect(parsed.global['moderator/1'].url).toBe(`${CDN}/mod.png`);
    expect(parsed.channel['subscriber/1'].title).toBe('Subscriber');
  });

  it('degrades to no art rather than throwing on a bad payload', () => {
    // A failed fetch or an older server must not take the chat feed down.
    expect(toBadgeManifest(undefined)).toEqual(EMPTY_BADGE_MANIFEST);
    expect(toBadgeManifest(null)).toEqual(EMPTY_BADGE_MANIFEST);
    expect(toBadgeManifest('nope')).toEqual(EMPTY_BADGE_MANIFEST);
    expect(toBadgeManifest({})).toEqual(EMPTY_BADGE_MANIFEST);
    expect(toBadgeManifest({ global: 'nope', channel: 5 })).toEqual(EMPTY_BADGE_MANIFEST);
  });

  it('drops entries with no usable url', () => {
    const parsed = toBadgeManifest({
      global: {
        'good/1': { url: `${CDN}/a.png`, title: 'A' },
        'nourl/1': { title: 'B' },
        'empty/1': { url: '', title: 'C' },
        'notobject/1': 'nope',
      },
    });

    expect(Object.keys(parsed.global)).toEqual(['good/1']);
  });

  it('falls back to the set name when a title is missing', () => {
    const parsed = toBadgeManifest({ global: { 'moderator/1': { url: `${CDN}/m.png` } } });

    expect(parsed.global['moderator/1'].title).toBe('moderator');
  });
});

describe('hasBadgeArt', () => {
  it('distinguishes an empty manifest from a loaded one', () => {
    expect(hasBadgeArt(EMPTY_BADGE_MANIFEST)).toBe(false);
    expect(hasBadgeArt(manifest())).toBe(true);
  });
});

describe('badgeImages', () => {
  it('renders an img per badge, in wire order', () => {
    const html = badgeImages(msg({ badgeVersions: ['moderator/1', 'subscriber/12'] }), manifest());

    expect(html).toBe(
      `<img class="ol-badge" src="${CDN}/mod.png" alt="Moderator" title="Moderator">` +
        `<img class="ol-badge" src="${CDN}/ours-12.png" alt="Subscriber (12 months)" title="Subscriber (12 months)">`,
    );
  });

  it('renders nothing when the message has no badges', () => {
    expect(badgeImages(msg({ badgeVersions: [] }), manifest())).toBe('');
  });

  it('renders nothing before the manifest loads', () => {
    expect(badgeImages(msg(), EMPTY_BADGE_MANIFEST)).toBe('');
  });

  it('skips a badge the manifest does not know', () => {
    // An unknown key must produce NO element rather than being interpolated
    // into the output - that is what stops a crafted badge name reaching the
    // html-safe slot.
    const html = badgeImages(msg({ badgeVersions: ['moderator/1', 'invented/99'] }), manifest());

    expect(html).toBe(`<img class="ol-badge" src="${CDN}/mod.png" alt="Moderator" title="Moderator">`);
  });

  it('respects the version, not just the set', () => {
    // A 3-month and a 12-month subscriber badge are different images.
    expect(badgeImages(msg({ badgeVersions: ['subscriber/3'] }), manifest())).toBe('');
    expect(badgeImages(msg({ badgeVersions: ['subscriber/12'] }), manifest())).not.toBe('');
  });

  describe('Shared Chat', () => {
    it('resolves a foreign message against global art only', () => {
      // A partner's subscriber badge art lives in THEIR manifest, which this
      // overlay never fetched. Falling back to ours would render our emblem
      // for someone who subscribes elsewhere - stating something false about
      // a viewer, which is worse than rendering nothing.
      const foreign = msg({ sourceRoomId: '999', badgeVersions: ['moderator/1', 'subscriber/12'] });

      const html = badgeImages(foreign, manifest());

      expect(html).toBe(`<img class="ol-badge" src="${CDN}/mod.png" alt="Moderator" title="Moderator">`);
      expect(html).not.toContain('ours-12');
    });

    it('still uses channel art for a native message', () => {
      const native = msg({ sourceRoomId: '', badgeVersions: ['subscriber/12'] });

      expect(badgeImages(native, manifest())).toContain('ours-12');
    });
  });

  describe('safety', () => {
    it('escapes the title, even though it came from an API', () => {
      const evil = manifest({
        channel: { 'moderator/1': { url: `${CDN}/m.png`, title: '"><script>x</script>' } },
      });

      const html = badgeImages(msg(), evil);

      expect(html).not.toContain('<script>');
      expect(html).toContain('&quot;&gt;&lt;script&gt;');
    });

    it('refuses a url that is not on Twitch CDN', () => {
      const evil = manifest({
        channel: { 'moderator/1': { url: 'https://evil.example/x.png', title: 'Moderator' } },
      });

      expect(badgeImages(msg(), evil)).toBe('');
    });

    it('refuses a javascript: url', () => {
      // Payload deliberately avoids the literal name of a native dialog:
      // NativeDialogsTest greps resources/js for those, and an XSS fixture
      // reads exactly like a real call site.
      const evil = manifest({
        channel: { 'moderator/1': { url: 'javascript:void 0', title: 'Moderator' } },
      });

      expect(badgeImages(msg(), evil)).toBe('');
    });

    it('never emits the badge key itself', () => {
      // The key is chatter-influenced (it comes off the IRC tag), so it must
      // not reach the output even when it looks like markup.
      const html = badgeImages(msg({ badgeVersions: ['"><img src=x onerror=y>/1'] }), manifest());

      expect(html).toBe('');
    });
  });
});
