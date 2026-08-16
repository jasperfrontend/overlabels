import { encodeHtml } from '@/utils/tagParser';
// TYPE-ONLY on purpose: this import is erased at compile time, so it creates no
// runtime dependency and the library stays out of the overlay's entry bundle.
// The value import lives inside initialize(), below. See the note there.
import type { EmoteParser } from '@mkody/twitch-emoticons';
import { ref } from 'vue';

interface TwitchEmotePosition {
  begin: number;
  end: number;
  id: string;
}

interface TwitchEmoteEntry {
  code: string;
  url: string;
}

export function useEmoteParser() {
  const isReady = ref(false);
  let parser: InstanceType<typeof EmoteParser> | null = null;
  // Twitch emotes fetched from backend proxy (credentials stay server-side)
  const twitchEmoteMap = new Map<string, string>(); // code → CDN URL

  /**
   * Load the emote library and warm its caches for one channel.
   *
   * The library is pulled in with a DYNAMIC import so it becomes its own chunk
   * instead of riding in the overlay's entry bundle. Only two fields are ever
   * emote-parsed (`event.message.text` and `event.user_input`, see
   * HTML_SAFE_ALERT_FIELDS in OverlayRenderer), and both belong to alerts - yet
   * every overlay was paying to download and evaluate the library before first
   * paint, including overlays no alert template targets.
   *
   * This is safe to make async because nothing awaits initialize(): OverlayRenderer
   * calls it fire-and-forget with a `.catch()`, and parseEmotes() already returns
   * its input untouched while `isReady` is false. The download window simply joins
   * the fetch window that was always there.
   *
   * It also degrades better than the static import did. A chunk that fails to load
   * (network blip, or a stale chunk reference after a deploy) now rejects here,
   * leaves `parser` null, and renders emotes as plain text. Previously the same
   * failure took the whole entry bundle - and therefore the whole overlay - with it.
   *
   * NOTE: the emote parser belongs in the static overlay and should stay here.
   * Alerts render inside the static overlay's DOM, so this is the only JS context
   * that exists, and emote sets are per-channel rather than per-alert - fetching
   * once at mount and reusing is what keeps a cheer from hitting 7TV every time.
   * The eagerness was the bug, not the placement.
   */
  async function initialize(channelId: number): Promise<void> {
    const { EmoteFetcher, EmoteParser } = await import('@mkody/twitch-emoticons');

    const fetcher = new EmoteFetcher(); // No Twitch credentials — BTTV/FFZ/7TV only
    parser = new EmoteParser(fetcher, {
      // No inline styles. Sizing lives in the overlay's base stylesheet as a
      // `.overlay-emote` rule, so a template can override it with an ordinary
      // selector instead of having to out-shout `style=""` with `!important`.
      template: '<img class="overlay-emote" alt="{name}" src="{link}">',
      match: /([a-zA-Z0-9_-]+)/g,
    });

    // Named so a failure can say WHICH source broke. This used to be a bare
    // Promise.allSettled with the results discarded, which meant every one of
    // these could fail and produce no signal at all - the overlay just rendered
    // emote codes as text forever.
    //
    // That is not hypothetical: a minifier collision between the emote
    // library's base class and its subclasses made every emote constructor
    // throw in production and nothing anywhere said so. See the keepNames note
    // in vite.config.mts.
    const sources: Array<[string, Promise<unknown>]> = [
      ['bttv:global', fetcher.fetchBTTVEmotes()],
      ['bttv:channel', fetcher.fetchBTTVEmotes(Number(channelId))],
      ['7tv:global', fetcher.fetchSevenTVEmotes()],
      ['7tv:channel', fetcher.fetchSevenTVEmotes(channelId)],
      ['ffz:global', fetcher.fetchFFZEmotes()],
      ['ffz:channel', fetcher.fetchFFZEmotes(Number(channelId))],
      // Twitch emotes via our own proxy, so credentials never reach the browser.
      [
        'twitch:proxy',
        fetch(`/api/overlay/emotes/${channelId}`)
          .then((r) => r.json())
          .then((entries: TwitchEmoteEntry[]) => {
            for (const { code, url } of entries) {
              twitchEmoteMap.set(code, url);
            }
          }),
      ],
    ];

    const results = await Promise.allSettled(sources.map(([, promise]) => promise));

    const failed: string[] = [];
    results.forEach((result, i) => {
      if (result.status !== 'rejected') return;
      const name = sources[i][0];
      failed.push(name);
      console.warn(`[emotes] ${name} failed:`, result.reason?.message ?? result.reason);
    });

    // Still a partial failure worth naming: every source can resolve and yet
    // cache nothing, which looks identical on screen to not loading at all.
    const thirdPartyCount = fetcher.emotes?.size ?? 0;
    if (thirdPartyCount === 0) {
      console.warn(`[emotes] no BTTV/FFZ/7TV emotes cached for channel ${channelId}. ` + 'Those emotes will render as plain text.');
    }

    console.info(
      `[emotes] ready - ${thirdPartyCount} third-party, ${twitchEmoteMap.size} twitch` +
        (failed.length ? `, ${failed.length} source(s) failed: ${failed.join(', ')}` : ''),
    );

    isReady.value = true;
  }

  /**
   * Parse a single whitespace-free token: check Twitch map first, then BTTV/FFZ/7TV library.
   * Splitting by whitespace before calling this ensures the library regex never sees
   * already-generated <img> HTML.
   */
  // Encode the token first so any user-typed HTML (e.g. `<img src=evil>`) is
  // neutralised before we look for emote names. Encoded text still contains
  // letters/digits, so emote names like `Kappa` survive and can be matched and
  // replaced with safe `<img>` HTML. The result is a "safe HTML" string the
  // OverlayRenderer can splice in without re-encoding.
  function parseToken(token: string): string {
    const twitchUrl = twitchEmoteMap.get(token);
    if (twitchUrl) {
      return `<img class="overlay-emote twitch-emote" alt="${encodeHtml(token)}" src="${twitchUrl}">`;
    }
    const encoded = encodeHtml(token);
    return parser ? parser.parse(encoded) : encoded;
  }

  /** Split text on whitespace runs, parse each word token independently. */
  function parseByTokens(text: string): string {
    return text
      .split(/(\s+)/)
      .map((chunk) => (/^\s+$/.test(chunk) ? chunk : parseToken(chunk)))
      .join('');
  }

  function parseEmotes(text: string, twitchEmotesJson?: string): string {
    if (!isReady.value) return text;

    // Parse Twitch emote positions from EventSub payload (resub messages have these)
    let twitchEmotes: TwitchEmotePosition[] = [];
    if (twitchEmotesJson) {
      try {
        const parsed = JSON.parse(twitchEmotesJson);
        if (Array.isArray(parsed)) twitchEmotes = parsed;
      } catch {
        /* invalid JSON, skip */
      }
    }

    // No position data (e.g., channel points user_input) — use token-based parsing
    if (!twitchEmotes.length) {
      return parseByTokens(text);
    }

    // Position-based splitting for resub messages: more accurate than code-matching,
    // prevents false positives on partial word matches.
    const sorted = [...twitchEmotes].sort((a, b) => a.begin - b.begin);
    const parts: string[] = [];
    let lastIndex = 0;

    for (const emote of sorted) {
      if (emote.begin > lastIndex) {
        parts.push(parseByTokens(text.slice(lastIndex, emote.begin)));
      }
      const emoteName = text.slice(emote.begin, emote.end + 1);
      const url = `https://static-cdn.jtvnw.net/emoticons/v2/${emote.id}/default/dark/1.0`;
      parts.push(`<img class="overlay-emote twitch-emote" alt="${encodeHtml(emoteName)}" src="${url}">`);
      lastIndex = emote.end + 1;
    }

    if (lastIndex < text.length) {
      parts.push(parseByTokens(text.slice(lastIndex)));
    }

    return parts.join('');
  }

  return { initialize, parseEmotes, isReady };
}
