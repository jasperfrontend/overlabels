const SCRIPT_TAG_PATTERN = /<script\b[^>]*>[\s\S]*?<\/script\s*>/gi;

/**
 * Strips all <script>...</script> blocks from a string, including any
 * attributes on the opening tag and multiline content.
 */
export function stripScripts(value: string): string {
    return value.replace(SCRIPT_TAG_PATTERN, '');
}

/**
 * Applies stripScripts to every string field in a plain object.
 * Returns the sanitized fields and the total number of script tags removed.
 */
export function stripScriptsFromFields<T extends Record<string, unknown>>(fields: T): { sanitized: T; removed: number } {
    let removed = 0;

    const sanitized = Object.fromEntries(
        Object.entries(fields).map(([key, value]) => {
            if (typeof value !== 'string') return [key, value];
            const matches = value.match(SCRIPT_TAG_PATTERN);
            if (matches) removed += matches.length;
            return [key, value.replace(SCRIPT_TAG_PATTERN, '')];
        }),
    ) as T;

    return { sanitized, removed };
}
