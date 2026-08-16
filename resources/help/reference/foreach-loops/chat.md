live Twitch chat message.
note: unlike every other loop here, this data does not come from the render payload. The overlay reads chat from Twitch directly over anonymous IRC, so the array exists only in the browser.

Fields available on each iteration item

Inside the loop body, reference any of these fields as `[[[alias.field]]]`, where alias is the name you picked after `as`. Missing fields render as an empty string.

- `author` :: the chatter's display name, capitalised as they write it
- `login` :: lowercase login - use this for comparisons, never the display name
- `text` :: the message as plain text, emotes left as their word form
- `html` :: the message with emotes replaced by `<img>` tags. The only foreach field rendered unescaped
- `color` :: the chatter's Twitch name colour, as a hex value
- `badges` :: bare badge set names ("moderator subscriber"), for use as CSS classes
- `badge_images` :: the badge artwork as `<img>` tags. Only emitted once the badge manifest has loaded
- `at` :: Unix epoch seconds of when the message arrived
- `id` :: Twitch's message id
- `mod`, `sub`, `vip`, `broadcaster`, `first` :: flags, testable with `[[[if:alias.mod]]]`
- `source_channel` :: empty for a native message; set when the message was duplicated in from a collab partner during a Shared Chat session

Also available

- `chat.count` :: how many messages are currently in the window

Notes

- Index 0 is the OLDEST visible message, so iterating in order reads top-to-bottom the way chat does.
- The window size is a foreach cap like any other, set on `/settings/account`. It defaults to 50, and it is the only cap enforced in the browser rather than server-side - because there is no server-side array to slice.
- Two display filters at `/settings/chat` can hide `!` commands and specific logins. They apply at ingest, so a hidden message never takes a slot in the window. They are display settings, not moderation.
- Deletions are always honoured regardless of those filters: a message removed by a mod disappears from the overlay too.

See also: the [Twitch Chat in an Overlay](/help/chat) guide for badges, emotes and Shared Chat in full, and [Show chat on screen](/help/tutorials/show-chat-on-screen) for a copy-and-paste starting point.
