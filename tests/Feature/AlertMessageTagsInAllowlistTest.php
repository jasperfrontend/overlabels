<?php

use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Messages\AlertMessageRenderer;
use App\Services\TemplateDataMapperService;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function allowlistAlert(array $attributes): OverlayTemplate
{
    $user = User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);

    $alert = OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
        ...$attributes,
    ]);
    $alert->template_tags = $alert->extractTemplateTags([]);
    $alert->save();

    return $alert;
}

function redemptionEvent(int $cost): array
{
    return [
        'subscription' => ['type' => 'channel.channel_points_custom_reward_redemption.add'],
        'event' => [
            'user_name' => 'JasperDiscovers',
            'reward' => ['id' => 'r1', 'title' => 'one for test', 'cost' => $cost, 'prompt' => ''],
        ],
    ];
}

// The bug as reported: the alert HTML shows the reward title, the TTS line
// also speaks the cost, and the cost came out empty because the allowlist
// was extracted from the HTML alone.

it('keeps a tag that only the TTS message uses in the fire-time payload', function () {
    $alert = allowlistAlert([
        'html' => '<div>[[[event.user_name]]] redeemed [[[event.reward.title]]]</div>',
        'tts_message' => '[[[event.user_name]]] redeemed [[[event.reward.title]]] for [[[event.reward.cost]]] point[[[if:event.reward.cost != 1]]]s[[[endif]]]',
    ]);

    expect($alert->template_tags)->toContain('event.reward.cost');

    $mapper = app(TemplateDataMapperService::class);
    $renderer = app(AlertMessageRenderer::class);

    $one = $mapper->mapForTemplate([], $alert->name, $alert->template_tags, redemptionEvent(1));
    expect($renderer->render($alert->owner, $alert->tts_message, $one))
        ->toBe('JasperDiscovers redeemed one for test for 1 point');

    $two = $mapper->mapForTemplate([], $alert->name, $alert->template_tags, redemptionEvent(2));
    expect($renderer->render($alert->owner, $alert->tts_message, $two))
        ->toBe('JasperDiscovers redeemed one for test for 2 points');
});

it('keeps a tag that only the chat message uses', function () {
    $alert = allowlistAlert([
        'html' => '<div>[[[event.user_name]]]</div>',
        'chat_message' => '[[[event.user_name]]] redeemed [[[event.reward.title]]]',
    ]);

    expect($alert->template_tags)->toContain('event.reward.title');
});

it('keeps a tag that only a message CONDITION reads', function () {
    // The condition key never appears as a bare tag anywhere, so this is the
    // case the conditional extraction has to cover on its own.
    $alert = allowlistAlert([
        'html' => '<div>[[[event.user_name]]]</div>',
        'tts_message' => '[[[event.user_name]]] [[[if:event.reward.cost > 1]]]big spender[[[endif]]]',
    ]);

    expect($alert->template_tags)->toContain('event.reward.cost');
});

it('still leaves out a tag nothing references', function () {
    $alert = allowlistAlert([
        'html' => '<div>[[[event.user_name]]]</div>',
        'tts_message' => '[[[event.user_name]]] redeemed something',
    ]);

    $payload = app(TemplateDataMapperService::class)->mapForTemplate([], $alert->name, $alert->template_tags, redemptionEvent(1));

    expect($payload)->not->toHaveKey('event.reward.cost');
});
