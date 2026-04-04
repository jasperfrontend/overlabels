<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $description
 * @property string $html_content
 * @property string|null $css_content
 * @property array<array-key, mixed>|null $used_tags
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $used_template_tags
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\UserTemplateFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereCssContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereHtmlContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereUsedTags($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserTemplate whereUserId($value)
 * @mixin \Eloquent
 */
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
        'status',
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
        if (! $this->used_tags) {
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
