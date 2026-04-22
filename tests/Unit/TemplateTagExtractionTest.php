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

test('extractTemplateTags expands foreach iterables to concrete indexed keys', function () {
    $template = new OverlayTemplate;
    $template->html = '<ul>[[[foreach:event.choices as choice]]]<li>[[[choice.title]]]</li>[[[endforeach]]]</ul>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    // `choices` has a cap of 5 in the data mapper
    expect($tags)
        ->toContain('event.choices.count')
        ->toContain('event.choices.0.title')
        ->toContain('event.choices.1.title')
        ->toContain('event.choices.2.title')
        ->toContain('event.choices.3.title')
        ->toContain('event.choices.4.title');
});

test('extractTemplateTags drops scope-local alias and loop tokens', function () {
    $template = new OverlayTemplate;
    $template->html = '<ul>[[[foreach:event.choices as choice]]]<li>[[[loop.index]]]. [[[choice.title]]]</li>[[[endforeach]]]</ul>';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)
        ->not->toContain('choice.title')
        ->not->toContain('loop.index')
        ->not->toContain('loop');
});

test('extractTemplateTags captures multiple foreach sub-keys in body', function () {
    $template = new OverlayTemplate;
    $template->html = '[[[foreach:event.outcomes as outcome]]]<span>[[[outcome.title]]] [[[outcome.color]]]</span>[[[endforeach]]]';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)
        ->toContain('event.outcomes.count')
        ->toContain('event.outcomes.0.title')
        ->toContain('event.outcomes.9.title')
        ->toContain('event.outcomes.0.color')
        ->toContain('event.outcomes.9.color');
});

test('extractTemplateTags preserves non-scoped tokens inside foreach body', function () {
    $template = new OverlayTemplate;
    $template->html = '[[[foreach:event.choices as choice]]]<li>[[[choice.title]]] ([[[event.title]]])</li>[[[endforeach]]]';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)
        ->toContain('event.title')
        ->toContain('event.choices.0.title');
});

test('extractTemplateTags expands alias references in conditional branches inside foreach', function () {
    $template = new OverlayTemplate;
    $template->html = '[[[foreach:event.choices as choice]]][[[if:choice.votes > 0]]][[[choice.title]]][[[endif]]][[[endforeach]]]';
    $template->css = '';

    $tags = $template->extractTemplateTags();

    expect($tags)
        ->toContain('event.choices.0.votes')
        ->toContain('event.choices.0.title')
        ->toContain('event.choices.4.votes');
});

test('extractTemplateTags expands user-scope subscribers foreach using provided caps', function () {
    $template = new OverlayTemplate;
    $template->html = '[[[foreach:subscribers as s]]]<li>[[[s.user_name]]]</li>[[[endforeach]]]';
    $template->css = '';

    $tags = $template->extractTemplateTags(['subscribers' => 3, 'goals' => 3, 'followers' => 5, 'followed' => 5]);

    expect($tags)
        ->toContain('subscribers.count')
        ->toContain('subscribers.0.user_name')
        ->toContain('subscribers.1.user_name')
        ->toContain('subscribers.2.user_name')
        ->not->toContain('subscribers.3.user_name');
});

test('extractTemplateTags expands channel_followers foreach using provided caps', function () {
    $template = new OverlayTemplate;
    $template->html = '[[[foreach:channel_followers as f]]]<li>[[[f.user_name]]]</li>[[[endforeach]]]';
    $template->css = '';

    $tags = $template->extractTemplateTags(['subscribers' => 10, 'goals' => 3, 'followers' => 4, 'followed' => 5]);

    expect($tags)
        ->toContain('channel_followers.count')
        ->toContain('channel_followers.0.user_name')
        ->toContain('channel_followers.3.user_name')
        ->not->toContain('channel_followers.4.user_name');
});

test('extractTemplateTags event caps are fixed regardless of user caps', function () {
    $template = new OverlayTemplate;
    $template->html = '[[[foreach:event.choices as c]]][[[c.title]]][[[endforeach]]]';
    $template->css = '';

    // Even with a huge user cap, event.choices stays at Twitch's 5-choice limit.
    $tags = $template->extractTemplateTags(['subscribers' => 50, 'goals' => 50, 'followers' => 50, 'followed' => 50]);

    expect($tags)
        ->toContain('event.choices.4.title')
        ->not->toContain('event.choices.5.title');
});
