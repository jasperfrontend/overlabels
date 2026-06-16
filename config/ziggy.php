<?php

// Routes the frontend should never need, expressed as negation patterns so they
// can be folded into every role group. NOTE: passing a group to @routes makes
// Ziggy ignore the top-level `except` key entirely (see Ziggy::applyFilters()),
// so these negations are how those routes stay out of the payload.
$hidden = [
    '!sanctum.*',
    '!telescope',
    '!storage.*',
    '!webhooks.*',
    '!api.*',
    '!overlay.*',
    '!auth.*',
    '!controls.*',
    '!eventsub.*',
    '!external-events.*',
    '!login',
    '!privacy',
    '!terms',
    '!template.export',
    '!tokens.revoke',
    '!tokens.destroy',
    '!events.replay',
    '!tags.generate',
    '!tags.preview',
    '!tags.clear',
    '!tags.cleanup',
    '!tags.api.jobs',
    '!twitchdata.refresh.*',
    '!kits.store',
    '!kits.show',
    '!kits.edit',
    '!kits.update',
    '!kits.destroy',
    '!kits.fork',
    '!settings.integrations.kofi.*',
];

return [
    'groups' => [
        // Logged-out visitors: only the handful of genuinely public routes.
        'guest' => ['login', 'home', 'help.*', 'privacy', 'terms'],
        // Authenticated non-admins: everything except admin routes and the hidden set.
        'user' => array_merge(['*', '!admin.*'], $hidden),
        // Admins: everything except the hidden set (admin routes kept).
        'admin' => array_merge(['*'], $hidden),
    ],
];
