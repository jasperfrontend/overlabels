<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Messages\AlertMessageRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

function alertCondUser(): User
{
    return User::factory()->create(['twitch_id' => (string) fake()->unique()->randomNumber(9)]);
}

function alertCondTemplate(User $user): OverlayTemplate
{
    return OverlayTemplate::factory()->create([
        'owner_id' => $user->id,
        'fork_of_id' => null,
        'type' => 'alert',
        'slug' => 'alert-'.fake()->unique()->lexify('????????'),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// The one that started it: "following for 1 months"
// ──────────────────────────────────────────────────────────────────────────────

it('pluralises a resub month count in TTS, so it never says "1 months"', function () {
    $user = alertCondUser();
    $renderer = app(AlertMessageRenderer::class);
    $message = '[[[event.user_name]]] resubscribed for [[[event.streak_months]]] month[[[if:event.streak_months != 1]]]s[[[endif]]]. Thank you!';

    expect($renderer->render($user, $message, ['event.user_name' => 'jasper', 'event.streak_months' => 1]))
        ->toBe('jasper resubscribed for 1 month. Thank you!');

    expect($renderer->render($user, $message, ['event.user_name' => 'jasper', 'event.streak_months' => 12]))
        ->toBe('jasper resubscribed for 12 months. Thank you!');
});

it('applies the same conditionals to the bot chat message', function () {
    $user = alertCondUser();
    $message = '[[[event.from_name]]] tipped [[[if:event.amount >= 10]]]big[[[else]]]a little[[[endif]]]!';

    expect(app(AlertMessageRenderer::class)->renderMessage($user, $message, ['event.from_name' => 'sam', 'event.amount' => '25']))
        ->toBe('sam tipped big!');
    expect(app(AlertMessageRenderer::class)->renderMessage($user, $message, ['event.from_name' => 'sam', 'event.amount' => '3']))
        ->toBe('sam tipped a little!');
});

it('reads controls inside an alert condition', function () {
    $user = alertCondUser();
    OverlayControl::create([
        'user_id' => $user->id,
        'overlay_template_id' => null,
        'key' => 'hype',
        'label' => 'hype',
        'type' => 'boolean',
        'value' => '1',
        'sort_order' => 0,
    ]);

    expect(app(AlertMessageRenderer::class)->render($user, '[[[if:c:hype]]]HYPE [[[endif]]][[[event.user_name]]] followed', ['event.user_name' => 'kim']))
        ->toBe('HYPE kim followed');
});

it('returns null when every branch renders empty', function () {
    $user = alertCondUser();

    expect(app(AlertMessageRenderer::class)->render($user, '[[[if:event.amount > 100]]]whale![[[endif]]]', ['event.amount' => '5']))
        ->toBeNull();
});

it('never rescans a payload value that looks like a block token', function () {
    $user = alertCondUser();
    $payload = ['event.message' => '[[[if:c:x]]]leak[[[endif]]]', 'c:x' => '1'];

    expect(app(AlertMessageRenderer::class)->render($user, 'said: [[[event.message]]]', $payload))
        ->toBe('said: [[[if:c:x]]]leak[[[endif]]]');
});

// ──────────────────────────────────────────────────────────────────────────────
// Save gate on the template editor
// ──────────────────────────────────────────────────────────────────────────────

it('refuses to save an alert whose TTS message has an if with no endif', function () {
    $user = alertCondUser();
    $alert = alertCondTemplate($user);

    $this->actingAs($user)
        ->put("/templates/{$alert->id}", [
            'name' => $alert->name,
            'type' => 'alert',
            'tts_message' => '[[[event.user_name]]] [[[if:event.streak_months > 1]]]is back',
        ])
        ->assertSessionHasErrors(['tts_message']);

    expect(session('errors')->first('tts_message'))->toContain('has no [[[endif]]]');
});

it('refuses foreach in a chat message with a reason', function () {
    $user = alertCondUser();
    $alert = alertCondTemplate($user);

    $this->actingAs($user)
        ->put("/templates/{$alert->id}", [
            'name' => $alert->name,
            'type' => 'alert',
            'chat_message' => '[[[foreach:subscribers as s]]][[[s.user_name]]][[[endforeach]]]',
        ])
        ->assertSessionHasErrors(['chat_message']);

    expect(session('errors')->first('chat_message'))->toContain("loops don't work here");
});

it('saves a well-formed conditional in both messages', function () {
    $user = alertCondUser();
    $alert = alertCondTemplate($user);
    $tts = '[[[event.user_name]]] for [[[event.streak_months]]] month[[[if:event.streak_months != 1]]]s[[[endif]]]';
    $chat = '[[[if:event.amount >= 10]]]big tip[[[else]]]tip[[[endif]]] from [[[event.from_name]]]';

    $this->actingAs($user)
        ->put("/templates/{$alert->id}", [
            'name' => $alert->name,
            'type' => 'alert',
            'tts_message' => $tts,
            'chat_message' => $chat,
        ])
        ->assertSessionDoesntHaveErrors(['tts_message', 'chat_message']);

    expect($alert->fresh())
        ->tts_message->toBe($tts)
        ->chat_message->toBe($chat);
});
