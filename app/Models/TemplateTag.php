<?php

namespace App\Models;

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;

class TemplateTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'tag_name',
        'display_tag',
        'json_path',
        'data_type',
        'tag_type',
        'version',
        'is_editable',
        'original_tag_name',
        'display_name',
        'description',
        'sample_data',
        'formatting_options',
        'is_active'
    ];

    protected $casts = [
        'sample_data' => 'array',
        'formatting_options' => 'array',
        'is_active' => 'boolean',
        'is_editable' => 'boolean',  // NEW
    ];

    /**
     * Get the category this tag belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TemplateTagCategory::class, 'category_id');
    }

    /**
     * Scope for standard (non-editable) tags
     */
    public function scopeStandard($query)
    {
        return $query->where('tag_type', 'standard');
    }

    /**
     * Scope for custom (user-editable) tags
     */
    public function scopeCustom($query)
    {
        return $query->where('tag_type', 'custom');
    }

    /**
     * Create a custom variant of this tag
     * @throws Exception
     */
    public function createCustomVariant(string $newTagName, array $customOptions = []): self
    {
        if ($this->tag_type === 'custom') {
            throw new Exception('Cannot create custom variant of a custom tag');
        }

        return self::create([
            'category_id' => $this->category_id,
            'tag_name' => $newTagName,
            'display_tag' => "[[[{$newTagName}]]]",
            'json_path' => $this->json_path,
            'data_type' => $this->data_type,
            'tag_type' => 'custom',
            'version' => '1.0',
            'is_editable' => true,
            'original_tag_name' => $this->tag_name,
            'display_name' => $customOptions['display_name'] ?? $this->display_name,
            'description' => $customOptions['description'] ?? "Custom variant of {$this->tag_name}",
            'sample_data' => $this->sample_data,
            'formatting_options' => array_merge($this->formatting_options ?? [], $customOptions['formatting_options'] ?? []),
            'is_active' => true,
        ]);
    }

    /**
     * Extract data from JSON using the stored path
     */
    public function extractDataFromJson(array $jsonData)
    {
        return Arr::get($jsonData, $this->json_path);
    }

    /**
     * Format the extracted data based on formatting options
     */
    public function formatData($data): string
    {
        if (!$this->formatting_options) {
            return $data;
        }

        // Handle date formatting
        if (isset($this->formatting_options['date_format']) && $data) {
            try {
                return Carbon::parse($data)->format($this->formatting_options['date_format']);
            } catch (Exception $e) {
                return $data;
            }
        }

        // Handle number formatting
        if (isset($this->formatting_options['number_format']) && is_numeric($data)) {
            return number_format($data, $this->formatting_options['number_format']['decimals'] ?? 0);
        }

        // Handle array to string conversion
        if (is_array($data) && isset($this->formatting_options['array_join'])) {
            return implode($this->formatting_options['array_join'], $data);
        }
        if ($data === null) {
            return "N/A";
        }

        return $data;
    }

    /**
     * Get the complete formatted output for this tag
     */
    public function getFormattedOutput(array $jsonData): string
    {
        $data = $this->extractDataFromJson($jsonData);
        return $this->formatData($data);
    }
}
