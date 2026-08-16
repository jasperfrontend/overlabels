import type { ChatMessage } from '@/utils/ircParser';

/**
 * Twitch chat badge artwork.
 *
 * The IRC `badges` tag addresses art as `set/version` (`moderator/1`,
 * `subscriber/12`), so the manifest is keyed exactly that way and no lookup
 * here has to know anything about Twitch's response shape.
 */
export interface BadgeArt {
  url: string;
  title: string;
}

/**
 * Two maps, deliberately not merged.
 *
 * `channel` is this channel's art (global, with the channel's own overrides
 * layered on). `global` is what is true in every channel on Twitch.
 *
 * A Shared Chat message from a collab partner carries THEIR badge versions,
 * and their channel-specific art - subscriber, bits, founder - lives in a
 * manifest this overlay never fetched. Resolving those against `channel` would
 * render OUR subscriber emblem for someone who subscribes somewhere else,
 * which is worse than rendering nothing: it states something false about a
 * viewer. Foreign messages therefore resolve against `global` only, where
 * moderator, VIP, staff and broadcaster art is correct for anyone anywhere.
 */
export interface BadgeManifest {
  global: Record<string, BadgeArt>;
  channel: Record<string, BadgeArt>;
}

export const EMPTY_BADGE_MANIFEST: BadgeManifest = { global: {}, channel: {} };

/**
 * Normalise the endpoint response.
 *
 * Defensive because this arrives over the wire: a failed fetch, an app token
 * Twitch refused, or an older server must degrade to "no badge art" rather
 * than throw while building a chat slot.
 */
export function toBadgeManifest(raw: unknown): BadgeManifest {
  if (!raw || typeof raw !== 'object') return EMPTY_BADGE_MANIFEST;

  const source = raw as { global?: unknown; channel?: unknown };

  return {
    global: toBadgeMap(source.global),
    channel: toBadgeMap(source.channel),
  };
}

function toBadgeMap(raw: unknown): Record<string, BadgeArt> {
  if (!raw || typeof raw !== 'object') return {};

  const out: Record<string, BadgeArt> = {};
  for (const [key, value] of Object.entries(raw as Record<string, unknown>)) {
    if (!value || typeof value !== 'object') continue;
    const art = value as { url?: unknown; title?: unknown };
    if (typeof art.url !== 'string' || art.url === '') continue;

    out[key] = {
      url: art.url,
      title: typeof art.title === 'string' ? art.title : key.split('/')[0],
    };
  }
  return out;
}

export function hasBadgeArt(manifest: BadgeManifest): boolean {
  return Object.keys(manifest.global).length > 0 || Object.keys(manifest.channel).length > 0;
}

/**
 * Build the `<img>` markup for one message's badges.
 *
 * THIS OUTPUT IS RENDERED WITHOUT ENTITY-ENCODING, as the second entry in the
 * html-safe field list alongside `chat.N.html`. Everything it emits must
 * therefore be generated here and never echoed from chat:
 *
 * - `src` comes only from the manifest, which came from our own server, which
 *   got it from Twitch's API. A chatter cannot put a value in it. It is
 *   additionally restricted to Twitch's own CDN below.
 * - `alt` is the manifest's title, escaped anyway, because "it came from an
 *   API" is a weaker guarantee than escaping and the cost is nothing.
 * - The badge key itself is never interpolated into the output. An unknown key
 *   produces no element at all, so a chatter cannot smuggle markup through a
 *   crafted badge name.
 *
 * Bracket defusing still applies downstream, exactly as it does for
 * `chat.N.html`, so a `[[[...]]]` sequence could not survive here either.
 */
export function badgeImages(message: ChatMessage, manifest: BadgeManifest): string {
  const versions = message.badgeVersions;
  if (!versions || versions.length === 0) return '';

  // A foreign message's channel-specific art is not ours to guess at.
  const map = message.sourceRoomId ? manifest.global : manifest.channel;

  const parts: string[] = [];
  for (const key of versions) {
    const art = map[key];
    if (!art) continue;
    if (!isTwitchBadgeUrl(art.url)) continue;

    const alt = escapeAttribute(art.title);
    parts.push(`<img class="ol-badge" src="${escapeAttribute(art.url)}" alt="${alt}" title="${alt}">`);
  }

  return parts.join('');
}

/**
 * Only Twitch's own badge CDN.
 *
 * Belt and braces: the manifest already comes from our server rather than from
 * chat, so this should never reject anything. It exists so that if the server
 * side is ever changed to accept a URL from somewhere less trustworthy, the
 * html-safe path does not silently become an open redirect for image loads.
 */
function isTwitchBadgeUrl(url: string): boolean {
  return url.startsWith('https://static-cdn.jtvnw.net/');
}

function escapeAttribute(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
