<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * DefaultTemplateProviderService
 * 
 * Centralized service for managing default overlay templates.
 * This ensures DRY principle - one source of truth for default templates!
 * 
 * For Laravel beginners:
 * - This is a Service class (part of the Service layer in MVC)
 * - Services contain business logic that can be reused across controllers
 * - Laravel's File facade helps us read files from the filesystem safely
 * - Singleton pattern ensures we only load templates once per request
 */
class DefaultTemplateProviderService
{
    private ?string $defaultHtml = null;
    private ?string $defaultCss = null;
    
    private const DEFAULT_HTML_PATH = 'resources/templates/default-overlay.html';
    private const DEFAULT_CSS_PATH = 'resources/templates/default-overlay.css';

    /**
     * Get the default HTML template
     * Uses caching to avoid reading file multiple times per request
     */
    public function getDefaultHtml(): string
    {
        if ($this->defaultHtml === null) {
            $this->loadDefaultTemplates();
        }
        
        return $this->defaultHtml ?? $this->getFallbackHtml();
    }

    /**
     * Get the default CSS template
     * Uses caching to avoid reading file multiple times per request
     */
    public function getDefaultCss(): string
    {
        if ($this->defaultCss === null) {
            $this->loadDefaultTemplates();
        }
        
        return $this->defaultCss ?? $this->getFallbackCss();
    }

    /**
     * Get both templates as an array (useful for API responses)
     */
    public function getDefaultTemplates(): array
    {
        return [
            'html' => $this->getDefaultHtml(),
            'css' => $this->getDefaultCss()
        ];
    }

    /**
     * Get a complete HTML document with CSS injected
     * This is used by OverlayHashController for serving actual overlays
     */
    public function getCompleteDefaultHtml(array $data = []): string
    {
        $html = $this->getDefaultHtml();
        $css = $this->getDefaultCss();
        
        // Replace template data if provided (for direct serving)
        if (!empty($data)) {
            foreach ($data as $key => $value) {
                $html = str_replace("[[[{$key}]]]", htmlspecialchars($value), $html);
            }
        }
        
        // Inject CSS into HTML
        if (strpos($html, '<style>') !== false) {
            $html = preg_replace('/<style[^>]*>.*?<\/style>/s', "<style>{$css}</style>", $html);
        } else {
            $html = str_replace('</head>', "<style>{$css}</style>\n</head>", $html);
        }
        
        return $html;
    }

    /**
     * Check if template files exist and are readable
     */
    public function validateTemplateFiles(): array
    {
        $htmlPath = base_path(self::DEFAULT_HTML_PATH);
        $cssPath = base_path(self::DEFAULT_CSS_PATH);
        
        $validation = [
            'html_exists' => File::exists($htmlPath) && File::isReadable($htmlPath),
            'css_exists' => File::exists($cssPath) && File::isReadable($cssPath),
            'html_path' => $htmlPath,
            'css_path' => $cssPath,
        ];
        
        $validation['all_valid'] = $validation['html_exists'] && $validation['css_exists'];
        
        return $validation;
    }

    /**
     * Load template files from disk
     * Private method - only called internally
     */
    private function loadDefaultTemplates(): void
    {
        try {
            $htmlPath = base_path(self::DEFAULT_HTML_PATH);
            $cssPath = base_path(self::DEFAULT_CSS_PATH);
            
            if (File::exists($htmlPath) && File::isReadable($htmlPath)) {
                $this->defaultHtml = File::get($htmlPath);
            } else {
                Log::warning('Default HTML template file not found or not readable', [
                    'path' => $htmlPath,
                    'exists' => File::exists($htmlPath),
                    'readable' => File::exists($htmlPath) ? File::isReadable($htmlPath) : false
                ]);
            }
            
            if (File::exists($cssPath) && File::isReadable($cssPath)) {
                $this->defaultCss = File::get($cssPath);
            } else {
                Log::warning('Default CSS template file not found or not readable', [
                    'path' => $cssPath,
                    'exists' => File::exists($cssPath),
                    'readable' => File::exists($cssPath) ? File::isReadable($cssPath) : false
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Error loading default templates', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Fallback HTML if file reading fails
     * This ensures the application doesn't break if template files are missing
     */
    private function getFallbackHtml(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[[[overlay_name]]]</title>
</head>
<body>
    <div style="padding: 20px; background: rgba(0,0,0,0.8); color: white; border-radius: 10px; margin: 20px;">
        <h1>[[[overlay_name]]]</h1>
        <p><strong>Channel:</strong> [[[channel_name]]]</p>
        <p><strong>Followers:</strong> [[[followers_total]]]</p>
        <p><em>Fallback template - default template file missing</em></p>
    </div>
</body>
</html>';
    }

    /**
     * Fallback CSS if file reading fails
     */
    private function getFallbackCss(): string
    {
        return 'body { font-family: Arial, sans-serif; background: transparent; color: white; }';
    }

    /**
     * Generate sample data for previews
     * This is used by template builder and parser service
     */
    public function getSampleData(): array
    {
        // Use the TemplateDataMapperService for consistent sample data
        $templateDataMapper = app(\App\Services\TemplateDataMapperService::class);
        return $templateDataMapper->getSampleTemplateData();
    }

    /**
     * Create a preview with sample data
     * Useful for API endpoints that need to show template previews
     */
    public function getPreviewHtml(): string
    {
        return $this->getCompleteDefaultHtml($this->getSampleData());
    }
}