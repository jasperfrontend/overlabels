/**
 * Client-side half of the image upload contract.
 *
 * Everything here MUST agree with `ImageUploadController::upload()`. The
 * server is still the authority and re-checks all of it; the point of
 * duplicating the rules is that the user finds out in a millisecond instead of
 * after uploading 31.6 MB that was never going to be accepted.
 *
 * Kept as pure functions rather than living in the component so the rules can
 * be tested directly. See imageUpload.test.ts.
 */

/**
 * Laravel's `max:10240` is 10240 KILOBYTES, so the byte ceiling is 10240 * 1024
 * and not 10_000_000. Getting that wrong by a rounding factor would let a file
 * pass here and fail server-side, which is the exact confusion this module
 * exists to remove.
 */
export const MAX_UPLOAD_BYTES = 10240 * 1024;

/**
 * Mirrors `mimes:jpeg,jpg,png,webp,gif` server-side.
 *
 * BMP is absent on purpose and has been since April 2026. It is the format
 * Windows hands you for an uncompressed 4K desktop capture, which is tens of
 * megabytes for an image a PNG stores in a fraction of that.
 *
 * This does NOT affect pasting a screenshot. Browsers normalise clipboard
 * bitmap data to PNG before exposing it through `getAsFile()`, so a pasted
 * capture arrives as `image/png`. Dragging a `.bmp` off disk is a real BMP and
 * is what gets refused.
 */
export const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'] as const;

/** Value for an `<input type="file">` accept attribute, so the OS picker filters too. */
export const ACCEPT_ATTRIBUTE = ACCEPTED_IMAGE_TYPES.join(',');

/** The minimum the server enforces, repeated here only for the message text. */
export const MIN_DIMENSION = 400;

/** Structural stand-in for `File`, so these can be tested without the DOM. */
export interface UploadCandidate {
  name: string;
  type: string;
  size: number;
}

/**
 * Human-readable size. Deliberately coarse: this only ever appears in an error
 * message, where "31.6 MB" communicates and "33,177,654 bytes" does not.
 */
export function formatBytes(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes < 0) return '0 B';
  if (bytes < 1024) return `${bytes} B`;

  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unit = 0;

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit++;
  }

  // One decimal below 10 ("9.4 MB"), none above ("11 MB") - the extra digit
  // stops mattering once the number is big enough to read at a glance.
  return `${value < 10 ? value.toFixed(1) : Math.round(value)} ${units[unit]}`;
}

/**
 * Returns an error message, or null when the file is worth sending.
 *
 * An empty `type` is deliberately allowed through. Some browsers and some
 * filesystems hand over a File with no MIME at all, and refusing on a missing
 * guess would block legitimate uploads to enforce a rule the server is about
 * to apply properly anyway. Being stricter than the server on weaker evidence
 * is the wrong trade.
 */
export function validateImageFile(file: UploadCandidate): string | null {
  if (file.type && !(ACCEPTED_IMAGE_TYPES as readonly string[]).includes(file.type)) {
    if (!file.type.startsWith('image/')) {
      return 'That file is not an image. Use a JPEG, PNG, WebP or GIF.';
    }

    const label = file.type.slice('image/'.length).toUpperCase();
    return `${label} images are not supported. Use JPEG, PNG, WebP or GIF.`;
  }

  if (file.size > MAX_UPLOAD_BYTES) {
    return `That image is ${formatBytes(file.size)}. The limit is ${formatBytes(MAX_UPLOAD_BYTES)}.`;
  }

  if (file.size === 0) {
    return 'That file is empty.';
  }

  return null;
}

/**
 * Turn a failed upload response into something a person can act on.
 *
 * `payload` is whatever came back parsed, or null when the body was not JSON at
 * all. That second case is the one worth caring about: it is what produced
 * "JSON.parse: unexpected character at line 1 column 1" in a user's face, when
 * the honest answer was "that file is too large".
 */
export function uploadErrorMessage(status: number, statusText: string, payload: unknown): string {
  const body = (payload ?? {}) as {
    error?: string;
    message?: string;
    errors?: Record<string, string[] | undefined>;
  };

  const validation = body.errors?.image?.[0] ?? body.errors?.kind?.[0];
  if (validation) return validation;
  if (body.error) return body.error;

  // Status-specific text beats echoing a server message, which for these is
  // either absent or written for a developer ("CSRF token mismatch").
  switch (status) {
    case 413:
      return `That file is too large to upload. The limit is ${formatBytes(MAX_UPLOAD_BYTES)}.`;
    case 419:
      return 'Your session expired. Reload the page and try again.';
    case 429:
      return 'Too many uploads in a short time. Wait a minute and try again.';
    case 401:
    case 403:
      return 'You are not signed in any more. Reload the page and try again.';
  }

  if (body.message) return body.message;

  return statusText ? `Upload failed: ${statusText}` : `Upload failed (HTTP ${status}).`;
}

/**
 * Parse a response body as JSON without ever letting the parser error escape.
 *
 * Reads text first, so a body that is not JSON - an HTML error page, or valid
 * JSON with a PHP warning glued to the front of it - returns null instead of
 * throwing something unreadable at the user.
 */
export async function parseJsonSafely(response: { text: () => Promise<string> }): Promise<unknown> {
  try {
    const text = await response.text();
    if (!text) return null;
    return JSON.parse(text) as unknown;
  } catch {
    return null;
  }
}
