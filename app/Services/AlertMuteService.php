<?php

namespace App\Services;

use App\Events\ControlValueUpdated;
use App\Models\OverlayControl;
use App\Models\User;

/**
 * Global alert mute: one switch that silences every alert output (visual,
 * sound, TTS synthesis, bot chat message) at the dispatch sites.
 *
 * State lives in a single service-managed boolean control (source `alerts`,
 * key `muted`) - the same control templates read via [[[if:c:alerts:muted]]] -
 * so the guard, the toggle button, and the overlay banner all share one source
 * of truth. Absent control = not muted. The control is user-scoped
 * (overlay_template_id NULL) and source_managed, so the toggle endpoints are
 * its only writers; the sibling of AlertExpressionRenderer's `tts` gate.
 */
class AlertMuteService
{
    public const string SOURCE = 'alerts';

    public const string KEY = 'muted';

    /** Preset defs for the "add preset control" flow, mirrors StreamSessionService::CONTROL_PRESETS. */
    public const array CONTROL_PRESETS = [
        ['key' => self::KEY, 'type' => 'boolean', 'label' => 'Alerts Muted', 'value' => '0'],
    ];

    public function isMuted(User $user): bool
    {
        return OverlayControl::where('user_id', $user->id)
            ->where('source', self::SOURCE)
            ->where('key', self::KEY)
            ->where('source_managed', true)
            ->where('value', '1')
            ->exists();
    }

    /**
     * Set the mute state, provisioning the control on first use, and
     * broadcast the change so overlays and feed pages update live.
     */
    public function setMuted(User $user, bool $muted): bool
    {
        $control = OverlayControl::provisionServiceControl($user, self::SOURCE, self::CONTROL_PRESETS[0]);

        $newValue = $muted ? '1' : '0';

        if ($control->value !== $newValue) {
            $control->update(['value' => $newValue]);

            ControlValueUpdated::dispatch(
                '',
                $control->broadcastKey(),
                $control->type,
                $newValue,
                $user->twitch_id,
            );
        }

        return $muted;
    }
}
