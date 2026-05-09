dump the current iteration item as pretty-printed JSON :: a debugging helper for inspecting the shape of an iterable while authoring a template.

### Usage

Inside a `foreach` loop body, use `[[[raw]]]`. It outputs `JSON.stringify(item, null, 2)` of whatever the current alias resolves to. Pipe formatters (e.g. `| upper`, `| number`) are ignored.

```
[[[foreach:event.choices as choice]]]
  <pre>[[[raw]]]</pre>
[[[endforeach]]]
```

### Notes

- Only valid inside a `foreach` body :: outside a loop, `[[[raw]]]` is left untouched.
- Any `[` / `]` in the JSON output are escaped to `&#91;` / `&#93;` so a stray `[[[...]]]` in the data can't re-enter the outer tag substitution pass. Browsers still render them as literal brackets.
- Intended for template authoring / debugging; remove before shipping an overlay.

### Example payload
```
{
  "user_name": "user1",
  "user_profile_image_url": "https://static-cdn.jtvnw.net/jtv_user_pictures/uuid-profile_image-300x300.png"
}

{
  "user_name": "user2",
  "user_profile_image_url": "https://static-cdn.jtvnw.net/jtv_user_pictures/uuid-profile_image-300x300.png"
}

{
  "user_name": "user3",
  "user_profile_image_url": "https://static-cdn.jtvnw.net/jtv_user_pictures/uuid-profile_image-300x300.png"
}
```
