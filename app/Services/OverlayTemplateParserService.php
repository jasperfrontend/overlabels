<?php

namespace App\Services;

use App\Models\TemplateTag;
use Illuminate\Support\Str;

/**
 * OverlayTemplateParserService
 *
 * Handles parsing of overlay templates with [[[template_tags]]] syntax.
 * Integrates with the existing template tag system stored in the database.
 */
class OverlayTemplateParserService
{
    private TemplateDataMapperService $templateDataMapper;

    public function __construct(TemplateDataMapperService $templateDataMapper)
    {
        $this->templateDataMapper = $templateDataMapper;
    }

    /**
     * Parse a template string, replacing [[[template_tags]]] with actual data
     */
    public function parse(string $template, array $data): string
    {
        if (empty($template)) {
            return '';
        }

        // First, handle conditional blocks
        $template = $this->parseConditionalBlocks($template, $data);

        // Then, handle loop blocks
        $template = $this->parseLoopBlocks($template, $data);

        // Finally, parse regular template tags
        return $this->parseTemplateTags($template, $data);
    }

    /**
     * Parse regular template tags [[[tag_name]]]
     */
    private function parseTemplateTags(string $template, array $data): string
    {
        // Get all active template tags from the database for reference
        $dbTags = TemplateTag::where('is_active', true)
            ->pluck('json_path', 'tag_name')
            ->toArray();

        // Pattern to match [[[tag_name]]] with optional transformations
        $pattern = '/\[\[\[([a-zA-Z0-9_]+)(?:\|([a-zA-Z0-9_]+))?]]]/';

        return preg_replace_callback($pattern, function($matches) use ($data, $dbTags) {
            $tagName = $matches[1];
            $transformation = $matches[2] ?? null;

            // First, check if this tag exists in our database
            if (isset($dbTags[$tagName])) {
                $jsonPath = $dbTags[$tagName];
                $value = $this->getValueByPath($data, $jsonPath);
            } else {
                // Try to get value directly if not in the database (for backward compatibility)
                $value = $this->getTagValue($data, $tagName);
            }

            // Apply transformation if specified
            if ($transformation && $value !== null) {
                $value = $this->applyTransformation($value, $transformation);
            }

            // Return sanitized value or empty string if not found
            return $value !== null ? $this->sanitizeOutput($value) : '';
        }, $template);
    }

    /**
     * Get value from a data array using JSON path (e.g., "user.display_name")
     */
    private function getValueByPath(array $data, string $path)
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Parse conditional blocks [[[if:condition]]]...[[[else]]]...[[[endif]]]
     */
    private function parseConditionalBlocks(string $template, array $data): string
    {
        $maxIterations = 10;
        $iteration = 0;

        do {
            $previousTemplate = $template;
            $template = $this->parseSingleConditionalPass($template, $data);
            $iteration++;
        } while ($template !== $previousTemplate && $iteration < $maxIterations);

        return $template;
    }

    /**
     * Parse a single pass of conditional blocks
     */
    private function parseSingleConditionalPass(string $template, array $data): string
    {
        // Pattern for conditionals with optional comparison
        $pattern = '/\[\[\[if:([a-zA-Z0-9_]+)(?:\s*(>=|<=|>|<|==|!=)\s*([a-zA-Z0-9_]+|\d+))?]]]((?:(?!\[\[\[if:).)*?)(?:\[\[\[else]]]((?:(?!\[\[\[if:).)*?))?\[\[\[endif]]]/s';

        return preg_replace_callback($pattern, function($matches) use ($data) {
            $conditionTag = $matches[1];
            $operator = $matches[2] ?? null;
            $compareValue = $matches[3] ?? null;
            $ifContent = $matches[4];
            $elseContent = $matches[5] ?? '';

            $tagValue = $this->getTagValue($data, $conditionTag);

            if ($operator && $compareValue !== null) {
                $rightValue = is_numeric($compareValue) ? (float)$compareValue : $compareValue;
                $result = $this->evaluateComparison($tagValue, $operator, $rightValue);
            } else {
                $result = $this->isTruthy($tagValue);
            }

            return $result ? $ifContent : $elseContent;
        }, $template);
    }

    /**
     * Parse loop blocks [[[foreach:array_name]]]...[[[endforeach]]]
     */
    private function parseLoopBlocks(string $template, array $data): string
    {
        $pattern = '/\[\[\[foreach:([a-zA-Z0-9_]+)]]](.*?)\[\[\[endforeach]]]/s';

        return preg_replace_callback($pattern, function($matches) use ($data) {
            $arrayName = $matches[1];
            $loopContent = $matches[2];
            $output = '';

            if (isset($data[$arrayName]) && is_array($data[$arrayName])) {
                foreach ($data[$arrayName] as $index => $item) {
                    // Parse the loop content with item data
                    $itemData = array_merge($data, [
                        'item' => $item,
                        'index' => $index,
                        'loop_index' => $index + 1,
                    ]);

                    // Replace item references
                    $parsedContent = preg_replace_callback('/\[\[\[item\.([a-zA-Z0-9_]+)]]]/',
                        function($m) use ($item) {
                            return isset($item[$m[1]]) ? $this->sanitizeOutput($item[$m[1]]) : '';
                        },
                        $loopContent
                    );

                    $output .= $parsedContent;
                }
            }

            return $output;
        }, $template);
    }

