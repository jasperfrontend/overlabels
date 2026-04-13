<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_group
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $user_id
 * @property-read Collection<int, TemplateTag> $activeTemplateTags
 * @property-read int|null $active_template_tags_count
 * @property-read Collection<int, TemplateTag> $templateTags
 * @property-read int|null $template_tags_count
 * @property-read User|null $user
 * @method static Builder<static>|TemplateTagCategory newModelQuery()
 * @method static Builder<static>|TemplateTagCategory newQuery()
 * @method static Builder<static>|TemplateTagCategory query()
 * @method static Builder<static>|TemplateTagCategory whereCreatedAt($value)
 * @method static Builder<static>|TemplateTagCategory whereDescription($value)
 * @method static Builder<static>|TemplateTagCategory whereDisplayName($value)
 * @method static Builder<static>|TemplateTagCategory whereId($value)
 * @method static Builder<static>|TemplateTagCategory whereIsGroup($value)
 * @method static Builder<static>|TemplateTagCategory whereName($value)
 * @method static Builder<static>|TemplateTagCategory whereSortOrder($value)
 * @method static Builder<static>|TemplateTagCategory whereUpdatedAt($value)
 * @method static Builder<static>|TemplateTagCategory whereUserId($value)
 * @mixin Eloquent
 * @mixin IdeHelperTemplateTagCategory
 */
class TemplateTagCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'display_name',
        'description',
        'is_group',
        'sort_order',
    ];

    protected $casts = [
        'is_group' => 'boolean',
    ];

    /**
     * Get the user this category belongs to
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all template tags in this category
     */
    public function templateTags(): HasMany
    {
        return $this->hasMany(TemplateTag::class, 'category_id');
    }

    /**
     * Get active template tags in this category
     */
    public function activeTemplateTags(): HasMany
    {
        return $this->templateTags()->where('is_active', true);
    }
}
