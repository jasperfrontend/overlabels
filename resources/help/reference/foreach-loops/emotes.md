emote typed in live Twitch chat, one item per occurrence.
note: like `chat`, this data does not come from the render payload. The overlay reads chat from Twitch directly over anonymous IRC and lists the emotes it sees, so the array exists only in the browser. A message with three emotes gives three items; a message with none gives nothing.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `html` :: the emote as an `<img>` tag. Rendered unescaped, like `chat`'s `html`
- `url` :: the emote's image URL on its provider's CDN, for a `src` or a CSS `background-image`
- `name` :: the emote code as typed (`PogChamp`, `KEKW`)
- `id` :: stable per occurrence: the message id plus the position within the message. Put it in `data-key` so a bubble keeps its animation across updates
- `n` :: which of its message's emotes this is, 0 first. Multiply it by a delay to stagger a burst
- `x`, `y`, `seed` :: three stable pseudo-random numbers in 0 to 99, different per occurrence and identical on every re-render. Spawn points, animation phases, anything that must look random without ever jumping
- `message_id` :: Twitch's id of the message the emote came from
- `author`, `login`, `color` :: the chatter, as on `chat`
- `at` :: Unix epoch seconds of the message
- `source_channel` :: empty for a native message; set for one duplicated in during a Shared Chat session

Also available

- `emotes.count` :: how many occurrences are currently in the buffer

Notes

- Index 0 is the OLDEST occurrence and `:last-child` is the newest, the same rule as `chat`.
- Twitch emotes, 7TV, BTTV and FFZ count. A Unicode emoji is text, not an emote, and is not listed.
- The buffer holds the last 100 occurrences and is not a foreach cap: it is a fixed bound. How many to SHOW is the template's decision, for instance `:nth-last-child(-n + 40)`.
- The chat display filters at `/settings/chat` apply here too, at ingest: a hidden chatter's emotes never enter the buffer. Deletions are honoured: a message a mod removes takes its emotes with it, and a purge takes a chatter's.
- Third-party emotes are matched when the message arrives. A message that lands in the second or so before the emote library has loaded contributes its Twitch emotes only.

See also: [Chat Emote Bubbles](/help/chat-emote-bubbles), the product built on this loop, and the [Twitch Chat in an Overlay](/help/chat) guide for the connection itself.
