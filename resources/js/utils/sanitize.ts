const SCRIPT_TAG_PATTERN = /<script\b[^>]*>[\s\S]*?<\/script\s*>/gi;

/**
 * Strips interactive/input elements entirely. Overlays are display-only
 * and should never contain form controls or embeddable objects.
 */
const FORM_TAG_PATTERN = /<form\b[^>]*>[\s\S]*?<\/form\s*>/gi;
const BUTTON_TAG_PATTERN = /<button\b[^>]*>[\s\S]*?<\/button\s*>/gi;
const TEXTAREA_TAG_PATTERN = /<textarea\b[^>]*>[\s\S]*?<\/textarea\s*>/gi;
const OBJECT_TAG_PATTERN = /<object\b[^>]*>[\s\S]*?<\/object\s*>/gi;
const SELECT_TAG_PATTERN = /<select\b[^>]*>[\s\S]*?<\/select\s*>/gi;
const INPUT_TAG_PATTERN = /<input\b[^>]*\/?>/gi;
const IFRAME_TAG_PATTERN = /<iframe\b[^>]*>[\s\S]*?<\/iframe\s*>/gi;
const IFRAME_SELFCLOSE_PATTERN = /<iframe\b[^>]*\/?>/gi;
const EMBED_TAG_PATTERN = /<embed\b[^>]*\/?>/gi;

/**
 * Strips inline event-handler attributes (onclick, onload, onerror, etc.)
 * from HTML tags. Matches on<word>="..." or on<word>='...' or unquoted.
 */
const EVENT_HANDLER_PATTERN = /\s+on\w+\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+)/gi;

/**
 * Strips attributes whose value starts with javascript: (href, action, src,
 * data, formaction, xlink:href, etc.). Handles optional whitespace and mixed
 * case around the colon.
 */
const URI_ATTRS = 'href|action|src|data|formaction|xlink:href';

const JAVASCRIPT_URI_PATTERN = new RegExp(
    `\\s+(?:${URI_ATTRS})\\s*=\\s*(?:"javascript\\s*:[^"]*"|'javascript\\s*:[^']*'|javascript\\s*:[^\\s>]+)`,
    'gi',
);

/**
 * Matches dangerous attributes whose values may contain HTML-entity-encoded
 * javascript: URIs. We decode and check in a callback.
 */
const ENCODED_URI_PATTERN = new RegExp(
    `(\\s+(?:${URI_ATTRS})\\s*=\\s*)("[^"]*"|'[^']*')`,
    'gi',
);

/**
 * Matches <meta http-equiv="refresh"> with javascript: or data: in the URL.
 */
const META_REFRESH_PATTERN =
    /<meta\s[^>]*http-equiv\s*=\s*["']?refresh["']?[^>]*content\s*=\s*["'][^"']*url\s*=\s*(?:javascript|data)\s*:[^"']*["'][^>]*>/gi;

/**
 * Matches javascript: inside CSS url() expressions.
 */
const CSS_JS_URL_PATTERN = /url\s*\(\s*["']?\s*javascript\s*:[^)]*["']?\s*\)/gi;

/**
 * Decode HTML entities (decimal &#106; and hex &#x6A; forms) in a string.
 * Only handles numeric entities since those are the encoding trick.
 */
function decodeHtmlEntities(str: string): string {
    return str.replace(/&#x([0-9a-f]+);/gi, (_, hex) => String.fromCodePoint(parseInt(hex, 16)))
        .replace(/&#(\d+);/g, (_, dec) => String.fromCodePoint(parseInt(dec, 10)));
}

/**
 * Sanitize a single HTML string by stripping dangerous constructs:
 * - <script> tags (including content)
 * - Interactive/embeddable elements: <form>, <button>, <input>, <textarea>, <select>, <object>, <iframe>, <embed>
 * - Inline event handlers (on*)
 * - javascript: URIs (plain and HTML-entity-encoded)
 * - <meta http-equiv="refresh"> with javascript:/data: URIs
 * - javascript: inside CSS url()
 *
 * Returns { value, removed } where removed is the total number of
 * dangerous constructs that were stripped.
 */
export function sanitizeHtml(value: string): { value: string; removed: number } {
    let removed = 0;

    const countAndReplace = (input: string, pattern: RegExp): string => {
        // Reset lastIndex for stateful global regexes
        pattern.lastIndex = 0;
        const matches = input.match(pattern);
        if (matches) removed += matches.length;
        // Reset again before replace (match advances lastIndex)
        pattern.lastIndex = 0;
        return input.replace(pattern, '');
    };

    let result = countAndReplace(value, SCRIPT_TAG_PATTERN);
    result = countAndReplace(result, FORM_TAG_PATTERN);
    result = countAndReplace(result, BUTTON_TAG_PATTERN);
    result = countAndReplace(result, TEXTAREA_TAG_PATTERN);
    result = countAndReplace(result, OBJECT_TAG_PATTERN);
    result = countAndReplace(result, SELECT_TAG_PATTERN);
    result = countAndReplace(result, INPUT_TAG_PATTERN);
    result = countAndReplace(result, IFRAME_TAG_PATTERN);
    result = countAndReplace(result, IFRAME_SELFCLOSE_PATTERN);
    result = countAndReplace(result, EMBED_TAG_PATTERN);
    result = countAndReplace(result, EVENT_HANDLER_PATTERN);
    result = countAndReplace(result, JAVASCRIPT_URI_PATTERN);

    // Catch HTML-entity-encoded javascript: URIs (e.g. &#106;avascript:)
    ENCODED_URI_PATTERN.lastIndex = 0;
    result = result.replace(ENCODED_URI_PATTERN, (match, _prefix, attrValue) => {
        const decoded = decodeHtmlEntities(attrValue);
        const inner = decoded.replace(/^['"]|['"]$/g, '').trim();
        if (/^\s*javascript\s*:/i.test(inner)) {
            removed++;
            return '';
        }
        return match;
    });

    // Strip <meta http-equiv="refresh"> with javascript:/data: URIs
    result = countAndReplace(result, META_REFRESH_PATTERN);

    // Strip javascript: inside CSS url()
    CSS_JS_URL_PATTERN.lastIndex = 0;
    const cssMatches = result.match(CSS_JS_URL_PATTERN);
    if (cssMatches) removed += cssMatches.length;
    CSS_JS_URL_PATTERN.lastIndex = 0;
    result = result.replace(CSS_JS_URL_PATTERN, 'url(about:blank)');

    return { value: result, removed };
}

/**
 * Applies sanitizeHtml to every string field in a plain object.
 * Returns the sanitized fields and the total number of dangerous
 * constructs removed.
 */
export function sanitizeHtmlFields<T extends Record<string, unknown>>(fields: T): { sanitized: T; removed: number } {
    let removed = 0;

    const sanitized = Object.fromEntries(
        Object.entries(fields).map(([key, value]) => {
            if (typeof value !== 'string') return [key, value];
            const result = sanitizeHtml(value);
            removed += result.removed;
            return [key, result.value];
        }),
    ) as T;

    return { sanitized, removed };
}
