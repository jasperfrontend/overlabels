when the title, category, or content labels change mid-stream.

### Channel Information
- `[[[event.broadcaster_user_id]]]` — Your Twitch ID
- `[[[event.broadcaster_user_login]]]` — Your username
- `[[[event.broadcaster_user_name]]]` — Your display name

### Updated Fields
- `[[[event.title]]]` — New stream title
- `[[[event.language]]]` — Language code (e.g. "en")
- `[[[event.category_id]]]` — New category/game ID
- `[[[event.category_name]]]` — New category/game name

example:
```
[[[if:event.category_name]]]
  <div class="now-playing">Now playing: [[[event.category_name]]]</div>
[[[endif]]]
```

note: maps to the Twitch EventSub event `channel.update`.
