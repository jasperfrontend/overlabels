<?php

namespace App\Services\Alerts;

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * One Alerts overlay per account, and where an alert renders.
 *
 * Alerts never render on their own: they render inside a static overlay that
 * is in OBS. Before this, an alert with no targeting rendered in EVERY static
 * overlay of the account, so a streamer with two overlays in OBS saw every
 * alert twice, and one with none saw nothing at all. Now each account has one
 * Alerts overlay, a transparent static overlay that exists to hold alerts, and
 * an untargeted alert renders there only.
 *
 * Only once OBS has loaded it, though (`users.alerts_overlay_live_at`, stamped
 * by markLive() from the render endpoint). Until then an untargeted alert
 * keeps rendering everywhere, as it always did: creating the overlay must
 * never take alerts off a stream before the streamer has added it to OBS.
 *
 * An alert WITH targeting keeps its targets, live overlay or not.
 *
 * Everything that decides where an alert goes calls into here: the three
 * broadcast sites (targetSlugs) and the render payload that ships alert
 * sources to an overlay (firesOn). They must agree, or an overlay receives a
 * broadcast for an alert it was never given the source of.
 */
class AlertsOverlay
{
    public const string NAME = 'Alerts';

    /**
     * The account's Alerts overlay, created if it does not exist yet.
     * Creating one resets the live stamp: a new overlay is not in OBS yet,
     * whatever the old one was.
     */
    public function ensure(User $user): OverlayTemplate
    {
        return DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = $locked->alerts_overlay_id ? OverlayTemplate::find($locked->alerts_overlay_id) : null;
            if ($existing) {
                $user->setRawAttributes($locked->getAttributes(), true);

                return $existing;
            }

            $overlay = OverlayTemplate::create([
                'owner_id' => $user->id,
                'type' => 'static',
                'name' => self::NAME,
                'description' => 'Your alerts appear here. Add it to OBS as a 1920x1080 browser source, on top of your other sources, in every scene where you want alerts.',
                'head' => '',
                'html' => '<div class="ol-alerts"></div>',
                'css' => 'html, body { margin: 0; background: transparent; }',
                'is_public' => false,
            ]);
            $overlay->template_tags = $overlay->extractTemplateTags($user->foreachCaps());
            $overlay->save();

            $locked->forceFill(['alerts_overlay_id' => $overlay->id, 'alerts_overlay_live_at' => null])->save();
            $user->setRawAttributes($locked->getAttributes(), true);
            $user->unsetRelation('alertsOverlay');

            return $overlay;
        });
    }

    /**
     * Copy controls onto the account's Alerts overlay, skipping keys it
     * already has. This is how a kit's settings for its alerts (Subtle's
     * Position) reach the overlay those alerts render in.
     *
     * @param  iterable<OverlayControl>  $controls
     */
    public function mergeControls(User $user, iterable $controls): OverlayTemplate
    {
        $overlay = $this->ensure($user);
        $taken = OverlayControl::where('overlay_template_id', $overlay->id)->pluck('key')->all();

        foreach ($controls as $control) {
            if (in_array($control->key, $taken, true)) {
                continue;
            }
            OverlayControl::create([
                'overlay_template_id' => $overlay->id,
                'user_id' => $user->id,
                'key' => $control->key,
                'label' => $control->label,
                'type' => $control->type,
                'value' => $control->value,
                'config' => $control->config,
                'sort_order' => $control->sort_order,
                'source' => null,
                'source_managed' => false,
            ]);
            $taken[] = $control->key;
        }

        return $overlay;
    }

    /**
     * Called by the render endpoint for every overlay load. Stamps the
     * moment OBS (or anything holding the token) first rendered the Alerts
     * overlay; from then on untargeted alerts render there only.
     */
    public function markLive(User $user, OverlayTemplate $rendered): void
    {
        if ($user->alerts_overlay_id !== $rendered->id || $user->alerts_overlay_live_at !== null) {
            return;
        }

        User::whereKey($user->id)->whereNull('alerts_overlay_live_at')->update(['alerts_overlay_live_at' => now()]);
        $user->alerts_overlay_live_at = now();
    }

    /**
     * Slugs an alert broadcast is addressed to, or null for "every static
     * overlay". Explicit targeting wins; otherwise the live Alerts overlay.
     *
     * @return list<string>|null
     */
    public function targetSlugs(OverlayTemplate $alert, User $user): ?array
    {
        $alert->loadMissing('targetStaticOverlays');
        if ($alert->targetStaticOverlays->isNotEmpty()) {
            return $alert->targetStaticOverlays->pluck('slug')->all();
        }

        $live = $this->liveOverlay($user);

        return $live ? [$live->slug] : null;
    }

    /**
     * Whether $alert can render in $static. Same rule as targetSlugs(), for
     * the render payload. $alert must have targetStaticOverlays loaded (ids
     * are enough).
     */
    public function firesOn(OverlayTemplate $alert, OverlayTemplate $static, User $user): bool
    {
        $targetIds = $alert->targetStaticOverlays->pluck('id')->all();
        if ($targetIds !== []) {
            return in_array($static->id, $targetIds, true);
        }

        $live = $this->liveOverlay($user);

        return $live === null || $live->id === $static->id;
    }

    /**
     * What a page shows about the account's Alerts overlay
     * (AlertsOverlayPanel.vue): the overlay, or null until it is created,
     * and whether OBS has loaded it.
     *
     * @return array{overlay: array{id: int, name: string, slug: string, type: string}|null, live: bool}
     */
    public function pageState(User $user): array
    {
        $overlay = $user->alertsOverlay;

        return [
            'overlay' => $overlay ? $overlay->only(['id', 'name', 'slug', 'type']) : null,
            'live' => $overlay !== null && $user->alerts_overlay_live_at !== null,
        ];
    }

    private function liveOverlay(User $user): ?OverlayTemplate
    {
        if ($user->alerts_overlay_id === null || $user->alerts_overlay_live_at === null) {
            return null;
        }

        return $user->alertsOverlay;
    }
}
