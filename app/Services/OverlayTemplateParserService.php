<?php

namespace App\Services;

use App\Services\DefaultTemplateProviderService;
use Illuminate\Support\Facades\Log;

/**
 * OverlayTemplateParserService
 * 
 * Handles parsing of overlay templates with [[[template_tags]]] syntax.
 * Now uses DefaultTemplateProviderService for consistent default templates.
 */
class OverlayTemplateParserService
{
    private DefaultTemplateProviderService $defaultTemplateProvider;

    public function __construct(DefaultTemplateProviderService $defaultTemplateProvider)
    {
        $this->defaultTemplateProvider = $defaultTemplateProvider;
    }

    /**
     * Parse a template string, replacing [[[template_tags]]] with actual data
     */
    public function parseTemplate(string $template, array $data): string
    {
        // Find all template tags in the format [[[tag_name]]]
        $pattern = '/\[\[\[([a-zA-Z0-9_]+)\]\]\]/';
        
        return preg_replace_callback($pattern, function($matches) use ($data) {
            $tagName = $matches[1];
            
            // Check if the tag exists in our data
            if (isset($data[$tagName])) {
                return $data[$tagName];
            }
            
            // Handle nested data (like channel.broadcaster_name)
            if (strpos($tagName, '.') !== false) {
                $value = $this->getNestedValue($data, $tagName);
                if ($value !== null) {
                    return $value;
                }
            }
            
            // Return empty string for unknown tags (or you could return the tag itself for debugging)
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
        $pattern = '/\[\[\[([a-zA-Z0-9_]+)\]\]\]/';
        preg_match_all($pattern, $template, $matches);
        
        $debugInfo['tags_found'] = array_unique($matches[1]);

        // Parse template and track replacements
        $parsedTemplate = preg_replace_callback($pattern, function($matches) use ($data, &$debugInfo) {
            $tagName = $matches[1];
            
            if (isset($data[$tagName])) {
                $debugInfo['tags_replaced'][] = $tagName;
                return $data[$tagName];
            }
            
            // Handle nested data
            if (strpos($tagName, '.') !== false) {
                $value = $this->getNestedValue($data, $tagName);
                if ($value !== null) {
                    $debugInfo['tags_replaced'][] = $tagName;
                    return $value;
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
        if (strpos($template, '<html') !== false || strpos($template, '<!DOCTYPE') !== false) {
            // This looks like a complete HTML document
            if (strpos($template, '<head>') === false) {
                $validation['warnings'][] = 'HTML document missing <head> section';
            }
            if (strpos($template, '<body>') === false) {
                $validation['warnings'][] = 'HTML document missing <body> section';
            }
        }

        // Check for malformed template tags
        $pattern = '/\[\[\[([a-zA-Z0-9_]*)\]\]\]/';
        preg_match_all($pattern, $template, $matches);
        
        $validation['tags_found'] = array_unique($matches[1]);

        // Check for incomplete tags
        if (preg_match('/\[\[\[[^\]]*$/', $template)) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Incomplete template tag found - make sure all tags end with ]]]';
        }

        // Check for nested tags (not supported)
        if (preg_match('/\[\[\[[^\]]*\[\[\[/', $template)) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Nested template tags are not supported';
        }

        // Check for empty tags
        if (preg_match('/\[\[\[\]\]\]/', $template)) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Empty template tags found - [[[tagname]]] format required';
        }

        // Warn about potential XSS
        if (strpos($template, '<script') !== false) {
            $validation['warnings'][] = 'JavaScript found in template - ensure data is properly sanitized';
        }

        return $validation;
    }

    /**
     * Get example/default template using the centralized service
     * This replaces the old generateExampleTemplate method!
     */
    public function getExampleTemplate(): array
    {
        return $this->defaultTemplateProvider->getDefaultTemplates();
    }

    /**
     * Generate a preview of the default template with sample data
     */
    public function getExamplePreview(): string
    {
        return $this->defaultTemplateProvider->getPreviewHtml();
    }

    /**
     * Get available template tags (this would typically come from your TemplateTag model)
     */
    public function getAvailableTemplateTags(): array
    {
        // This could be expanded to read from your TemplateTag model
        return [
            'overlay_name' => 'Name of the overlay',
            'channel_name' => 'Twitch channel name',
            'followers_total' => 'Total number of followers',
            'followers_latest_name' => 'Name of the latest follower',
            'subscribers_total' => 'Total number of subscribers',
            'viewers_current' => 'Current viewer count',
            'stream_title' => 'Current stream title',
            'stream_category' => 'Current stream category',
            'timestamp' => 'Current timestamp'
        ];
    }

    /**
     * Helper: Get nested value from array using dot notation
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
    private function sanitizeOutput(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}