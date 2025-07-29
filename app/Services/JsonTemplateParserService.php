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
     * Generate only STANDARD tags that are consistent for everyone
     */
    public function parseJsonAndCreateTags(array $jsonData): array
    {
        $createdTags = [];
        $categories = [];

        // Parse with standard naming conventions
        $this->parseLevel($jsonData, '', $createdTags, $categories);

        // Mark all generated tags as 'standard' and non-editable
        foreach ($createdTags as &$tag) {
            $tag['tag_type'] = 'standard';
            $tag['version'] = '1.0';
            $tag['is_editable'] = false;
        }

        return [
            'categories' => $categories,
            'tags' => $createdTags,
            'total_tags' => count($createdTags)
        ];
    }
    
    /**
     * Get standardized tag name (consistent naming for everyone)
     * This function maps raw JSON paths to user-friendly, standardized tag names
     */
    private function getStandardizedTagName(string $jsonPath): string
    {
        // Define standard naming conventions
        // Format: 'json_path' => 'standardized_tag_name'
        $standardNames = [
            // User data mappings
            'user.id' => 'user_id',
            'user.login' => 'user_login', 
            'user.display_name' => 'user_name',
            'user.type' => 'user_type',
            'user.broadcaster_type' => 'user_broadcaster_type',
            'user.description' => 'user_description',
            'user.profile_image_url' => 'user_avatar',
            'user.offline_image_url' => 'user_offline_banner',
            'user.view_count' => 'user_view_count',
            'user.email' => 'user_email',
            'user.created_at' => 'user_created',

            // Channel data mappings
            'channel.broadcaster_id' => 'channel_id',
            'channel.broadcaster_login' => 'channel_login', 
            'channel.broadcaster_name' => 'channel_name',
            'channel.broadcaster_language' => 'channel_language',
            'channel.game_id' => 'channel_game_id',
            'channel.game_name' => 'channel_game',
            'channel.title' => 'channel_title',
            'channel.delay' => 'channel_delay',
            'channel.tags' => 'channel_tags',
            'channel.content_classification_labels' => 'channel_content_labels',
            'channel.is_branded_content' => 'channel_is_branded',
            
            // Followers mappings - IMPORTANT: These fix your duplicate issue!
            'channel_followers.total' => 'followers_total',
            'channel_followers.data.0.user_id' => 'followers_latest_id',
            'channel_followers.data.0.user_login' => 'followers_latest_login',
            'channel_followers.data.0.user_name' => 'followers_latest_name',
            'channel_followers.data.0.followed_at' => 'followers_latest_date',
            
            // Followed channels mappings
            'followed_channels.total' => 'followed_total',
            'followed_channels.data.0.broadcaster_id' => 'followed_latest_id',
            'followed_channels.data.0.broadcaster_login' => 'followed_latest_login',
            'followed_channels.data.0.broadcaster_name' => 'followed_latest_name',
            'followed_channels.data.0.followed_at' => 'followed_latest_date',
            
            // Subscribers mappings - IMPORTANT: These fix your duplicate issue!
            'subscribers.points' => 'subscribers_points',
            'subscribers.total' => 'subscribers_total',
            'subscribers.data.0.broadcaster_id' => 'subscribers_latest_broadcaster_id',
            'subscribers.data.0.broadcaster_login' => 'subscribers_latest_broadcaster_login',
            'subscribers.data.0.broadcaster_name' => 'subscribers_latest_broadcaster_name',
            'subscribers.data.0.gifter_id' => 'subscribers_latest_gifter_id',
            'subscribers.data.0.gifter_login' => 'subscribers_latest_gifter_login',
            'subscribers.data.0.gifter_name' => 'subscribers_latest_gifter_name',
            'subscribers.data.0.is_gift' => 'subscribers_latest_is_gift',
            'subscribers.data.0.plan_name' => 'subscribers_latest_plan_name',
            'subscribers.data.0.tier' => 'subscribers_latest_tier',
            'subscribers.data.0.user_id' => 'subscribers_latest_user_id',
            'subscribers.data.0.user_name' => 'subscribers_latest_user_name',
            'subscribers.data.0.user_login' => 'subscribers_latest_user_login',

            // Goals mappings (empty in your case, but good to have)
            'goals.data.0.type' => 'goals_latest_type',
            'goals.data.0.target' => 'goals_latest_target',
            'goals.data.0.current' => 'goals_latest_current',
        ];

        // First, try exact match
        if (isset($standardNames[$jsonPath])) {
            return $standardNames[$jsonPath];
        }

        // If no exact match, try to build a logical name
        // Convert dots to underscores and handle common patterns
        $parts = explode('.', $jsonPath);
        
        // Handle array access like "data.0.field_name" -> prefix with parent + "latest_" + field
        if (count($parts) >= 3 && $parts[count($parts) - 2] === '0' && $parts[count($parts) - 3] === 'data') {
            // This is accessing first item in data array
            // Get the parent object name (like 'channel_followers', 'subscribers', etc.)
            $parentParts = array_slice($parts, 0, count($parts) - 3);
            $parentName = implode('_', $parentParts);
            $fieldName = end($parts);
            
            return $parentName . '_latest_' . $fieldName;
        }
        
        // Handle nested objects - join with underscores
        return implode('_', $parts);
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
                // FIX: Get standardized name for this path
                $standardizedName = $this->getStandardizedTagName($newPath);
                
                $this->createTag($standardizedName, $newPath, $value, $createdTags, $categories, $parentKey);
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
                // Create tags for each property of the first object
                foreach ($value[0] as $objKey => $objValue) {
                    $fullPath = "{$path}.0.{$objKey}";
                    
                    // Use getStandardizedTagName for array items
                    $standardizedName = $this->getStandardizedTagName($fullPath);
                    
                    $this->createTag(
                        $standardizedName,
                        $fullPath,
                        $objValue,
                        $createdTags,
                        $categories,
                        $key
                    );
                }
            }
        }

        // Create a tag for array length - FIX: Handle total field properly
        if (isset($value) && is_array($value)) {
            // For arrays with a separate 'total' field (like channel_followers, followed_channels)
            // The count tag will be handled by parseLevel when it encounters the 'total' field
            // So we just create a simple count tag here for the array length
            $countTagName = $this->getStandardizedTagName($path) . '_count';
            
            $this->createTag(
                $countTagName,
                $path,
                count($value),
                $createdTags,
                $categories,
                $key,
                'array_count'
            );
        }
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
     * Create a template tag - UPDATED to use standardized naming
     */
    private function createTag(
        string $tagName,  // This is now the standardized name
        string $jsonPath, 
        $sampleValue, 
        array &$createdTags, 
        array &$categories, 
        string $categoryName, 
        string $dataType = null
    ): void {
        // If no tagName provided, try to standardize the jsonPath
        if (empty($tagName)) {
            $tagName = $this->getStandardizedTagName($jsonPath);
        }

        // Determine data type if not provided
        if (!$dataType) {
            $dataType = $this->getDataType($sampleValue);
        }

        // Create display tag
        $displayTag = "[[[{$tagName}]]]";

        // Ensure category exists
        $this->createCategory($categoryName, $categories);

        // Debug logging to see what's being created
        Log::info('Creating template tag', [
            'tag_name' => $tagName,
            'json_path' => $jsonPath,
            'category' => $categoryName,
            'sample_value' => $sampleValue
        ]);

        // Create the tag data
        $createdTags[] = [
            'category_name' => $categoryName,
            'tag_name' => $tagName,  // Now uses standardized name!
            'display_tag' => $displayTag,
            'json_path' => $jsonPath,
            'data_type' => $dataType,
            'display_name' => $this->generateDisplayName($tagName),
            'description' => $this->generateDescription($tagName, $dataType),
            'sample_data' => $this->formatSampleData($sampleValue, $dataType),
            'formatting_options' => $this->getFormattingOptions($dataType, $tagName)
        ];
    }

    // ... rest of your methods remain the same ...

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
                    // Check if tag already exists
                    $existingTag = TemplateTag::where([
                        'category_id' => $category->id,
                        'tag_name' => $tagData['tag_name']
                    ])->first();

                    if ($existingTag) {
                        // Tag exists - only update structure, NOT sample data
                        $existingTag->update([
                            'display_tag' => $tagData['display_tag'],
                            'json_path' => $tagData['json_path'],
                            'data_type' => $tagData['data_type'],
                            'display_name' => $tagData['display_name'],
                            'description' => $tagData['description'],
                            'formatting_options' => $tagData['formatting_options'],
                            'is_active' => true,
                            // DON'T update sample_data here!
                        ]);
                    } else {
                        // New tag - create with sample data
                        TemplateTag::create(array_merge($tagData, ['category_id' => $category->id]));
                    }
                    
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
