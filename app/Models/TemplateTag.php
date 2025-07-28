<?php 

namespace App\Models;

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
    ];

    /**
     * Get the category this tag belongs to
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TemplateTagCategory::class, 'category_id');
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
    public function formatData($data)
    {
        if (!$this->formatting_options) {
            return $data;
        }

        // Handle date formatting
        if (isset($this->formatting_options['date_format']) && $data) {
            try {
                return \Carbon\Carbon::parse($data)->format($this->formatting_options['date_format']);
            } catch (\Exception $e) {
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

        return $data;
    }

    /**
     * Get the complete formatted output for this tag
     */
    public function getFormattedOutput(array $jsonData)
    {
        $data = $this->extractDataFromJson($jsonData);
        return $this->formatData($data);
    }
}