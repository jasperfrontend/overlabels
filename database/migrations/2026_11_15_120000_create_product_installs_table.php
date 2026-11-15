<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every product install, kept after the uninstall. One row per install:
 * when it happened, when an overlay link first showed it, and when it was
 * uninstalled. The welcome funnel only records the first install of an
 * account; this is the history behind it. See App\Models\ProductInstall.
 *
 * Backfilled from what still exists: every live install (its first load
 * read from the access log, which only reaches back 90 days), plus an
 * uninstalled row with no date for each account whose funnel records an
 * install that is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_installs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_slug', 100);
            // Null once the install is gone, whichever way it went.
            $table->foreignId('recipe_instance_id')->nullable()->constrained()->nullOnDelete();
            // The static overlays whose first load counts as "on stream".
            // Empty for an alerts-only product: any static overlay counts.
            $table->jsonb('static_overlay_ids')->default(DB::raw("'[]'::jsonb"));
            $table->timestamp('installed_at');
            $table->timestamp('first_loaded_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();

            $table->index(['user_id', 'recipe_instance_id']);
            $table->index('installed_at');
        });

        $this->backfillLiveInstalls();
        $this->backfillGoneInstalls();
    }

    public function down(): void
    {
        Schema::dropIfExists('product_installs');
    }

    private function backfillLiveInstalls(): void
    {
        $instances = DB::table('recipe_instances')
            ->join('recipes', 'recipes.id', '=', 'recipe_instances.recipe_id')
            ->select(['recipe_instances.id', 'recipe_instances.user_id', 'recipe_instances.created_at', 'recipe_instances.primitive_map', 'recipes.slug'])
            ->orderBy('recipe_instances.id')
            ->get();

        foreach ($instances as $instance) {
            $map = json_decode((string) $instance->primitive_map, true) ?: [];
            $overlays = DB::table('overlay_templates')
                ->whereIn('id', array_values($map['overlays'] ?? []))
                ->where('type', 'static')
                ->get(['id', 'slug']);

            $loads = DB::table('overlay_access_logs')
                ->join('overlay_access_tokens', 'overlay_access_tokens.id', '=', 'overlay_access_logs.token_id')
                ->where('overlay_access_tokens.user_id', $instance->user_id)
                ->whereRaw("coalesce(overlay_access_tokens.metadata->>'purpose', '') <> 'chat_designer'")
                ->where('overlay_access_logs.accessed_at', '>=', $instance->created_at)
                ->whereNotNull('overlay_access_logs.template_slug');

            if ($overlays->isNotEmpty()) {
                $loads->whereIn('overlay_access_logs.template_slug', $overlays->pluck('slug'));
            } else {
                $loads->whereIn('overlay_access_logs.template_slug', DB::table('overlay_templates')
                    ->where('owner_id', $instance->user_id)
                    ->where('type', 'static')
                    ->select('slug'));
            }

            DB::table('product_installs')->insert([
                'user_id' => $instance->user_id,
                'product_slug' => $instance->slug,
                'recipe_instance_id' => $instance->id,
                'static_overlay_ids' => json_encode($overlays->pluck('id')->values()->all()),
                'installed_at' => $instance->created_at,
                'first_loaded_at' => $loads->min('overlay_access_logs.accessed_at'),
            ]);
        }
    }

    private function backfillGoneInstalls(): void
    {
        $users = DB::table('users')
            ->whereNotNull(DB::raw("preferences->'welcome_funnel'->>'installed_slug'"))
            ->get(['id', 'preferences']);

        foreach ($users as $user) {
            $funnel = (json_decode((string) $user->preferences, true) ?: [])['welcome_funnel'] ?? [];
            $slug = $funnel['installed_slug'] ?? null;
            $at = $funnel['installed_at'] ?? null;

            if (! is_string($slug) || ! is_int($at)) {
                continue;
            }

            $known = DB::table('product_installs')->where('user_id', $user->id)->where('product_slug', $slug)->exists();
            if ($known) {
                continue;
            }

            DB::table('product_installs')->insert([
                'user_id' => $user->id,
                'product_slug' => $slug,
                'recipe_instance_id' => null,
                'static_overlay_ids' => '[]',
                'installed_at' => date('Y-m-d H:i:s', $at),
            ]);
        }
    }
};
