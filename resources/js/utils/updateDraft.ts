/**
 * Reads a /changeblog draft pasted whole into the /updates editor's Body:
 *
 *     Title: ...
 *     Excerpt: ...
 *     Tags: a, b, c
 *     Slug: ...            (optional)
 *
 *     ---
 *
 *     <the body, which may open with its own CTA frontmatter block>
 *
 * The field block is `Key: value` lines at the very top, ended by the first
 * line that is exactly `---`. Everything after that line is the body,
 * untouched - including a CTA block's own `---` lines.
 */
export interface UpdateDraft {
  title?: string;
  excerpt?: string;
  tags?: string[];
  slug?: string;
  body: string;
}

const FIELDS = ['title', 'excerpt', 'tags', 'slug'] as const;

/** The draft's fields and body, or null when the text does not open with a field block. */
export function parseUpdateDraft(text: string): UpdateDraft | null {
  const lines = text.replace(/\r\n?/g, '\n').split('\n');
  const draft: UpdateDraft = { body: '' };
  let found = false;

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trim();

    if (line === '---') {
      if (!found) return null;
      draft.body = lines
        .slice(i + 1)
        .join('\n')
        .replace(/^\n+/, '');
      return draft;
    }
    if (line === '') continue;

    const match = /^([A-Za-z]+):\s*(.*)$/.exec(line);
    const key = match?.[1].toLowerCase() as (typeof FIELDS)[number] | undefined;
    if (!match || !key || !FIELDS.includes(key)) return null;

    // "Slug: x (only when ...)" - the skill's own hint text is not part of a slug.
    const value = match[2].trim();
    found = true;
    if (key === 'tags') {
      draft.tags = value
        .split(',')
        .map((t) => t.trim())
        .filter(Boolean);
    } else if (key === 'slug') {
      draft.slug = value.split(/\s/)[0] ?? '';
    } else {
      draft[key] = value;
    }
  }

  return null;
}
