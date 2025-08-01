<?php

namespace App\Services;

use App\Services\DefaultTemplateProviderService;
use Illuminate\Support\Facades\Log;

/**
 * OverlayTemplateParserService
 *
 * Handles parsing of overlay templates with [[[template_tags]]] syntax.
 * This uses DefaultTemplateProviderService for consistent default templates.
 */
class OverlayTemplateParserService
{

    private function parseConditionalBlocks(string $template, array $data): string
    {
        // Pattern that handles BOTH simple truthy AND comparison operators
        $pattern = '/\[\[\[if:([a-zA-Z0-9_]+)(?:\s*(>=|<=|>|<|==|!=)\s*([a-zA-Z0-9_]+|\d+))?]]](.*?)(?:\[\[\[else]]](.*?))?\[\[\[endif]]]/s';

        return preg_replace_callback($pattern, function($matches) use ($data) {
            $conditionTag = $matches[1];
            $operator = $matches[2] ?? null;
            $compareValue = $matches[3] ?? null;
            $ifContent = $matches[4];
            $elseContent = $matches[5] ?? '';

            $tagValue = $this->getTagValue($data, $conditionTag);

            // Handle comparison versus a simple truthy check
            if ($operator && $compareValue !== null) {
                // Comparison mode: [[[if:tag==value]]]
                $rightValue = is_numeric($compareValue) ? (float)$compareValue : $compareValue;
                $result = $this->evaluateComparison($tagValue, $operator, $rightValue);
            } else {
                // Simple truthy mode: [[[if:tag]]]
                $result = $this->isTruthy($tagValue);
            }

            return $result ? $ifContent : $elseContent;
        }, $template);
    }

    private function evaluateComparison($leftValue, string $operator, $rightValue): bool
    {
        // Handle string equality/inequality comparisons
        if ($operator === '==' || $operator === '!=') {
            // Convert both values to strings for comparison
            $leftStr = (string)$leftValue;
            $rightStr = (string)$rightValue;

            return match ($operator) {
                '==' => $leftStr === $rightStr,
                '!=' => $leftStr !== $rightStr,
            };
        }

        // Handle numeric comparisons (>, >=, <, <=)
        if (in_array($operator, ['>', '>=', '<', '<='])) {
            // Only proceed if both values can be treated as numbers
            if (!is_numeric($leftValue) || !is_numeric($rightValue)) {
                return false; // Non-numeric values can't use numeric operators
            }

            $leftNum = (float)$leftValue;
            $rightNum = (float)$rightValue;

            return match ($operator) {
                '>' => $leftNum > $rightNum,
                '>=' => $leftNum >= $rightNum,
                '<' => $leftNum < $rightNum,
                '<=' => $leftNum <= $rightNum,
            };
        }

        return false;
    }

    /**
     * Get the value for a template tag from the data array
     * Handles both simple tags and nested dot notation
     */
    private function getTagValue(array $data, string $tagName)
    {
        // First, try direct key lookup (the most common case)
        if (isset($data[$tagName])) {
            return $data[$tagName];
        }

        // Handle nested data using dot notation (like your existing method)
        if (str_contains($tagName, '.')) {
            return $this->getNestedValue($data, $tagName);
        }

        // Handle array access patterns like "data.0.field_name"
        // This matches your TemplateDataMapperService logic
        if (preg_match('/^([a-zA-Z_]+)\.(\d+)\.([a-zA-Z_]+)$/', $tagName, $matches)) {
            $arrayKey = $matches[1];      // e.g., "subscribers"
            $index = (int)$matches[2];    // e.g., 0 for latest
            $fieldKey = $matches[3];      // e.g., "user_name"

            if (isset($data[$arrayKey][$index]) && is_array($data[$arrayKey])) {
                $item = $data[$arrayKey][$index];
                return $item[$fieldKey] ?? null;
            }
        }

        // Handle standardized template tag names
        // This integrates with your TemplateDataMapperService mappings
        $standardizedValue = $this->getStandardizedTagValue($data, $tagName);
        if ($standardizedValue !== null) {
            return $standardizedValue;
        }

        // Tag isn't found - return null for conditional evaluation
        return null;
    }

    /**
     * Get standardized tag values using the TemplateDataMapperService
     * This ensures consistency with your existing template mapping logic
     */
    private function getStandardizedTagValue(array $data, string $tagName)
    {
        // Get the template data mapper service
        $templateDataMapper = app(\App\Services\TemplateDataMapperService::class);

        // Transform the raw Twitch data using your existing mapping logic
        $mappedData = $templateDataMapper->mapTwitchDataForTemplates($data, 'overlay');

        // Return the mapped value if it exists
        return $mappedData[$tagName] ?? null;
    }

