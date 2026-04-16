<?php

use App\Models\OverlayTemplate;

test('extractTemplateTags extracts plain tags', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[followers_total]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('followers_total');
});

test('extractTemplateTags extracts tag name and strips pipe formatter', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:round_timer|duration]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('c:round_timer');
    expect($tags)->not->toContain('c:round_timer|duration');
});

test('extractTemplateTags strips pipe with format args', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:timer|duration:hh:mm:ss]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('c:timer');
});

test('extractTemplateTags handles date format with hyphens', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:event_date|date:dd-MM-yyyy]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('c:event_date');
});

test('extractTemplateTags deduplicates tags with different formatters', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:score]]]</span><span>[[[c:score|round]]]</span><span>[[[c:score|number:2]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect(array_count_values($tags)['c:score'])->toBe(1);
});

test('extractTemplateTags handles pipe args with spaces', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:datetime_thing|date:dd-MM-yyyy HH:mm]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('c:datetime_thing');
});

test('extractTemplateTags handles hyphenated service names', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:overlabels-mobile:gps_speed]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('c:overlabels-mobile:gps_speed');
});

test('extractTemplateTags handles hyphenated service names with pipe', function () {
    $template = new OverlayTemplate;
    $template->html = '<span>[[[c:overlabels-mobile:gps_speed|round]]]</span>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)->toContain('c:overlabels-mobile:gps_speed');
});

test('extractTemplateTags handles mixed plain and piped tags', function () {
    $template = new OverlayTemplate;
    $template->html = '<div>[[[followers_total]]] [[[c:amount|currency:EUR]]] [[[channel_title]]]</div>';
    $template->css = '.bar { width: [[[c:timer|duration:mm:ss]]]; }';

    $tags = $template->extractTemplateTags();

    expect($tags)
        ->toContain('followers_total')
        ->toContain('c:amount')
        ->toContain('channel_title')
        ->toContain('c:timer');
});
