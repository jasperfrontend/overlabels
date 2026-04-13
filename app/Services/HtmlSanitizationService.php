<?php

namespace App\Services;

class HtmlSanitizationService
{
    /** Attributes that can carry dangerous URIs. */
    private const URI_ATTRS = 'href|action|src|data|formaction|xlink:href';

    /**
     * Sanitize an HTML string by stripping dangerous constructs:
     * - <script> tags (including content)
     * - Inline event handlers (on*)
     * - javascript: URIs (plain and HTML-entity-encoded)
     * - Interactive/embeddable elements: <form>, <button>, <input>, <textarea>, <select>, <object>, <iframe>, <embed>
     * - <meta http-equiv="refresh"> with javascript:/data: URIs
     * - javascript: inside CSS url()
     *
     * Overlays intentionally render raw HTML, so we strip only known-dangerous
     * patterns rather than allowlisting tags.
     */
    public static function sanitize(string $value): string
    {
        // Strip <script> tags and their content
        $value = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script\s*>/i', '', $value);

        // Strip inline event handlers (onclick, onload, onerror, etc.)
        $value = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $value);

        // Strip javascript: URIs in dangerous attributes (plain text)
        $attrs = self::URI_ATTRS;
        $value = preg_replace(
            '/\s+(?:'.$attrs.')\s*=\s*(?:"javascript\s*:[^"]*"|\'javascript\s*:[^\']*\'|javascript\s*:[^\s>]+)/i',
            '',
            $value,
        );

        // Strip HTML-entity-encoded javascript: URIs in dangerous attributes.
        // Browsers decode &#106;&#97;&#118;... back to "javascript:" before
        // interpreting, so we must catch the encoded form too.
        $value = preg_replace_callback(
            '/(\s+(?:'.$attrs.')\s*=\s*)("[^"]*"|\'[^\']*\')/i',
            function (array $m): string {
                $decoded = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $inner = trim($decoded, '"\'');
                if (preg_match('/^\s*javascript\s*:/i', $inner)) {
                    return ''; // strip the entire attribute
                }

                return $m[0]; // keep safe attributes unchanged
            },
            $value,
        );

        // Strip interactive/input elements - overlays are display-only
        $value = preg_replace('/<form\b[^>]*>[\s\S]*?<\/form\s*>/i', '', $value);
        $value = preg_replace('/<button\b[^>]*>[\s\S]*?<\/button\s*>/i', '', $value);
        $value = preg_replace('/<textarea\b[^>]*>[\s\S]*?<\/textarea\s*>/i', '', $value);
        $value = preg_replace('/<object\b[^>]*>[\s\S]*?<\/object\s*>/i', '', $value);
        $value = preg_replace('/<input\b[^>]*\/?>/i', '', $value);
        $value = preg_replace('/<select\b[^>]*>[\s\S]*?<\/select\s*>/i', '', $value);
        $value = preg_replace('/<iframe\b[^>]*>[\s\S]*?<\/iframe\s*>/i', '', $value);
        $value = preg_replace('/<iframe\b[^>]*\/?>/i', '', $value); // self-closing
        $value = preg_replace('/<embed\b[^>]*\/?>/i', '', $value);

        // Strip <meta http-equiv="refresh"> tags with javascript: or data: URIs
        $value = preg_replace(
            '/<meta\s[^>]*http-equiv\s*=\s*["\']?refresh["\']?[^>]*content\s*=\s*["\'][^"\']*url\s*=\s*(?:javascript|data)\s*:[^"\']*["\'][^>]*>/i',
            '',
            $value,
        );

        // Strip javascript: inside CSS url() expressions.
        // The value may be wrapped in quotes: url('javascript:...') or url("javascript:...")
        return preg_replace(
            '/url\s*\(\s*["\']?\s*javascript\s*:[^)]*["\']?\s*\)/i',
            'url(about:blank)',
            $value,
        );
    }

    /**
     * Sanitize specific fields in a validated data array.
     * Only processes the html, head, css, and description fields.
     */
    public static function sanitizeTemplateFields(array $validated): array
    {
        $fieldsToSanitize = ['html', 'head', 'css', 'description'];

        foreach ($fieldsToSanitize as $field) {
            if (isset($validated[$field])) {
                $validated[$field] = static::sanitize($validated[$field]);
            }
            // ConvertEmptyStringsToNull turns "" into null, but the DB column
            // is NOT NULL. Coalesce back to empty string.
            if (array_key_exists($field, $validated) && $validated[$field] === null) {
                $validated[$field] = '';
            }
        }

        return $validated;
    }
}
