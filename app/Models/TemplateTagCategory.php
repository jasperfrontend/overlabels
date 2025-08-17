<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class TemplateTagCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'display_name', 
        'description',
        'is_group',
        'sort_order'
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
