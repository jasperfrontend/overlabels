<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'html_content',
        'css_content',
        'used_tags',
        'status'
    ];

    protected $casts = [
        'used_tags' => 'array',
    ];

    /**
     * Get the user who owns this template
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the template tags used in this template
     */
    public function getUsedTemplateTagsAttribute()
    {
        if (!$this->used_tags) {
            return collect();
        }

        return TemplateTag::whereIn('id', $this->used_tags)->get();
    }

    /**
     * Parse the template and replace all [[[tags]]] with actual data
     */
    public function renderWithData(array $jsonData): string
    {
        $html = $this->html_content;
        
        // Find all [[[tag]]] patterns in the HTML
        preg_match_all('/\[\[\[([^\]]+)\]\]\]/', $html, $matches);
        
        foreach ($matches[1] as $tagName) {
            $tag = TemplateTag::where('tag_name', $tagName)->first();
            
            if ($tag) {
                $data = $tag->getFormattedOutput($jsonData);
                $html = str_replace("[[[{$tagName}]]]", $data, $html);
            }
        }
        
        return $html;
    }
}