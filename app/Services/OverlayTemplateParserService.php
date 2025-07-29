<?php

namespace App\Services;

use App\Models\TemplateTag;
use Illuminate\Support\Facades\Log;

class OverlayTemplateParserService
{
    /**
     * Parse template string and replace [[[template_tags]]] with live data
     */
    public function parseTemplate(string $template, array $twitchData): string
    {
        // Find all [[[tag_name]]] patterns in the template
        preg_match_all('/\[\[\[([^\]]+)\]\]\]/', $template, $matches);
        
        if (empty($matches[1])) {
            // No template tags found, return as-is
            return $template;
        }
        
        $parsedTemplate = $template;
        
        foreach ($matches[1] as $tagName) {
            // Find the template tag in database
            $templateTag = TemplateTag::where('tag_name', $tagName)
                ->where('is_active', true)
                ->first();
                
            if ($templateTag) {
                // Get the formatted output for this tag
                $value = $templateTag->getFormattedOutput($twitchData);
                
                // Replace the [[[tag_name]]] with the actual value
                $parsedTemplate = str_replace("[[[{$tagName}]]]", $value, $parsedTemplate);
                
                Log::debug('Template tag replaced', [
                    'tag_name' => $tagName,
                    'value' => $value,
                    'json_path' => $templateTag->json_path
                ]);
            } else {
                // Template tag not found - leave as placeholder or show error
                $errorMessage = "<!-- Template tag '{$tagName}' not found -->";
                $parsedTemplate = str_replace("[[[{$tagName}]]]", $errorMessage, $parsedTemplate);
                
                Log::warning('Template tag not found', [
                    'tag_name' => $tagName,
                    'available_tags' => TemplateTag::where('is_active', true)->pluck('tag_name')->toArray()
                ]);
            }
        }
        
        return $parsedTemplate;
    }

    /**
     * Parse template and return both parsed content and debug info
     */
    public function parseTemplateWithDebug(string $template, array $twitchData): array
    {
        $debugInfo = [
            'tags_found' => [],
            'tags_replaced' => [],
            'tags_missing' => [],
            'parsing_errors' => []
        ];
        
        // Find all [[[tag_name]]] patterns
        preg_match_all('/\[\[\[([^\]]+)\]\]\]/', $template, $matches);
        
        $debugInfo['tags_found'] = $matches[1] ?? [];
        
        if (empty($matches[1])) {
            return [
                'parsed_template' => $template,
                'debug_info' => $debugInfo
            ];
        }
        
        $parsedTemplate = $template;
        
        foreach ($matches[1] as $tagName) {
            $templateTag = TemplateTag::where('tag_name', $tagName)
                ->where('is_active', true)
                ->first();
                
            if ($templateTag) {
                try {
                    $value = $templateTag->getFormattedOutput($twitchData);
                    $parsedTemplate = str_replace("[[[{$tagName}]]]", $value, $parsedTemplate);
                    
                    $debugInfo['tags_replaced'][] = [
                        'tag_name' => $tagName,
                        'value' => $value,
                        'json_path' => $templateTag->json_path,
                        'data_type' => $templateTag->data_type
                    ];
                } catch (\Exception $e) {
                    $debugInfo['parsing_errors'][] = [
                        'tag_name' => $tagName,
                        'error' => $e->getMessage()
                    ];
                    
                    $errorMessage = "<!-- Error parsing '{$tagName}': {$e->getMessage()} -->";
                    $parsedTemplate = str_replace("[[[{$tagName}]]]", $errorMessage, $parsedTemplate);
                }
            } else {
                $debugInfo['tags_missing'][] = $tagName;
                $errorMessage = "<!-- Template tag '{$tagName}' not found -->";
                $parsedTemplate = str_replace("[[[{$tagName}]]]", $errorMessage, $parsedTemplate);
            }
        }
        
        return [
            'parsed_template' => $parsedTemplate,
            'debug_info' => $debugInfo
        ];
    }

    /**
     * Get all available template tags for frontend use
     */
    public function getAvailableTemplateTags(): array
    {
        return TemplateTag::where('is_active', true)
            ->with('category')
            ->orderBy('tag_name')
            ->get()
            ->map(function($tag) {
                return [
                    'tag_name' => $tag->tag_name,
                    'display_tag' => $tag->display_tag,
                    'display_name' => $tag->display_name,
                    'description' => $tag->description,
                    'data_type' => $tag->data_type,
                    'category' => $tag->category->display_name ?? 'Unknown',
                    'sample_data' => $tag->sample_data
                ];
            })
            ->toArray();
    }

    /**
     * Validate template syntax without parsing
     */
    public function validateTemplate(string $template): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => [],
            'tags_found' => [],
            'syntax_issues' => []
        ];
        
        // Check for basic syntax issues
        if (substr_count($template, '[[[') !== substr_count($template, ']]]')) {
            $validation['is_valid'] = false;
            $validation['syntax_issues'][] = 'Mismatched triple brackets - ensure every [[[ has a matching ]]]';
        }
        
        // Find all template tags
        preg_match_all('/\[\[\[([^\]]+)\]\]\]/', $template, $matches);
        $validation['tags_found'] = $matches[1] ?? [];
        
        // Check if tags exist in database
        foreach ($validation['tags_found'] as $tagName) {
            $exists = TemplateTag::where('tag_name', $tagName)
                ->where('is_active', true)
                ->exists();
                
            if (!$exists) {
                $validation['warnings'][] = "Template tag '{$tagName}' does not exist or is inactive";
            }
        }
        
        // Check for common issues
        if (preg_match('/\[\[([^\]]+)\]\]/', $template)) {
            $validation['warnings'][] = 'Found double brackets [[ ]] - did you mean triple brackets [[[ ]]]?';
        }
        
        if (preg_match('/\{\{\{([^\}]+)\}\}\}/', $template)) {
            $validation['warnings'][] = 'Found curly brackets {{ }} - this system uses square brackets [[[ ]]]';
        }
        
        return $validation;
    }

    /**
     * Generate example template with common tags
     */
    public function generateExampleTemplate(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: transparent;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.8);
        }
        .overlay {
            background: rgba(0,0,0,0.7);
            padding: 20px;
            border-radius: 10px;
            max-width: 300px;
        }
        .channel-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .stats {
            font-size: 16px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="overlay">
        <div class="channel-name">[[[channel_name]]]</div>
        <div class="stats">
            <div>Followers: [[[followers_total]]]</div>
            <div>Latest Follower: [[[followers_latest_name]]]</div>
            <div>Subscribers: [[[subscribers_total]]]</div>
        </div>
    </div>
</body>
</html>';
    }
}