<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A chat-command trigger materialised from a Recipe manifest's
 * triggers[].kind === "chat_command". Fires a Picker on invocation;
 * does not produce chat output (announcement is the Bot Expression
 * layer's job, optionally bundled by a Kit).
 *
 * @property int $id
 * @property int $recipe_instance_id
 * @property int $user_id
 * @property int $picker_id
 * @property string $command           Without leading "!"
 * @property string $permission_level
 * @property int $cooldown_seconds
 * @property \Illuminate\Support\Carbon|null $last_fired_at
 * @property bool $enabled
 * @property-read RecipeInstance|null $recipeInstance
 * @property-read User|null $user
 * @property-read Picker|null $picker
 */
class RecipeChatTrigger extends Model
{
    use HasFactory;

    protected $fillable = [
        'recipe_instance_id',
        'user_id',
        'picker_id',
        'command',
        'permission_level',
        'cooldown_seconds',
        'last_fired_at',
        'enabled',
    ];

    protected $casts = [
        'cooldown_seconds' => 'integer',
        'last_fired_at' => 'datetime',
        'enabled' => 'boolean',
    ];

    public function recipeInstance(): BelongsTo
    {
        return $this->belongsTo(RecipeInstance::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(Picker::class);
    }
}
