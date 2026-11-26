import { describe, expect, it } from 'vitest';
import { parseUpdateDraft } from './updateDraft';

const draft = `Title: The Overlabels Overload Report: your busiest moments
Excerpt: When your stream gets busy, Overlabels writes it all down.
Tags: reports, whatsnew, twitch

---

---
route: settings.overload-report
label: Open your Overload Report settings
---

You get raided.

## What it is

A timeline.
`;

describe('parseUpdateDraft', () => {
  it('fills the fields and keeps the body, CTA frontmatter included', () => {
    const parsed = parseUpdateDraft(draft);

    expect(parsed).toEqual({
      title: 'The Overlabels Overload Report: your busiest moments',
      excerpt: 'When your stream gets busy, Overlabels writes it all down.',
      tags: ['reports', 'whatsnew', 'twitch'],
      body: '---\nroute: settings.overload-report\nlabel: Open your Overload Report settings\n---\n\nYou get raided.\n\n## What it is\n\nA timeline.\n',
    });
  });

  it('reads a slug without the hint text after it, and Windows line endings', () => {
    const parsed = parseUpdateDraft('Title: T\r\nSlug: my-post (only when ugly)\r\n---\r\nBody');
    expect(parsed?.slug).toBe('my-post');
    expect(parsed?.body).toBe('Body');
  });

  it('returns null for a plain body, so nothing gets overwritten', () => {
    expect(parseUpdateDraft('Just a post.\n\n---\n\nMore.')).toBeNull();
    expect(parseUpdateDraft('---\nroute: updates.index\n---\nBody')).toBeNull();
    expect(parseUpdateDraft('Title: no separator, so no body')).toBeNull();
    expect(parseUpdateDraft('Note: not a field\n---\nBody')).toBeNull();
  });
});
