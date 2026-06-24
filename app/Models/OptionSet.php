<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A named, reusable list of values owned by a user. Pickers consume an
 * OptionSet to produce results. This is a Recipes-layer primitive; the
 * Recipe install flow creates these on behalf of an installer, but power
 * users can also create them directly via tinker / future DSL.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $recipe_instance_id
 * @property string $slug
 * @property string|null $label
 * @property array<int, array{id:int,value:string,added_at:int,label:?string,weight:int|float,color:?string}> $items
 * @property int $next_item_id Per-list id counter; the id to assign to the next appended item
 * @property int $min_items
 * @property int|null $max_items
 * @property bool $user_editable
 * @property Carbon|null $disabled_at
 * @property int|null $entry_ttl_seconds
 * @property Carbon|null $expires_at
 * @property array<string, string>|null $chat_permissions Action -> permission level overrides; NULL means use defaults
 * @property array{enabled:bool,types:array<int,string>}|null $event_feed Recent-events feed config; NULL means this list is not an event feed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read RecipeInstance|null $recipeInstance
 */
class OptionSet extends Model
{
    use HasFactory;

    public const string SLUG_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    protected $fillable = [
        'user_id',
        'recipe_instance_id',
        'slug',
        'label',
        'items',
        'next_item_id',
        'min_items',
        'max_items',
        'user_editable',
        'disabled_at',
        'entry_ttl_seconds',
        'expires_at',
        'chat_permissions',
        'event_feed',
    ];

    protected $casts = [
        'items' => 'array',
        'next_item_id' => 'integer',
        'min_items' => 'integer',
        'max_items' => 'integer',
        'user_editable' => 'boolean',
        'disabled_at' => 'datetime',
        'entry_ttl_seconds' => 'integer',
        'expires_at' => 'datetime',
        'chat_permissions' => 'array',
        'event_feed' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this list is an enabled recent-events feed - i.e. incoming
     * Twitch / external events should be appended to it.
     */
    public function eventFeedEnabled(): bool
    {
        return (bool) ($this->event_feed['enabled'] ?? false);
    }

    /**
     * The event_type whitelist for the feed. An empty array means "every
     * event type" - the feed accepts anything.
     *
     * @return array<int, string>
     */
    public function eventFeedTypes(): array
    {
        $types = $this->event_feed['types'] ?? [];

        return is_array($types) ? array_values(array_filter($types, 'is_string')) : [];
    }

    /**
     * Does the feed accept this event_type? Empty whitelist = accept all.
     */
    public function eventFeedAccepts(string $eventType): bool
    {
        $types = $this->eventFeedTypes();

        return $types === [] || in_array($eventType, $types, true);
    }

    public function pickers(): HasMany
    {
        return $this->hasMany(Picker::class);
    }

    public function recipeInstance(): BelongsTo
    {
        return $this->belongsTo(RecipeInstance::class);
    }
}
