# Chat Tower controls

Chat Tower provisions 11 controls when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.

Reference them anywhere a tag works, using the `c:tower:` prefix.

## Specific to Chat Tower

| Tag | Type | Default | Holds |
|---|---|---|---|
| `[[[c:tower:tower_height]]]` | number | `0` | Tower Height |
| `[[[c:tower:tower_lean]]]` | number | `0` | Tower Lean |
| `[[[c:tower:tower_room]]]` | number | `4` | Tower Room To Fall |
| `[[[c:tower:last_stacker_name]]]` | text | empty | Last Stacker Name |
| `[[[c:tower:blocks_stacked_this_stream]]]` | counter | `0` | Blocks Stacked This Stream |
| `[[[c:tower:tallest_tower_this_stream]]]` | number | `0` | Tallest Tower This Stream |
| `[[[c:tower:topples_this_stream]]]` | counter | `0` | Topples This Stream |
| `[[[c:tower:last_topple_by]]]` | text | empty | Last Topple By |
| `[[[c:tower:last_topple_height]]]` | number | `0` | Last Topple Height |
| `[[[c:tower:tallest_tower_record]]]` | number | `0` | Tallest Tower Record (all time) |
| `[[[c:tower:blocks_stacked_total]]]` | number | `0` | Blocks Stacked Total (all time) |

## Events that update them

`stack`, `topple`

## Notes

- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.
- Referencing one in a template declares that dependency. Someone who copies the template without Chat Tower connected is warned, not blocked, and it starts working the moment they connect.

---

*Generated from the Chat Tower driver by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*
