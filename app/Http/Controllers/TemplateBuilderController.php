<?php

namespace App\Http\Controllers;

use App\Models\OverlayHash;
use App\Models\TemplateTag;
use App\Services\OverlayTemplateParserService;
use App\Services\DefaultTemplateProviderService;
use App\Services\TemplateDataMapperService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class TemplateBuilderController extends Controller
{
    private OverlayTemplateParserService $templateParser;
    private DefaultTemplateProviderService $defaultTemplateProvider;
    private TemplateDataMapperService $templateDataMapper;

    public function __construct(
        OverlayTemplateParserService $templateParser,
        DefaultTemplateProviderService $defaultTemplateProvider,
        TemplateDataMapperService $templateDataMapper
    ) {
        $this->templateParser = $templateParser;
        $this->defaultTemplateProvider = $defaultTemplateProvider;
        $this->templateDataMapper = $templateDataMapper;
    }

    /**
     * Show the template builder interface (Inertia) - NOW USES CENTRALIZED SERVICE!
     */
    public function index(Request $request, string $slug = null): Response
    {
        $overlayHash = null;
        $existingTemplate = [
            'html' => '',
            'css' => ''
        ];

        // If slug is provided, load existing template
        if ($slug) {
            $overlayHash = OverlayHash::where('slug', $slug)
                ->where('user_id', Auth::id())
                ->where('is_active', true)
                ->first();

            if ($overlayHash) {
                // Check if custom template exists
                if (isset($overlayHash->metadata['html_template']) && !empty($overlayHash->metadata['html_template'])) {
                    $existingTemplate = [
                        'html' => $overlayHash->metadata['html_template'],
                        'css' => $overlayHash->metadata['css_template'] ?? ''
                    ];
                } else {
                    // No custom template exists - use centralized default template service!
                    $existingTemplate = $this->defaultTemplateProvider->getDefaultTemplates();
                }
            }
        }

        return Inertia::render('TemplateBuilder', [
            'overlayHash' => $overlayHash ? [
                'id' => $overlayHash->id,
                'hash_key' => $overlayHash->hash_key,
                'slug' => $overlayHash->slug,
                'overlay_name' => $overlayHash->overlay_name,
            ] : null,
            'existingTemplate' => $existingTemplate,
            'availableTags' => $this->getTemplateTagsForFrontend(),
            'userOverlayHashes' => $this->getUserOverlayHashes()
        ]);
    }

    /**
     * Get available template tags
     */
    public function getAvailableTags(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'tags' => $this->getTemplateTagsForFrontend()
        ]);
    }

    /**
     * Get default templates from the centralized service
     * This ensures the Vue component always gets the latest default templates
     */
    public function getDefaultTemplates(): JsonResponse
    {
        try {
            $templates = $this->defaultTemplateProvider->getDefaultTemplates();
            
            return response()->json([
                'success' => true,
                'templates' => $templates,
                'source' => 'DefaultTemplateProviderService - centralized template files'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching default templates', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to load default templates',
                'templates' => [
                    'html' => '<!-- Default template unavailable -->',
                    'css' => '/* Default styles unavailable */'
                ]
            ], 500);
        }
    }

    /**
     * Validate template syntax and content
     */
    public function validateTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'html_template' => 'required|string',
            'css_template' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all()
            ], 422);
        }

        if (preg_match('/<html|<head|<body|<!DOCTYPE/i', $request->input('html_template'))) {
            return response()->json([
                'success' => false,
                'errors' => ['Please do not include <html>, <head>, or <body> tags — just write the overlay content. Add styling to the CSS editor.']
            ], 422);
        }

        // Validate HTML template
        $htmlValidation = $this->templateParser->validateTemplate($request->input('html_template'));
        
        // Validate CSS template
        $cssValidation = $this->validateCssTemplate($request->input('css_template', ''));

        $allErrors = array_merge(
            $htmlValidation['syntax_issues'] ?? [],
            $cssValidation['errors'] ?? []
        );

        $allWarnings = array_merge(
            $htmlValidation['warnings'] ?? [],
            $cssValidation['warnings'] ?? []
        );

        return response()->json([
            'success' => count($allErrors) === 0,
            'is_valid' => $htmlValidation['is_valid'] && $cssValidation['is_valid'],
            'errors' => $allErrors,
            'warnings' => $allWarnings,
            'tags_found' => $htmlValidation['tags_found'] ?? [],
            'html_validation' => $htmlValidation,
            'css_validation' => $cssValidation
        ]);
    }

    /**
     * Save template to an overlay hash - USES SLUG TO FIND OVERLAY
     */
    public function saveTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'overlay_slug' => 'required|string',
            'html_template' => 'required|string',
            'css_template' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all()
            ], 422);
        }

        // Find the overlay hash by slug
        $overlayHash = OverlayHash::where('slug', $request->input('overlay_slug'))
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->first();

        if (!$overlayHash) {
            return response()->json([
                'success' => false,
                'message' => 'Overlay not found or you do not have permission to edit it.'
            ], 404);
        }

        // Validate templates before saving
        $validation = $this->templateParser->validateTemplate($request->input('html_template'));
        
        if (!$validation['is_valid']) {
            return response()->json([
                'success' => false,
                'message' => 'Template validation failed.',
                'errors' => $validation['syntax_issues']
            ], 422);
        }

        // Update the overlay hash metadata
        $metadata = $overlayHash->metadata ?? [];
        $metadata['html_template'] = $request->input('html_template');
        $metadata['css_template'] = $request->input('css_template', '');
        $metadata['template_updated_at'] = now()->toISOString();

        $overlayHash->update(['metadata' => $metadata]);

        Log::info('Template saved for overlay via slug', [
            'slug' => $overlayHash->slug,
            'user_id' => Auth::id(),
            'template_size_html' => strlen($request->input('html_template')),
            'template_size_css' => strlen($request->input('css_template', ''))
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template saved successfully!',
            'overlay_slug' => $overlayHash->slug,
            'overlay_name' => $overlayHash->overlay_name
        ]);
    }

    /**
     * Load existing template from slug
     */
    public function loadTemplate(string $slug): JsonResponse
    {
        $overlayHash = OverlayHash::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->first();

        if (!$overlayHash) {
            return response()->json([
                'success' => false,
                'message' => 'Template not found.'
            ], 404);
        }

        // Check if custom template exists, otherwise provide default from service
        $template = [
            'html' => $overlayHash->metadata['html_template'] ?? '',
            'css' => $overlayHash->metadata['css_template'] ?? ''
        ];

        // If no custom template exists, provide the beautiful default from service
        if (empty($template['html'])) {
            $template = $this->defaultTemplateProvider->getDefaultTemplates();
        }

        return response()->json([
            'success' => true,
            'template' => [
                'html' => $template['html'],
                'css' => $template['css'],
                'overlay_name' => $overlayHash->overlay_name,
                'slug' => $overlayHash->slug,
                'hash_key' => $overlayHash->hash_key,
                'last_updated' => $overlayHash->metadata['template_updated_at'] ?? $overlayHash->updated_at
            ]
        ]);
    }

    /**
     * Preview template with sample data
     */
    public function previewTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'html_template' => 'required|string',
            'css_template' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()->all()
            ], 422);
        }

        // Generate sample Twitch data for preview using the service
        $sampleData = $this->defaultTemplateProvider->getSampleData();

        // Parse the template with sample data
        $parsedHtml = $this->templateParser->parseTemplateWithDebug(
            $request->input('html_template'),
            $sampleData
        );
        
        $parsedCss = $this->templateParser->parseTemplate(
            $request->input('css_template', ''),
            $sampleData
        );

        // Wrap into full HTML doc
        $finalHtml = $this->templateDataMapper->wrapHtmlAndCssIntoDocument(
            $parsedHtml['parsed_template'],
            $parsedCss,
            $sampleData['overlay_name'] ?? 'Overlay Preview'
        );

        return response()->json([
            'success' => true,
            'preview_html' => $finalHtml,
            'debug_info' => $parsedResult['debug_info'],
            'sample_data' => $sampleData
        ]);
    }

    /**
     * Export template as standalone HTML file
     */
    public function exportTemplate(Request $request): \Illuminate\Http\Response
    {
        $validator = Validator::make($request->all(), [
            'html_template' => 'required|string',
            'css_template' => 'nullable|string',
            'filename' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response('Invalid template data', 422);
        }

        // Create complete HTML file
        $htmlTemplate = $request->input('html_template');
        $cssTemplate = $request->input('css_template', '');
        
        if (!empty($cssTemplate)) {
            // Inject CSS into HTML
            if (strpos($htmlTemplate, '<style>') !== false) {
                $htmlTemplate = preg_replace('/<style[^>]*>.*?<\/style>/s', "<style>{$cssTemplate}</style>", $htmlTemplate);
            } else {
                $htmlTemplate = str_replace('</head>', "<style>{$cssTemplate}</style>\n</head>", $htmlTemplate);
            }
        }

        $filename = $request->input('filename', 'overlay-template') . '.html';

        return response($htmlTemplate, 200)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Get template tags formatted for frontend
     */
    private function getTemplateTagsForFrontend(): array
    {
        return TemplateTag::where('is_active', true)
            ->with('category')
            ->orderBy('tag_name')
            ->get()
            ->map(function($tag) {
                return [
                    'tag_name' => $tag->tag_name,
                    'display_name' => $tag->display_name,
                    'description' => $tag->description,
                    'data_type' => $tag->data_type,
                    'category' => $tag->category->display_name ?? 'General',
                    'sample_data' => $tag->sample_data
                ];
            })
            ->toArray();
    }

    /**
     * Get user's overlay hashes for selection - USES SLUG FOR NAVIGATION
     */
    private function getUserOverlayHashes(): array
    {
        return OverlayHash::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'hash_key', 'slug', 'overlay_name', 'created_at'])
            ->map(function($hash) {
                return [
                    'id' => $hash->id,
                    'hash_key' => $hash->hash_key,
                    'slug' => $hash->slug,
                    'overlay_name' => $hash->overlay_name,
                    'created_at' => $hash->created_at->diffForHumans()
                ];
            })
            ->toArray();
    }

    /**
     * Basic CSS validation
     */
    private function validateCssTemplate(string $css): array
    {
        $validation = [
            'is_valid' => true,
            'errors' => [],
            'warnings' => []
        ];

        if (empty($css)) {
            return $validation;
        }

        // Basic syntax checks
        $openBraces = substr_count($css, '{');
        $closeBraces = substr_count($css, '}');
        
        if ($openBraces !== $closeBraces) {
            $validation['is_valid'] = false;
            $validation['errors'][] = 'Mismatched CSS braces - ensure every { has a matching }';
        }

        // Check for common CSS issues
        if (preg_match('/[^{}]*{[^{}]*{/', $css)) {
            $validation['warnings'][] = 'Possible nested CSS rules detected - this may not render correctly';
        }

        // Check for potentially dangerous CSS
        if (strpos($css, '@import') !== false) {
            $validation['warnings'][] = '@import statements may not work in overlay context';
        }

        if (strpos($css, 'javascript:') !== false) {
            $validation['is_valid'] = false;
            $validation['errors'][] = 'JavaScript URLs are not allowed in CSS for security reasons';
        }

        return $validation;
    }
}