    private function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return $value > 0;
        }

        if (is_string($value)) {
            return !empty(trim($value)) && strtolower($value) !== 'false';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return !empty($value);
    }

    private function parseLoopBlocks(string $template, array $data): string
    {
        // Match [[[foreach:array_name]]] content [[[endforeach]]] blocks
        $pattern = '/\[\[\[foreach:([a-zA-Z0-9_]+)]]](.*?)\[\[\[endforeach]]]/s';

        return preg_replace_callback($pattern, function($matches) use ($data) {
            $arrayTag = $matches[1];
            $loopContent = $matches[2];

            $arrayData = $this->getTagValue($data, $arrayTag);

            if (!is_array($arrayData)) {
                return ''; // Not an array, return empty
            }

            $output = '';
            foreach ($arrayData as $index => $item) {
                // Create context for this loop iteration
                $loopData = array_merge($data, [
                    'index' => $index,
                    'item' => $item
                ], $item); // Merge item data directly

                // Parse the loop content with loop-specific data
                $output .= $this->parseTemplate($loopContent, $loopData);
            }

            return $output;
        }, $template);
    }

    /**
     * Parse a template string, replacing [[[template_tags]]] with actual data
     */
    public function parseTemplate(string $template, array $data): string
    {
        // STEP 1: Parse block-level constructs first (if/foreach/etc.)
        $template = $this->parseConditionalBlocks($template, $data);
        $template = $this->parseLoopBlocks($template, $data);

        // Find all template tags in the format [[[tag_name]]]
        $pattern = '/\[\[\[([a-zA-Z0-9_]+)]]]/';

        return preg_replace_callback($pattern, function($matches) use ($data) {
            $tagName = $matches[1];

            // Check if the tag exists in our data
            if (isset($data[$tagName])) {
                return $this->sanitizeOutput($data[$tagName]);
            }

            // Handle nested data (like channel.broadcaster_name)
            if (str_contains($tagName, '.')) {
                $value = $this->getNestedValue($data, $tagName);
                if ($value !== null) {
                    return $this->sanitizeOutput($value);
                }
            }

            // Return empty string for unknown or unparsed template tags
            // @TODO: have this return something better when a currently not-existing debug mode is enabled.
            return '';
        }, $template);
    }

    /**
     * Parse template with debug information
     */
    public function parseTemplateWithDebug(string $template, array $data): array
    {
        $debugInfo = [
            'tags_found' => [],
            'tags_replaced' => [],
            'tags_missing' => [],
            'original_template_size' => strlen($template)
        ];

        // Find all template tags
        $pattern = '/\[\[\[([a-zA-Z0-9_]+)]]]/';
        preg_match_all($pattern, $template, $matches);

        $debugInfo['tags_found'] = array_unique($matches[1]);

        // Parse template and track replacements
        $parsedTemplate = preg_replace_callback($pattern, function($matches) use ($data, &$debugInfo) {
            $tagName = $matches[1];

            if (isset($data[$tagName])) {
                $debugInfo['tags_replaced'][] = $tagName;
                return $this->sanitizeOutput($data[$tagName]);
            }

            // Handle nested data
            if (str_contains($tagName, '.')) {
                $value = $this->getNestedValue($data, $tagName);
                if ($value !== null) {
                    $debugInfo['tags_replaced'][] = $tagName;
                    return $this->sanitizeOutput($value);
                }
            }

            $debugInfo['tags_missing'][] = $tagName;
            return '';
        }, $template);

        $debugInfo['parsed_template_size'] = strlen($parsedTemplate);
        $debugInfo['tags_replaced'] = array_unique($debugInfo['tags_replaced']);
        $debugInfo['tags_missing'] = array_unique($debugInfo['tags_missing']);

        return [
            'parsed_template' => $parsedTemplate,
            'debug_info' => $debugInfo
        ];
    }

    /**
     * Validate template syntax and structure
     */
    public function validateTemplate(string $template): array
    {
        $validation = [
            'is_valid' => true,
            'syntax_issues' => [],
            'warnings' => [],
            'tags_found' => []
        ];

        // Check for basic HTML structure if it looks like HTML
        if (str_contains($template, '<html') || str_contains($template, '<!DOCTYPE')) {
            // This looks like a complete HTML document
            if (!str_contains($template, '<head>')) {
                $validation['warnings'][] = 'HTML document missing <head> section';
            }
            if (!str_contains($template, '<body>')) {
                $validation['warnings'][] = 'HTML document missing <body> section';
            }
        }

        // Check for malformed template tags
        $pattern = '/\[\[\[([a-zA-Z0-9_]*)]]]/';
        preg_match_all($pattern, $template, $matches);

        $validation['tags_found'] = array_unique($matches[1]);

        // Check for incomplete tags
        if (preg_match('/\[\[\[[^]]*$/', $template)) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Incomplete template tag found - make sure all tags end with ]]]';
        }

        // Check for nested tags (not supported)
        if (preg_match('/\[\[\[[^]]*\[\[\[/', $template)) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Nested template tags are not supported';
        }

        // Check for empty tags
        if (preg_match('/\[\[\[]]]/', $template)) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Empty template tags found - [[[tagname]]] format required';
        }

        // Warn about potential XSS
        if (str_contains($template, '<script')) {
            $validation['warnings'][] = 'JavaScript found in template - ensure data is properly sanitized';
        }

        // Check for malformed comparisons
        if (preg_match('/\[\[\[if:[^><=!]*[><=!][^0-9]/', $template)) {
            $validation['syntax_issues'][] = 'Invalid comparison operator usage';
        }

        return $validation;
    }

    /**
     * Helper: Get nested value from an array using dot notation
     */
    private function getNestedValue(array $data, string $key)
    {
        $keys = explode('.', $key);
        $value = $data;

        foreach ($keys as $nestedKey) {
            if (is_array($value) && isset($value[$nestedKey])) {
                $value = $value[$nestedKey];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Helper: Sanitize output to prevent XSS
     */
    private function sanitizeOutput($value): string
    {
        // Handle different data types
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
