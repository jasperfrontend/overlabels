<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int|mixed $owner_id
 * @property string $title
 * @property string $description
 * @property bool $is_public
 * @property string|mixed $thumbnail
 * @property mixed $templates
 */
class Kit extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'description',
        'thumbnail',
        'is_public',
        'forked_from_id',
        'fork_count',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'fork_count' => 'integer',
    ];

    protected $appends = [
        'thumbnail_url',
    ];

    /**
     * Boot method
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleting(function ($kit) {
            // Prevent deletion if kit has been forked
            if ($kit->fork_count > 0) {
                throw new Exception('Cannot delete a kit that has been forked.');
            }

            // Delete thumbnail if exists and is local storage (not Cloudinary URL)
            if ($kit->thumbnail && ! filter_var($kit->thumbnail, FILTER_VALIDATE_URL) && Storage::disk('public')->exists($kit->thumbnail)) {
                Storage::disk('public')->delete($kit->thumbnail);
            }
        });
    }

    /**
     * Owner relationship
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Templates relationship
     */
    public function templates(): BelongsToMany
    {
        return $this->belongsToMany(
            OverlayTemplate::class,
            'kit_templates',
            'kit_id',
            'overlay_template_id'
        )->withTimestamps();
    }

    /**
     * Fork parent relationship
     */
    public function forkedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'forked_from_id');
    }

    /**
     * Forks relationship
     */
    public function forks(): HasMany
    {
        return $this->hasMany(self::class, 'forked_from_id');
    }

    /**
     * Fork this kit for a user
     */
    public function fork(User $user): self
    {
        // Create the new kit
        $fork = $this->replicate();
        $fork->owner_id = $user->id;
        $fork->forked_from_id = $this->id;
        $fork->title = 'Fork of '.Str::limit($this->title, 80);
        $fork->fork_count = 0;
        $fork->save();

        // Fork all templates in the kit
        $forkedTemplates = [];
        foreach ($this->templates as $template) {
            $forkedTemplate = $template->fork($user);
            $forkedTemplates[] = $forkedTemplate->id;
        }

        // Attach forked templates to the new kit
        $fork->templates()->attach($forkedTemplates);

        // Increment fork count on original
        $this->increment('fork_count');

        return $fork;
    }

    /**
     * Check if the user can delete this kit
     */
    public function canBeDeleted(): bool
    {
        return $this->fork_count === 0;
    }

    /**
     * Get thumbnail URL
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail) {
            return null;
        }

        // If the thumbnail is already a full URL (Cloudinary), return as-is
        if (filter_var($this->thumbnail, FILTER_VALIDATE_URL)) {
            return $this->thumbnail;
        }

        // Legacy support for local storage files
        return Storage::disk('public')->url($this->thumbnail);
    }

    /**
     * Scope for public kits
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope for user's kits
     */
    public function scopeOwnedBy($query, $userId)
    {
        return $query->where('owner_id', $userId);
    }
}
