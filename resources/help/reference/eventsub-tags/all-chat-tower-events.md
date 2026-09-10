available on the `stack` and `topple` events, fired when a viewer runs `!stack` in chat. `stack` means the block landed and the tower still stands; `topple` means that block brought the tower down.

- `[[[event.user_name]]]` :: Display name of the viewer who placed the block
- `[[[event.user_login]]]` :: Login of the viewer who placed the block
- `[[[event.color]]]` :: The viewer's Twitch chat colour as `#RRGGBB` :: empty when they never picked one
- `[[[event.aim]]]` :: `left`, `right`, or empty for an unaimed `!stack`
- `[[[event.height]]]` :: On `stack`, how tall the tower stands after this block. On `topple`, the height it fell at
- `[[[event.lean]]]` :: Where the top block sits relative to the base, in block widths (a block is 4 wide) :: negative is left
- `[[[event.room]]]` :: How far the top of the sway is from the nearer fall line :: negative on a `topple`
- `[[[event.record]]]` :: `1` when this block took the tower past the all-time record, or when the tower that fell was the record holder :: empty otherwise

Every value arrives as a string. Use `??` for a fallback where a value can be empty: `[[[event.color ?? #ffffff]]]`.

### Only a standing tower counts
The block that topples the tower sets no record and raises no `tallest_*` control - the tower has to stand after a block for it to count. `event.height` on a `topple` is the position of the block that brought it down, which is also what the bot says in chat.

### Tags and controls answer different questions
The event tags are the single block that just landed; the `c:tower:` controls are the running state: `c:tower:tower_height`, `c:tower:tower_lean`, `c:tower:tower_room`, `c:tower:last_stacker_name`, `c:tower:blocks_stacked_this_stream`, `c:tower:tallest_tower_this_stream`, `c:tower:topples_this_stream`, `c:tower:last_topple_by`, `c:tower:last_topple_height`, `c:tower:tallest_tower_record` and `c:tower:blocks_stacked_total`. The tower itself is the `[[[foreach:tower as block]]]` iterable, and the all-time record roster is the `tower_record` List when the Chat Tower product installed one.

example:
```
<div class="tower-alert">
  [[[event.user_name]]] brought the tower down at [[[event.height]]]
  [[[if:event.record]]]<span>and it was the record</span>[[[endif]]]
</div>
```

note: Chat Tower is an Overlabels integration (not Twitch EventSub). Events arrive from the Overlabels bot relaying the `!stack` chat command; there is no public webhook for this service.
