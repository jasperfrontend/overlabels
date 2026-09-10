<?php

namespace App\Support;

use App\Models\OverlayControl;
use App\Models\User;

/**
 * One user's controls, resolved ONCE.
 *
 * A random-mode control has no stored value: every resolveDisplayValue()
 * is a fresh roll. Anything that resolves more than one template for the
 * same query - a list appender's value and its success reply, an alert's
 * TTS line, chat line and overlay payload - must read from a single
 * snapshot, or the chatter is told one number and the list, the speaker
 * or the screen holds another. Build one per fire and pass it around;
 * never build one per template.
 *
 * `values` is the map both resolvers look `c:` tags up in: identifier =>
 * display value, where a service-managed control is keyed by its
 * broadcastKey ("kofi:donations_received") and an own control by its key.
 *
 * `rolls` is the subset that only exists because of the roll: the
 * random-mode controls, keyed as the overlay reads them (`c:<identifier>`).
 * The overlay ticks random controls client-side, so an alert can only
 * agree with the server if its broadcast payload carries this map, which
 * the alert merge prefers over the live data.
 */
final readonly class ControlSnapshot
{
    /**
     * @param  array<string,string>  $values
     * @param  array<string,string>  $rolls
     */
    private function __construct(
        public array $values,
        public array $rolls,
    ) {}

    public static function for(User $user): self
    {
        $values = [];
        $rolls = [];

        foreach (OverlayControl::where('user_id', $user->id)->get() as $control) {
            $identifier = $control->tagIdentifier();
            $value = $control->resolveDisplayValue();
            $values[$identifier] = $value;

            if ($control->isRandom()) {
                $rolls['c:'.$identifier] = $value;
            }
        }

        return new self($values, $rolls);
    }
}
