import { EmoteFetcher, EmoteParser } from '@mkody/twitch-emoticons'
import { ref } from 'vue'

interface TwitchEmotePosition {
  begin: number
  end: number
  id: string
}

export function useEmoteParser() {
  const isReady = ref(false)
  let parser: InstanceType<typeof EmoteParser> | null = null

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
    ])

    isReady.value = true
  }

  function parseEmotes(text: string, twitchEmotesJson?: string): string {
    // Parse Twitch emote positions from EventSub payload (already JSON-encoded by backend)
    let twitchEmotes: TwitchEmotePosition[] = []
    if (twitchEmotesJson) {
      try {
        const parsed = JSON.parse(twitchEmotesJson)
        if (Array.isArray(parsed)) twitchEmotes = parsed
      } catch {
        /* invalid JSON, skip */
      }
    }

    // No Twitch emotes — pass entire text through library for BTTV/FFZ/7TV
    if (!twitchEmotes.length) {
      return isReady.value && parser ? parser.parse(text) : text
    }

    // Split text around Twitch emote positions; parse non-Twitch segments with library.
    // This prevents the library regex from matching words inside generated <img> tags.
    const sorted = [...twitchEmotes].sort((a, b) => a.begin - b.begin)
    const parts: string[] = []
    let lastIndex = 0

    for (const emote of sorted) {
      if (emote.begin > lastIndex) {
        const segment = text.slice(lastIndex, emote.begin)
        parts.push(isReady.value && parser ? parser.parse(segment) : segment)
      }
      // Twitch emote → construct CDN URL directly from position data
      const emoteName = text.slice(emote.begin, emote.end + 1)
      const url = `https://static-cdn.jtvnw.net/emoticons/v2/${emote.id}/default/dark/1.0`
      parts.push(
        `<img class="overlay-emote twitch-emote" alt="${emoteName}" src="${url}" style="display:inline;vertical-align:middle;height:1.5em;">`,
      )
      lastIndex = emote.end + 1
    }

    if (lastIndex < text.length) {
      const segment = text.slice(lastIndex)
      parts.push(isReady.value && parser ? parser.parse(segment) : segment)
    }

    return parts.join('')
  }

  return { initialize, parseEmotes, isReady }
}
