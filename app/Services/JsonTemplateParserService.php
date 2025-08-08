<?php

namespace App\Services;

use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * JsonTemplateParserService
 *
 * Generates template tags from JSON data and saves them to the database.
 * Now uses TemplateDataMapperService for consistent mapping logic.
 */
class JsonTemplateParserService
{
    // Max scalar index tags per array (0 -> N-1) for allowlisted arrays
    private const int ARRAY_INDEX_LIMIT = 0;

    // Only these BASE standardized tags will get scalar _0, _1, ... tags
    // e.g. 'channel.tags' → standardized 'channel_tags' (in your mapper)
    private const array INDEXED_ARRAY_BASE_TAGS = [
        'channel_tags'
    ];

    // Arrays we do not recurse deeply (keep your existing list)
    private array $limitedArrays = [
        'followed_channels.data',
        'channel_followers.data',
        'subscribers.data',
    ];

    private TemplateDataMapperService $templateDataMapper;

    public function __construct(TemplateDataMapperService $templateDataMapper)
    {
        $this->templateDataMapper = $templateDataMapper;
    }

    /**
     * Generate only STANDARD tags that are consistent for everyone
     */
    public function parseJsonAndCreateTags(array $jsonData): array
    {
        $createdTags = [];
        $categories = [];
        $processedPaths = []; // Track processed paths to prevent duplicates

        // Get category definitions from the mapper service
        $categoryDefinitions = $this->templateDataMapper->getTagCategories();

        // Create categories first
        foreach ($categoryDefinitions as $categoryKey => $categoryInfo) {
            $this->createCategory($categoryKey, $categoryInfo['display_name'], $categoryInfo['description'], $categories);
        }

        // Parse data and create tags using centralized mapping
        $this->parseLevel($jsonData, '', $createdTags, $categories, '', $processedPaths);

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
     * Parse a level of JSON data recursively
     * Enhanced with debugging for subscriber data and duplicate prevention
     */
    private function parseLevel(array $data, string $path, array &$createdTags, array &$categories, string $parentKey = '', array &$processedPaths = []): void
    {
        foreach ($data as $key => $value) {
            $currentPath = $path ? "$path.$key" : $key;

            if (is_array($value)) {
                $this->handleArrayValue($key, $value, $currentPath, $createdTags, $categories, $parentKey, $processedPaths);
            } else {
                // Create a tag for this value using the centralized mapper
                $this->createTagFromValue($key, $value, $currentPath, $createdTags, $categories, '', $processedPaths);
            }
        }
    }

    /**
     * Handle array values in the JSON structure
     * Fixed to prevent duplicate tag creation and "Undefined array key 0" errors
     */
    private function handleArrayValue(
        string $key,
        array $value,
        string $path,
        array &$createdTags,
        array &$categories,
        string $parentKey,
        array &$processedPaths = []
    ): void {
        // Let the unified array logic run (this handles count, latest, and limited indexing)
        $this->createTagFromValue($key, $value, $path, $createdTags, $categories, $parentKey, $processedPaths);

        // Check if this is a limited array - if so, DON'T recurse into individual items
        $isLimitedArray = in_array($path, $this->limitedArrays, true);
        if ($isLimitedArray) {
            // For limited arrays, we've already handled everything we need in createTagFromValue
            // (count, latest fields, etc.) - no need to recurse further
            return;
        }

        // For non-limited arrays, recurse normally (but skip numeric keys for data arrays)
        foreach ($value as $k => $v) {

            $childPath = "$path.$k";

            if (is_array($v)) {
                $this->parseLevel($v, $childPath, $createdTags, $categories, $key, $processedPaths);
            } else {
                $this->createTagFromValue((string)$k, $v, $childPath, $createdTags, $categories, $key, $processedPaths);
            }
        }
    }



    /**
     * Create a template tag from a value using the centralized mapper
     * Enhanced with duplicate prevention
     */
    private function createTagFromValue(
        string $key,
               $value,
        string $jsonPath,
        array &$createdTags,
        array &$categories,
        string $parentKey = '',
        array &$processedPaths = []
    ): void {
        // De-dup by path (not by tag name)
        if (in_array($jsonPath, $processedPaths, true)) {
            return;
        }
        $processedPaths[] = $jsonPath;

        $dataType = $this->getDataType($value);

        // Standardized base tag from mapper (or fallback)
        $baseTag = $this->templateDataMapper->getStandardizedTagName($jsonPath);
        if (!$baseTag) {
            $baseTag = str_replace('.', '_', $jsonPath);
        }

        // Category ensure once
        $categoryName = $this->determineCategoryForTag($baseTag);
        if (!isset($categories[$categoryName])) {
            $categoryDefinitions = $this->templateDataMapper->getTagCategories();
            if (isset($categoryDefinitions[$categoryName])) {
                $this->createCategory(
                    $categoryName,
                    $categoryDefinitions[$categoryName]['display_name'],
                    $categoryDefinitions[$categoryName]['description'],
                    $categories
                );
            } else {
                $this->createCategory(
                    $categoryName,
                    ucfirst($categoryName),
                    "Template tags related to $categoryName",
                    $categories
                );
            }
        }

        // ===== ARRAY HANDLING =====
        if ($dataType === 'array' && is_array($value)) {
            // 1) Base array tag (so [[[foreach:...]]] works)
            $this->createTag(
                $baseTag,
                $jsonPath,
                array_slice($value, 0, 3), // small sample for DB display
                $createdTags,
                $categories,
                $categoryName
            );

            // 2) Count tag
            $countTag = "{$baseTag}_count";
            $countPath = "{$jsonPath}_count";
            if (!$this->tagExists($createdTags, $countTag)) {
                $this->createTag(
                    $countTag,
                    $countPath,
                    count($value),
                    $createdTags,
                    $categories,
                    $categoryName
                );
            }

            // 3) First-item scalar fields for arrays-of-objects → enables *latest* mappings
            // Example: channel_followers.data.0.user_name -> followers_latest_user_name
            $firstItem = reset($value);
            if (is_array($firstItem) && !empty($firstItem)) {
                foreach ($firstItem as $objKey => $objVal) {
                    if (is_scalar($objVal) || $this->getDataType($objVal) !== 'array') {
                        $firstPath = "$jsonPath.0.$objKey";
                        // This will hit mapper's '...data.0.*' rules → *_latest_* tag names
                        $this->createTagFromValue((string)$objKey, $objVal, $firstPath, $createdTags, $categories, $key, $processedPaths);
                    }
                }
            }

            // 4) Optional scalar indexing for allowlisted scalar arrays (e.g., channel.tags)
            // ONLY for simple scalar arrays, NOT for arrays of objects
            $limit = self::ARRAY_INDEX_LIMIT;
            $baseIsIndexed = in_array($baseTag, self::INDEXED_ARRAY_BASE_TAGS, true);
            if ($baseIsIndexed && !empty($value)) {
                // Check if this is a simple scalar array
                $firstItem = reset($value);
                $isScalarArray = is_scalar($firstItem);

                if ($isScalarArray) {
                    // Only create indexed tags for simple scalar arrays
                    $slice = array_slice($value, 0, $limit, true);
                    foreach ($slice as $index => $item) {
                        if (is_scalar($item)) {
                            $indexTag = "{$baseTag}_$index";
                            $indexPath = "$jsonPath.$index";
                            if (!$this->tagExists($createdTags, $indexTag)) {
                                $this->createTag(
                                    $indexTag,
                                    $indexPath,
                                    $item,
                                    $createdTags,
                                    $categories,
                                    $categoryName
                                );
                            }
                        }
                    }
                }
                // Do NOT create indexed tags for arrays of objects
            }

            return; // done with arrays
        }

        // ===== NON-ARRAY =====
        if (!$this->tagExists($createdTags, $baseTag)) {
            $this->createTag(
                $baseTag,
                $jsonPath,
                $value,
                $createdTags,
                $categories,
                $categoryName
            );
        }
    }

    /** helper */
    private function tagExists(array $createdTags, string $tagName): bool
    {
        foreach ($createdTags as $existingTag) {
            if (($existingTag['tag_name'] ?? null) === $tagName) {
                return true;
            }
        }
        return false;
    }


    /**
     * Determine which category a tag belongs to based on its name
     */
    private function determineCategoryForTag(string $tagName): string
    {
        $categoryDefinitions = $this->templateDataMapper->getTagCategories();

        // Check each category to see if this tag belongs to it
        foreach ($categoryDefinitions as $categoryKey => $categoryInfo) {
            if (in_array($tagName, $categoryInfo['tags'])) {
                return $categoryKey;
            }
        }

        // Default category if not found
        if (str_starts_with($tagName, 'user_')) return 'user';
        if (str_starts_with($tagName, 'channel_')) return 'channel';
        if (str_starts_with($tagName, 'followers_')) return 'followers';
        if (str_starts_with($tagName, 'followed_')) return 'followed';
        if (str_starts_with($tagName, 'subscribers_')) return 'subscribers';
        if (str_starts_with($tagName, 'goals_')) return 'goals';
        if (str_starts_with($tagName, 'overlay_')) return 'overlay';

        return 'other';
    }

    /**
     * Create a category if it doesn't exist
     */
    private function createCategory(string $name, string $displayName, string $description, array &$categories): void
    {
        if (!isset($categories[$name])) {
            $categories[$name] = [
                'name' => $name,
                'display_name' => $displayName,
                'description' => $description,
                'is_group' => false,
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
        string $categoryName
    ): void {
        // Determine data type
        $dataType = $this->getDataType($sampleValue);

        // Create display tag
        $displayTag = "[[[$tagName]]]";

        // Debug logging
        Log::info('Creating template tag', [
            'tag_name' => $tagName,
            'json_path' => $jsonPath,
            'category' => $categoryName,
            'sample_value' => $sampleValue
        ]);

        // Create the tag data
        $createdTags[] = [
            'category_name' => $categoryName,
            'tag_name' => $tagName,
            'display_tag' => $displayTag,
            'display_name' => $this->generateDisplayName($tagName),
            'description' => $this->generateDescription($tagName, $dataType),
            'data_type' => $dataType,
            'json_path' => $jsonPath,
            'sample_data' => $this->formatSampleData($sampleValue, $dataType),
            'formatting_options' => $this->getFormattingOptions($dataType, $tagName),
            'is_active' => true,
            'created_by_system' => true,
        ];
    }

    /**
     * Save generated tags to the database
     */
    public function saveTagsToDatabase(array $tagData): array
    {
        $saved = [
            'categories' => 0,
            'tags' => 0,
            'errors' => [],
            'updated_tags' => 0
        ];

        try {
            // Save categories first
            foreach ($tagData['categories'] as $categoryData) {
                $category = TemplateTagCategory::firstOrCreate(
                    ['name' => $categoryData['name']],
                    $categoryData
                );

                if ($category->wasRecentlyCreated) {
                    $saved['categories']++;
                }
            }

            // Save tags
            foreach ($tagData['tags'] as $tagData) {
                $category = TemplateTagCategory::where('name', $tagData['category_name'])->first();

                if ($category) {
                    $existingTag = TemplateTag::where('tag_name', $tagData['tag_name'])->first();

                    if ($existingTag) {
                        // Update an existing tag with new sample data
                        $existingTag->update([
                            'sample_data' => $tagData['sample_data'],
                            'json_path' => $tagData['json_path'],
                            'description' => $tagData['description']
                        ]);
                        $saved['updated_tags']++;
                    } else {
                        // Create new tag
                        TemplateTag::create(array_merge($tagData, ['category_id' => $category->id]));
                        $saved['tags']++;
                    }
                } else {
                    $saved['errors'][] = "Category not found for tag: {$tagData['tag_name']}";
                }
            }

        } catch (Exception $e) {
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

    /**
     * Get the data type from a value
     */
    private function getDataType($value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value)) {
            return 'array';
        }

        if (is_string($value)) {
            // Check if it's a date
            if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                return 'datetime';
            }

            // Check if it's a URL
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return 'url';
            }

            return 'string';
        }

        return 'unknown';
    }

    /**
     * Generate a human-readable display name from tag name
     */
    private function generateDisplayName(string $tagName): string
    {
        // Remove common prefixes for cleaner display names
        $cleanName = preg_replace('/^(user_|channel_|followers_|followed_|subscribers_|goals_|overlay_)/', '', $tagName);

        // Convert underscores to spaces and title case
        return Str::title(str_replace('_', ' ', $cleanName));
    }

    /**
     * Generate description for a tag
     */
    private function generateDescription(string $tagName, string $dataType): string
    {
        // Use the centralized descriptions from TemplateDataMapperService
        $availableTags = $this->templateDataMapper->getAvailableTemplateTags();

        if (isset($availableTags[$tagName])) {
            return $availableTags[$tagName];
        }

        // Fallback to generated description
        $baseDescription = match($dataType) {
            'datetime' => 'Date and time value',
            'integer' => 'Numeric value',
            'float' => 'Decimal number',
            'boolean' => 'Yes/No value',
            'url' => 'Website URL',
            'array' => 'List of values',
            default => 'Text value'
        };

        return "Template tag for {$this->generateDisplayName($tagName)}. $baseDescription.";
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
            // Store only the first few items for arrays
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
            $options['max_items'] = 3;
        }

        return empty($options) ? null : $options;
    }
}
