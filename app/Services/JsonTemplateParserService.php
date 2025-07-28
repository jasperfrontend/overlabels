<?php
// app/Services/JsonTemplateParserService.php

namespace App\Services;

use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class JsonTemplateParserService
{
    /**
     * Parse JSON data and generate template tags
     */
    public function parseJsonAndCreateTags(array $jsonData): array
    {
        $createdTags = [];
        $categories = [];

        // Recursively parse the JSON structure
        $this->parseLevel($jsonData, '', $createdTags, $categories);

        return [
            'categories' => $categories,
            'tags' => $createdTags,
            'total_tags' => count($createdTags)
        ];
    }

    /**
     * Recursively parse each level of the JSON
     */
    private function parseLevel(array $data, string $currentPath, array &$createdTags, array &$categories, string $parentKey = ''): void
    {
        foreach ($data as $key => $value) {
            $newPath = $currentPath ? "{$currentPath}.{$key}" : $key;
            
            if (is_array($value)) {
                // Handle arrays - could be a list of objects or simple array
                if ($this->isAssociativeArray($value)) {
                    // This is an object, create a category for it
                    $this->createCategory($key, $categories);
                    
                    // Parse the object's properties
                    $this->parseLevel($value, $newPath, $createdTags, $categories, $key);
                } else {
                    // This is a list/array
                    $this->handleArrayValue($key, $value, $newPath, $createdTags, $categories, $parentKey);
                }
            } else {
                // This is a primitive value (string, number, boolean)
                $this->createTag($key, $newPath, $value, $createdTags, $categories, $parentKey);
            }
        }
    }

    /**
     * Check if array is associative (object-like) or sequential (list-like)
     */
    private function isAssociativeArray(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Handle array values (like lists of followers, subscribers, etc.)
     */
    private function handleArrayValue(string $key, array $value, string $path, array &$createdTags, array &$categories, string $parentKey): void
    {
        // Create a category for this array
        $this->createCategory($key, $categories, true);

        // Create tags for array metadata
        if (!empty($value)) {
            // If it's an array of objects, create tags for the first object's structure
            if (is_array($value[0])) {
                // Create a "latest" tag that points to the first item
                $this->createTag("{$key}.latest", "{$path}.0", $value[0], $createdTags, $categories, $key, 'object');
                
                // Create tags for each property of the first object
                foreach ($value[0] as $objKey => $objValue) {
                    $this->createTag(
                        "{$key}.latest.{$objKey}",
                        "{$path}.0.{$objKey}",
                        $objValue,
                        $createdTags,
                        $categories,
                        $key
                    );
                }
            }
        }

        // Create a tag for array length
        $this->createTag(
            "{$key}.count",
            $path,
            count($value),
            $createdTags,
            $categories,
            $key,
            'array_count'
        );
    }

    /**
     * Create a category if it doesn't exist
     */
    private function createCategory(string $name, array &$categories, bool $isGroup = false): void
    {
        if (!isset($categories[$name])) {
            $categories[$name] = [
                'name' => $name,
                'display_name' => Str::title(str_replace('_', ' ', $name)),
                'description' => "Template tags related to {$name}",
                'is_group' => $isGroup,
                'sort_order' => count($categories)
            ];
        }
    }

    /**
     * Create a template tag
     */
    private function createTag(
        string $tagName, 
        string $jsonPath, 
        $sampleValue, 
        array &$createdTags, 
        array &$categories, 
        string $categoryName, 
        string $dataType = null
    ): void {
        // Determine data type if not provided
        if (!$dataType) {
            $dataType = $this->getDataType($sampleValue);
        }

        // Create display tag
        $displayTag = "[[[{$tagName}]]]";

        // Ensure category exists
        $this->createCategory($categoryName, $categories);

        // Create the tag data
        $createdTags[] = [
            'category_name' => $categoryName,
            'tag_name' => $tagName,
            'display_tag' => $displayTag,
            'json_path' => $jsonPath,
            'data_type' => $dataType,
            'display_name' => $this->generateDisplayName($tagName),
            'description' => $this->generateDescription($tagName, $dataType),
            'sample_data' => $this->formatSampleData($sampleValue, $dataType),
            'formatting_options' => $this->getFormattingOptions($dataType, $tagName)
        ];
    }

    /**
     * Determine the data type of a value
     */
    private function getDataType($value): string
    {
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'float';
        if (is_array($value)) return 'array';
        if (is_null($value)) return 'null';
        
        // Check if string looks like a date
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z?$/', $value)) {
            return 'datetime';
        }
        
        return 'string';
    }

    /**
     * Generate a human-readable display name
     */
    private function generateDisplayName(string $tagName): string
    {
        // Convert snake_case and dot notation to Title Case
        $parts = explode('.', $tagName);
        $formatted = array_map(function($part) {
            return Str::title(str_replace('_', ' ', $part));
        }, $parts);
        
        return implode(' - ', $formatted);
    }

    /**
     * Generate a description for the tag
     */
    private function generateDescription(string $tagName, string $dataType): string
    {
        $descriptions = [
            'datetime' => 'Date and time value',
            'integer' => 'Numeric value',
            'float' => 'Decimal number',
            'boolean' => 'True/false value',
            'array' => 'List of items',
            'array_count' => 'Number of items in the list',
            'object' => 'Complex data object'
        ];

        $baseDescription = $descriptions[$dataType] ?? 'Text value';
        
        return "Template tag for {$this->generateDisplayName($tagName)}. {$baseDescription}.";
    }

    /**
     * Format sample data for storage
     */
    private function formatSampleData($value, string $dataType)
    {
        if ($dataType === 'array_count') {
            return $value; // Just the count
        }
        
        if (is_array($value)) {
            // Store only first few items for arrays
            return array_slice($value, 0, 3);
        }
        
        return $value;
    }

    /**
     * Get formatting options based on data type and tag name
     */
    private function getFormattingOptions(string $dataType, string $tagName): ?array
    {
        $options = [];

        // Date formatting options
        if ($dataType === 'datetime') {
            $options['date_format'] = 'd-m-Y H:i'; // Default format
            $options['available_formats'] = [
                'd-m-Y H:i' => 'DD-MM-YYYY HH:MM',
                'Y-m-d H:i:s' => 'YYYY-MM-DD HH:MM:SS',
                'M j, Y' => 'Month Day, Year',
                'D, M j Y' => 'Day, Month Day Year'
            ];
        }

        // Number formatting
        if (in_array($dataType, ['integer', 'float'])) {
            $options['number_format'] = [
                'decimals' => $dataType === 'float' ? 2 : 0,
                'thousands_separator' => ','
            ];
        }

        // Array joining options
        if ($dataType === 'array') {
            $options['array_join'] = ', ';
            $options['max_items'] = 10;
        }

        return empty($options) ? null : $options;
    }

    /**
     * Save parsed tags to database
     */
    public function saveTagsToDatabase(array $parsedData): array
    {
        $saved = [
            'categories' => 0,
            'tags' => 0,
            'errors' => []
        ];

        try {
            // Create categories first
            foreach ($parsedData['categories'] as $categoryData) {
                $category = TemplateTagCategory::updateOrCreate(
                    ['name' => $categoryData['name']],
                    $categoryData
                );
                $saved['categories']++;
            }

            // Create tags
            foreach ($parsedData['tags'] as $tagData) {
                $category = TemplateTagCategory::where('name', $tagData['category_name'])->first();
                
                if ($category) {
                    $tag = TemplateTag::updateOrCreate(
                        [
                            'category_id' => $category->id,
                            'tag_name' => $tagData['tag_name']
                        ],
                        array_merge($tagData, ['category_id' => $category->id])
                    );
                    $saved['tags']++;
                } else {
                    $saved['errors'][] = "Category not found for tag: {$tagData['tag_name']}";
                }
            }

        } catch (\Exception $e) {
            $saved['errors'][] = $e->getMessage();
            Log::error('Error saving template tags to database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $saved;
    }

    /**
     * Get all template tags organized by category
     */
    public function getOrganizedTemplateTags(): array
    {
        $categories = TemplateTagCategory::with('activeTemplateTags')
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->get();

        $organized = [];

        foreach ($categories as $category) {
            $organized[$category->name] = [
                'category' => $category,
                'tags' => $category->activeTemplateTags->map(function($tag) {
                    return [
                        'id' => $tag->id,
                        'tag_name' => $tag->tag_name,
                        'display_tag' => $tag->display_tag,
                        'display_name' => $tag->display_name,
                        'description' => $tag->description,
                        'data_type' => $tag->data_type,
                        'sample_data' => $tag->sample_data,
                        'json_path' => $tag->json_path
                    ];
                })
            ];
        }

        return $organized;
    }
}
