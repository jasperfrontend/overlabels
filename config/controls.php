<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Change detection
    |--------------------------------------------------------------------------
    |
    | Noise suppression for service control updates, and nothing else. For
    | noisy floats - a stationary scooter's GPS still jitters in the 6th
    | decimal - a change <= epsilon is dropped from BOTH the broadcast and
    | persistence, so the stored value stays put and drift can't accumulate.
    | (The phone stops transmitting altogether when it detects no movement, so
    | this is the second filter, not the only one.)
    |
    | Keyed by control key. 1e-5 degrees is ~1.1m of latitude. Keys not listed
    | fall back to `default` when numeric (number/counter).
    |
    | A threshold of 0.0 means NO suppression: an identical value still writes
    | and still broadcasts. That is deliberate - `_at` means "when did this
    | control last receive a write", so a repeat donor has to move it. Only a
    | positive number here drops anything. Tune without a deploy.
    |
    */

    'change_detection' => [
        'epsilon' => [
            'lat' => 1e-5,
            'lng' => 1e-5,
            'speed' => 0.5,
            'bearing' => 1.0,
            'distance' => 0.0,
            'default' => 0.0,
        ],
    ],

];