    /**
     * Get tag value from data, supporting nested paths and database lookups
     */
    private function getTagValue(array $data, string $tagName)
    {
        // First, try to get from database tags
        $dbTag = TemplateTag::where('tag_name', $tagName)->first();
        if ($dbTag) {
            return $this->getValueByPath($data, $dbTag->json_path);
        }

        // Direct lookup
        if (isset($data[$tagName])) {
            return $data[$tagName];
        }

        // Handle nested paths
        if (str_contains($tagName, '.')) {
            return $this->getValueByPath($data, $tagName);
        }

        // Handle array access patterns
        if (preg_match('/^([a-zA-Z_]+)_(\d+)_([a-zA-Z_]+)$/', $tagName, $matches)) {
            $arrayKey = $matches[1];
            $index = (int)$matches[2];
            $fieldKey = $matches[3];

            if (isset($data[$arrayKey][$index][$fieldKey])) {
                return $data[$arrayKey][$index][$fieldKey];
            }
        }

        // Use the template data mapper for standardized values
        $mappedData = $this->templateDataMapper->mapTwitchDataForTemplates($data, 'overlay');
        return $mappedData[$tagName] ?? null;
    }

    /**
     * Apply transformation to a value
     */
    private function applyTransformation($value, string $transformation): string
    {
        return match($transformation) {
            'upper' => strtoupper($value),
            'lower' => strtolower($value),
            'title' => Str::title($value),
            'number' => number_format((float)$value),
            'thousands' => number_format((float)$value, 0, '.', ','),
            'date' => is_numeric($value) ? date('Y-m-d', $value) : date('Y-m-d', strtotime($value)),
            'time' => is_numeric($value) ? date('H:i:s', $value) : date('H:i:s', strtotime($value)),
            'datetime' => is_numeric($value) ? date('Y-m-d H:i:s', $value) : date('Y-m-d H:i:s', strtotime($value)),
            'bool' => $value ? 'Yes' : 'No',
            'truncate' => Str::limit($value, 50),
            default => $value
        };
    }

    /**
     * Evaluate comparison operators
     */
    private function evaluateComparison($leftValue, string $operator, $rightValue): bool
    {
        if ($operator === '==' || $operator === '!=') {
            $leftStr = (string)$leftValue;
            $rightStr = (string)$rightValue;
            return match ($operator) {
                '==' => $leftStr === $rightStr,
                '!=' => $leftStr !== $rightStr,
            };
        }

        if (in_array($operator, ['>', '>=', '<', '<='])) {
            if (!is_numeric($leftValue) || !is_numeric($rightValue)) {
                return false;
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
     * Check if a value is truthy
     */
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
        return !empty($value);
    }

    /**
     * Sanitize output to prevent XSS
     */
    private function sanitizeOutput($value): string
    {
        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate template syntax
     */
    public function validateTemplate(string $template): array
    {
        $validation = [
            'is_valid' => true,
            'syntax_issues' => [],
            'warnings' => [],
            'tags_found' => []
        ];

        // Find all template tags
        preg_match_all('/\[\[\[([a-zA-Z0-9_]+(?:\|[a-zA-Z0-9_]+)?)]]]/', $template, $matches);
        $validation['tags_found'] = array_unique($matches[1]);

        // Check for unclosed conditional blocks
        $ifCount = substr_count($template, '[[[if:');
        $endifCount = substr_count($template, '[[[endif]]]');
        if ($ifCount !== $endifCount) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = "Mismatched if/endif blocks (found $ifCount if blocks and $endifCount endif blocks)";
        }

        // Check for unclosed loop blocks
        $foreachCount = substr_count($template, '[[[foreach:');
        $endforeachCount = substr_count($template, '[[[endforeach]]]');
        if ($foreachCount !== $endforeachCount) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = "Mismatched foreach/endforeach blocks";
        }

        // Validate that used tags exist in a database
        $dbTags = TemplateTag::where('is_active', true)->pluck('tag_name')->toArray();
        foreach ($validation['tags_found'] as $tag) {
            // Remove the transformation part if present
            $tagName = explode('|', $tag)[0];
            if (!in_array($tagName, $dbTags)) {
                $validation['warnings'][] = "Tag '$tagName' not found in database";
            }
        }

        return $validation;
    }

    /**
     * Get available template tags organized by category
     */
    public function getAvailableTags(): array
    {
        return TemplateTag::with('category')
            ->where('is_active', true)
            ->get()
            ->groupBy('category.display_name')
            ->map(function ($tags) {
                return $tags->map(function ($tag) {
                    return [
                        'tag' => $tag->display_tag,
                        'name' => $tag->display_name,
                        'description' => $tag->description,
                        'sample' => $tag->sample_data,
                        'type' => $tag->data_type,
                    ];
                })->values();
            })
            ->toArray();
    }
}
