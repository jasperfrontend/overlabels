import { describe, expect, it } from 'vitest';
import {
  ACCEPTED_IMAGE_TYPES,
  ACCEPT_ATTRIBUTE,
  MAX_UPLOAD_BYTES,
  formatBytes,
  parseJsonSafely,
  uploadErrorMessage,
  validateImageFile,
} from './imageUpload';

function candidate(overrides: Partial<{ name: string; type: string; size: number }> = {}) {
  return { name: 'shot.png', type: 'image/png', size: 500_000, ...overrides };
}

describe('MAX_UPLOAD_BYTES', () => {
  it("matches Laravel's max:10240, which is kilobytes not bytes", () => {
    // 10240 KB, so 10485760 bytes. Reading the rule as 10_000_000 would let
    // files through here that the server then refuses.
    expect(MAX_UPLOAD_BYTES).toBe(10_485_760);
  });
});

describe('ACCEPTED_IMAGE_TYPES', () => {
  it('mirrors the server allowlist exactly', () => {
    expect([...ACCEPTED_IMAGE_TYPES]).toEqual(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
  });

  it('excludes BMP', () => {
    // The format a 4K Windows desktop capture arrives in uncompressed. Pasting
    // a screenshot is unaffected: browsers hand over clipboard bitmaps as PNG.
    expect([...ACCEPTED_IMAGE_TYPES]).not.toContain('image/bmp');
  });

  it('builds an accept attribute the file picker can use', () => {
    expect(ACCEPT_ATTRIBUTE).toBe('image/jpeg,image/png,image/webp,image/gif');
  });
});

describe('formatBytes', () => {
  it('formats the sizes that actually show up in messages', () => {
    expect(formatBytes(33_177_654)).toBe('32 MB');
    expect(formatBytes(MAX_UPLOAD_BYTES)).toBe('10 MB');
    expect(formatBytes(5_400_000)).toBe('5.1 MB');
    expect(formatBytes(2048)).toBe('2.0 KB');
    expect(formatBytes(512)).toBe('512 B');
  });

  it('does not produce NaN for junk input', () => {
    expect(formatBytes(Number.NaN)).toBe('0 B');
    expect(formatBytes(-1)).toBe('0 B');
  });
});

describe('validateImageFile', () => {
  it('accepts every allowed type', () => {
    for (const type of ACCEPTED_IMAGE_TYPES) {
      expect(validateImageFile(candidate({ type }))).toBeNull();
    }
  });

  it('rejects a BMP by name so the reason is obvious', () => {
    const message = validateImageFile(candidate({ name: 'Desktop Background.bmp', type: 'image/bmp' }));

    expect(message).toBe('BMP images are not supported. Use JPEG, PNG, WebP or GIF.');
  });

  it('rejects the real 31.6 MB bitmap before it can be uploaded', () => {
    const message = validateImageFile(candidate({ type: 'image/bmp', size: 33_177_654 }));

    // Type is checked first: the more specific reason wins over "too large".
    expect(message).toContain('BMP');
  });

  it('reports size in a form a person can act on', () => {
    const message = validateImageFile(candidate({ type: 'image/png', size: 33_177_654 }));

    expect(message).toBe('That image is 32 MB. The limit is 10 MB.');
  });

  it('allows a file exactly on the limit and refuses one byte over', () => {
    expect(validateImageFile(candidate({ size: MAX_UPLOAD_BYTES }))).toBeNull();
    expect(validateImageFile(candidate({ size: MAX_UPLOAD_BYTES + 1 }))).not.toBeNull();
  });

  it('rejects a non-image outright rather than calling it an "APPLICATION/PDF image"', () => {
    expect(validateImageFile(candidate({ name: 'x.pdf', type: 'application/pdf' }))).toBe('That file is not an image. Use a JPEG, PNG, WebP or GIF.');
  });

  it('rejects an empty file', () => {
    expect(validateImageFile(candidate({ size: 0 }))).toBe('That file is empty.');
  });

  it('lets a file with no MIME type through to the server', () => {
    // Some browsers and filesystems supply no type at all. Refusing on a
    // missing guess would block legitimate uploads to enforce a rule the
    // server applies properly a moment later.
    expect(validateImageFile(candidate({ type: '', size: 500_000 }))).toBeNull();
  });

  it('still applies the size limit when the MIME type is missing', () => {
    expect(validateImageFile(candidate({ type: '', size: 33_177_654 }))).not.toBeNull();
  });
});

describe('parseJsonSafely', () => {
  it('parses an ordinary JSON body', async () => {
    const response = { text: async () => '{"url":"https://images.overlabels.com/a.webp"}' };

    await expect(parseJsonSafely(response)).resolves.toEqual({ url: 'https://images.overlabels.com/a.webp' });
  });

  it('returns null for valid JSON with a PHP warning glued to the front', async () => {
    // The exact shape that produced "JSON.parse: unexpected character at line 1
    // column 1" in production: display_errors=On emits an HTML warning at PHP
    // request startup, before Laravel exists to suppress it.
    const body = '<br />\n<b>Warning</b>:  PHP Request Startup: File upload error<br />\n{"message":"ok"}';

    await expect(parseJsonSafely({ text: async () => body })).resolves.toBeNull();
  });

  it('returns null for an HTML error page and for an empty body', async () => {
    await expect(parseJsonSafely({ text: async () => '<!doctype html><html></html>' })).resolves.toBeNull();
    await expect(parseJsonSafely({ text: async () => '' })).resolves.toBeNull();
  });

  it('returns null instead of throwing when the body cannot be read at all', async () => {
    const response = {
      text: async () => {
        throw new TypeError('network error');
      },
    };

    await expect(parseJsonSafely(response)).resolves.toBeNull();
  });
});

describe('uploadErrorMessage', () => {
  it('prefers the server validation message, which is the most specific', () => {
    const payload = { message: 'The given data was invalid.', errors: { image: ['Image must be at least 400x400px.'] } };

    expect(uploadErrorMessage(422, 'Unprocessable Content', payload)).toBe('Image must be at least 400x400px.');
  });

  it('uses the controller error field for a 500', () => {
    expect(uploadErrorMessage(500, 'Internal Server Error', { error: 'Upload failed. Please try again.' })).toBe('Upload failed. Please try again.');
  });

  it('explains an unparseable 413 without mentioning JSON', () => {
    const message = uploadErrorMessage(413, 'Payload Too Large', null);

    expect(message).toBe('That file is too large to upload. The limit is 10 MB.');
    expect(message).not.toMatch(/JSON/i);
  });

  it('turns an expired session into an instruction rather than "CSRF token mismatch"', () => {
    const message = uploadErrorMessage(419, '', { message: 'CSRF token mismatch.' });

    expect(message).toBe('Your session expired. Reload the page and try again.');
    expect(message).not.toMatch(/CSRF/i);
  });

  it('explains the rate limiter', () => {
    expect(uploadErrorMessage(429, 'Too Many Requests', null)).toBe('Too many uploads in a short time. Wait a minute and try again.');
  });

  it('never returns an empty string, whatever comes back', () => {
    for (const status of [400, 404, 502, 0]) {
      expect(uploadErrorMessage(status, '', null).length).toBeGreaterThan(0);
    }
  });
});
