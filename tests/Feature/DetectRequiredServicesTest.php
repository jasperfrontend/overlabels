<?php

use App\Models\OverlayTemplate;

/**
 * detectRequiredServices feeds the copy wizard's "you need to connect these
 * services" warning. It used to run a bespoke service-shaped regex that had
 * drifted from the canonical tag pattern in two ways; it now runs the shared
 * pattern and inspects the resulting keys. See D3/D4 in
 * docs/design/overlabels-dsl-spec.md.
 */
function templateWith(string $html): OverlayTemplate
{
    return new OverlayTemplate(['html' => $html, 'css' => '', 'head' => '']);
}

it('detects a plain service control tag', function () {
    expect(templateWith('[[[c:kofi:donations_received]]]')->detectRequiredServices())
        ->toBe(['kofi']);
});

it('D3: detects a service control tag carrying a ?? default', function () {
    // The old pattern had no `??` branch, so a defaulted control tag reported no
    // dependency at all and the connect-this-service warning never fired.
    expect(templateWith('[[[c:kofi:total ?? 0]]]')->detectRequiredServices())
        ->toBe(['kofi']);
});

it('D3: detects a service control tag with both a pipe and a default', function () {
    expect(templateWith('[[[c:streamlabs:total|currency:EUR ?? nothing yet]]]')->detectRequiredServices())
        ->toBe(['streamlabs']);
});

it('D4: detects a service tag whose key has more than two segments', function () {
    expect(templateWith('[[[c:fourthwall:latest:donor_name]]]')->detectRequiredServices())
        ->toBe(['fourthwall']);
});

it('does not report list tags as an integration', function () {
    // `c:list:<slug>` is a namespace, not a driver. The old two-segment pattern
    // would happily have reported "list" as a required service.
    expect(templateWith('[[[c:list:raffle:count]]]')->detectRequiredServices())
        ->toBe([]);
});

it('does not report a user-scoped control as an integration', function () {
    expect(templateWith('[[[c:my_counter]]]')->detectRequiredServices())
        ->toBe([]);
});

it('does not report an unknown namespace as an integration', function () {
    expect(templateWith('[[[c:not_a_service:key]]]')->detectRequiredServices())
        ->toBe([]);
});

it('deduplicates and finds services across html, css and head', function () {
    $template = new OverlayTemplate([
        'html' => '[[[c:kofi:total]]] [[[c:kofi:latest_donor]]]',
        'css' => 'div::after { content: "[[[c:streamlabs:total ?? 0]]]" }',
        'head' => '<meta name="x" content="[[[c:fourthwall:orders]]]">',
    ]);

    expect($template->detectRequiredServices())
        ->toEqualCanonicalizing(['kofi', 'streamlabs', 'fourthwall']);
});

it('reports nothing for a template with no control tags', function () {
    expect(templateWith('<div>[[[followers_total]]]</div>')->detectRequiredServices())
        ->toBe([]);
});
