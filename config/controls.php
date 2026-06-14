<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Change detection
    |--------------------------------------------------------------------------
    |
    | Service control updates (GPS pings, donations) only broadcast when the
    | value actually moved. For noisy floats - a stationary scooter's GPS still
    | jitters in the 6th decimal - we compare against an epsilon: a change <=
    | epsilon is dropped from BOTH the broadcast and persistence, so the stored
    | value stays at the last broadcast value (no drift creep) and a parked
    | device emits nothing.
    |
    | Keyed by control key. 1e-5 degrees is ~1.1m of latitude. Keys not listed
    | fall back to `default` when numeric (number/counter), or to an exact
    | string comparison for text/boolean controls. Tune here without a deploy.
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
