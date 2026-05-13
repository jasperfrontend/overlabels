<?php

namespace App\Models;

use App\Events\PickerLanded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Random\RandomException;
use RuntimeException;

/**
 * A Picker is the RNG / selection engine over an OptionSet. Calling
 * fire() picks one item, stores it as last_result, optionally marks
 * that index consumed, and broadcasts PickerLanded so consumers
 * (alerts, bot expressions, the future recipe-layer control bridge)
 * can react.
 *
 * Recipes-layer primitive. Power-user accessible via tinker today;
 * the Recipe install flow will create these on installers' behalf
 * once that machinery lands.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $recipe_instance_id
 * @property int $option_set_id
 * @property string $slug
 * @property string|null $label
 * @property bool $consume_on_pick
 * @property array<int, int> $consumed_indices
 * @property string $concurrency
 * @property bool $user_editable
 * @property string|null $last_result
 * @property int|null $last_result_index
 * @property \Illuminate\Support\Carbon|null $last_result_at
 * @property bool $is_running
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read OptionSet|null $optionSet
 * @property-read RecipeInstance|null $recipeInstance
 */
class Picker extends Model
{
    use HasFactory;

    public const string SLUG_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    public const string CONCURRENCY_REJECT = 'reject_if_running';

    public const string CONCURRENCY_CANCEL = 'cancel_running';

    public const string CONCURRENCY_ALLOW = 'allow';

    public const array CONCURRENCY_MODES = [
        self::CONCURRENCY_REJECT,
        self::CONCURRENCY_CANCEL,
        self::CONCURRENCY_ALLOW,
    ];

    protected $fillable = [
        'user_id',
        'recipe_instance_id',
        'option_set_id',
        'slug',
        'label',
        'consume_on_pick',
        'consumed_indices',
        'concurrency',
        'user_editable',
        'last_result',
        'last_result_index',
        'last_result_at',
        'is_running',
    ];

    protected $casts = [
        'consume_on_pick' => 'boolean',
        'consumed_indices' => 'array',
        'user_editable' => 'boolean',
        'last_result_index' => 'integer',
        'last_result_at' => 'datetime',
        'is_running' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function optionSet(): BelongsTo
    {
        return $this->belongsTo(OptionSet::class);
    }

    public function recipeInstance(): BelongsTo
    {
        return $this->belongsTo(RecipeInstance::class);
    }

    /**
     * Picks one item from the linked OptionSet, records it on this row,
     * and broadcasts PickerLanded. Returns the picked value, or null if
     * the picker is busy / has nothing pickable.
     *
     * @throws RandomException
     */
    public function fire(): ?string
    {
        return DB::transaction(function () {
            /** @var self|null $locked */
            $locked = self::with('optionSet', 'user')
                ->lockForUpdate()
                ->find($this->id);

            if (! $locked) {
                return null;
            }

            if ($locked->concurrency === self::CONCURRENCY_REJECT && $locked->is_running) {
                return null;
            }

            if (! $locked->optionSet) {
                throw new RuntimeException("Picker {$locked->id} has no linked option set");
            }

            $items = $locked->optionSet->items ?? [];
            $consumed = $locked->consumed_indices ?? [];

            $availableIndices = array_values(array_diff(
                array_keys($items),
                $consumed
            ));

            if (empty($availableIndices)) {
                return null;
            }

            $pickedIndex = $availableIndices[random_int(0, count($availableIndices) - 1)];
            $result = (string) $items[$pickedIndex];
            $resultAt = now();

            $locked->last_result = $result;
            $locked->last_result_index = $pickedIndex;
            $locked->last_result_at = $resultAt;
            $locked->is_running = false;

            if ($locked->consume_on_pick) {
                $consumed[] = $pickedIndex;
                $locked->consumed_indices = $consumed;
            }

            $locked->save();

            if ($locked->user?->twitch_id) {
                PickerLanded::dispatch(
                    $locked->id,
                    $locked->slug,
                    $result,
                    $pickedIndex,
                    $resultAt->timestamp,
                    (string) $locked->user->twitch_id,
                );
            }

            $this->refresh();

            return $result;
        });
    }

    /**
     * Clear the consumed_indices list so a raffle-style picker can be
     * reused. Idempotent.
     */
    public function resetConsumed(): void
    {
        $this->update(['consumed_indices' => []]);
    }
}
