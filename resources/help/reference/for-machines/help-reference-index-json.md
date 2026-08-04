# help-reference-index.json

The entire reference - every template tag, EventSub event, EventSub tag, and foreach loop field - as
one JSON array:

**<https://overlabels.com/help-reference-index.json>**

No auth, no key, nothing to sign up for. Build your own frontend over it, feed it to an editor's
autocomplete, or load it into an assistant's context. It is a static file that changes rarely, so
cache it rather than fetching it on every keystroke.

## Shape

```json
[
  {
    "category": "template-tags",
    "categoryLabel": "Template Tags",
    "slug": "channel_followers",
    "title": "channel_followers",
    "body": "# channel_followers\n\nThe total number of ..."
  }
]
```

| Field | Meaning |
|---|---|
| `category` | Directory slug. One of `template-tags`, `eventsub-tags`, `eventsub-events`, `foreach-loops`, `integration-controls`, `for-machines`. |
| `categoryLabel` | Human label for that category. |
| `slug` | URL segment. For `template-tags` this is also the tag name, so `[[[<slug>]]]` is a valid tag. |
| `title` | First `#` heading of the body, falling back to a humanised slug. |
| `body` | The full markdown source of the entry. |

Each entry maps to a page at `https://overlabels.com/help/reference/{category}/{slug}`.

The file is regenerated from the markdown sources, so it is always a mirror of what the site serves.
It is the same data the fuzzy search on this page runs against.

The `integration-controls` category is itself generated, one step further back: it is emitted from the
external service drivers in PHP rather than hand-written, so the control keys listed for Ko-fi,
StreamLabs, Fourthwall, Buy Me a Coffee, Throne and Overlabels GPS are the keys the
running app actually provisions. Connecting a new service adds its entries with no separate
documentation step.

For the template language itself rather than the tag catalogue, read [[llms-txt]]. For the prose help
pages, see [[markdown-endpoints]].
