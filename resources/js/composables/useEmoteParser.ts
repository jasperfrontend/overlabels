import { EmoteFetcher, EmoteParser } from '@mkody/twitch-emoticons'
import { ref } from 'vue'

interface TwitchEmotePosition {
  begin: number
  end: number
  id: string
}

interface TwitchEmoteEntry {
  code: string
  url: string
}

export function useEmoteParser() {
  const isReady = ref(false)
  let parser: InstanceType<typeof EmoteParser> | null = null
  // Twitch emotes fetched from backend proxy (credentials stay server-side)
  const twitchEmoteMap = new Map<string, string>() // code → CDN URL

  async function initialize(channelId: string): Promise<void> {
    const fetcher = new EmoteFetcher() // No Twitch credentials — BTTV/FFZ/7TV only
    parser = new EmoteParser(fetcher, {
      template:
        '<img class="overlay-emote" alt="{name}" src="{link}" style="display:inline;vertical-align:middle;height:1.5em;">',
      match: /([a-zA-Z0-9_-]+)/g,
    })

    await Promise.allSettled([
      fetcher.fetchBTTVEmotes(),
      fetcher.fetchBTTVEmotes(Number(channelId)),
      fetcher.fetchSevenTVEmotes(),
      fetcher.fetchSevenTVEmotes(channelId),
      fetcher.fetchFFZEmotes(),
      fetcher.fetchFFZEmotes(Number(channelId)),
      // Fetch Twitch emotes via backend proxy so credentials never reach the browser
      fetch(`/api/overlay/emotes/${channelId}`)
        .then((r) => r.json())
        .then((entries: TwitchEmoteEntry[]) => {
          for (const { code, url } of entries) {
            twitchEmoteMap.set(code, url)
          }
        }),
    ])

    isReady.value = true
  }

  /**
   * Parse a single whitespace-free token: check Twitch map first, then BTTV/FFZ/7TV library.
   * Splitting by whitespace before calling this ensures the library regex never sees
   * already-generated <img> HTML.
   */
  function parseToken(token: string): string {
    const twitchUrl = twitchEmoteMap.get(token)
    if (twitchUrl) {
      return `<img class="overlay-emote twitch-emote" alt="${token}" src="${twitchUrl}" style="display:inline;vertical-align:middle;height:1.5em;">`
    }
    return parser ? parser.parse(token) : token
  }

  /** Split text on whitespace runs, parse each word token independently. */
  function parseByTokens(text: string): string {
    return text
      .split(/(\s+)/)
      .map((chunk) => (/^\s+$/.test(chunk) ? chunk : parseToken(chunk)))
      .join('')
  }

  function parseEmotes(text: string, twitchEmotesJson?: string): string {
    if (!isReady.value) return text

    // Parse Twitch emote positions from EventSub payload (resub messages have these)
    let twitchEmotes: TwitchEmotePosition[] = []
    if (twitchEmotesJson) {
      try {
        const parsed = JSON.parse(twitchEmotesJson)
        if (Array.isArray(parsed)) twitchEmotes = parsed
      } catch {
        /* invalid JSON, skip */
      }
    }

    // No position data (e.g. channel points user_input) — use token-based parsing
    if (!twitchEmotes.length) {
      return parseByTokens(text)
    }

    // Position-based splitting for resub messages: more accurate than code-matching,
    // prevents false positives on partial word matches.
    const sorted = [...twitchEmotes].sort((a, b) => a.begin - b.begin)
    const parts: string[] = []
    let lastIndex = 0

    for (const emote of sorted) {
      if (emote.begin > lastIndex) {
        parts.push(parseByTokens(text.slice(lastIndex, emote.begin)))
      }
      const emoteName = text.slice(emote.begin, emote.end + 1)
      const url = `https://static-cdn.jtvnw.net/emoticons/v2/${emote.id}/default/dark/1.0`
      parts.push(
        `<img class="overlay-emote twitch-emote" alt="${emoteName}" src="${url}" style="display:inline;vertical-align:middle;height:1.5em;">`,
      )
      lastIndex = emote.end + 1
    }

    if (lastIndex < text.length) {
      parts.push(parseByTokens(text.slice(lastIndex)))
    }

    return parts.join('')
  }

  return { initialize, parseEmotes, isReady }
}
