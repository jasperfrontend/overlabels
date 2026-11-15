<?php

namespace App\Models;

use App\Support\ProductDesigner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One product install, kept after the uninstall: installed, first shown by
 * an overlay link, uninstalled. The welcome funnel records an account's
 * first install only; this is every install after it too, read by the admin
 * funnel page.
 *
 * Written at three moments and nowhere else: RecipeInstaller::install()
 * opens the row, the overlay render endpoint stamps the first load, and
 * RecipeInstaller::uninstall() closes it. `recipe_instance_id` goes null
 * when the install row is deleted, so a live install is one that still has
 * it. A null `uninstalled_at` on a closed row means the uninstall predates
 * this table and its date is unknown.
 *
 * @property int $id
 * @property int $user_id
 * @property string $product_slug
 * @property int|null $recipe_instance_id
 * @property list<int> $static_overlay_ids
 * @property Carbon $installed_at
 * @property Carbon|null $first_loaded_at
 * @property Carbon|null $uninstalled_at
 * @property-read User|null $user
 * @property-read RecipeInstance|null $instance
 */
class ProductInstall extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'product_slug',
        'recipe_instance_id',
        'static_overlay_ids',
        'installed_at',
        'first_loaded_at',
        'uninstalled_at',
    ];

    protected $casts = [
        'static_overlay_ids' => 'array',
        'installed_at' => 'datetime',
        'first_loaded_at' => 'datetime',
        'uninstalled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(RecipeInstance::class, 'recipe_instance_id');
    }

    /**
     * Open the row for a fresh install. The static overlays it created are
     * the ones whose load counts; an alerts-only product has none, and any
     * static overlay of the account counts instead, because that is where
     * its alerts play.
     */
    public static function opened(RecipeInstance $instance, string $productSlug): self
    {
        $static = OverlayTemplate::whereIn('id', array_values($instance->primitive_map['overlays'] ?? []))
            ->where('type', 'static')
            ->pluck('id')
            ->values()
            ->all();

        return self::create([
            'user_id' => $instance->user_id,
            'product_slug' => $productSlug,
            'recipe_instance_id' => $instance->id,
            'static_overlay_ids' => $static,
            'installed_at' => now(),
        ]);
    }

    /** Stamp the uninstall. Called before the install row is deleted. */
    public static function closed(RecipeInstance $instance): void
    {
        self::where('recipe_instance_id', $instance->id)
            ->whereNull('uninstalled_at')
            ->update(['uninstalled_at' => now()]);
    }

    /**
     * An overlay link just served this overlay: the first load of every live
     * install it belongs to. The designer's preview link is not a load, for
     * the same reason ProductController::realLinks() leaves it out, and a
     * link only counts on its own account's overlays.
     */
    public static function loaded(OverlayAccessToken $token, OverlayTemplate $template): void
    {
        if ($template->type !== 'static'
            || $template->owner_id !== $token->user_id
            || ($token->metadata['purpose'] ?? null) === ProductDesigner::TOKEN_PURPOSE) {
            return;
        }

        self::where('user_id', $token->user_id)
            ->whereNotNull('recipe_instance_id')
            ->whereNull('first_loaded_at')
            ->where(fn ($query) => $query
                ->whereJsonContains('static_overlay_ids', $template->id)
                ->orWhereJsonLength('static_overlay_ids', 0))
            ->update(['first_loaded_at' => now()]);
    }
}
