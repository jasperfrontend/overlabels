<?php

use App\Jobs\SyncLivingTitle;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\LivingTitleService;
use App\Services\TwitchScopeService;
use App\Services\TwitchTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * The living Twitch title: a one-line tag template rendered against the
 * account's live values and PATCHed to Twitch when the text changes. Three
 * rules from the handoff are pinned here - channel_title is refused inside
 * the template, a title we did not write pauses the feature, and a burst of
 * events becomes one debounced write - plus the category picker being a
 * one-shot write the render never re-asserts.
 */
beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create([
        'twitch_id' => '73327367',
        'access_token' => 'fake-twitch-token',
        'refresh_token' => 'fake-refresh',
        'token_expires_at' => now()->addHour(),
        'twitch_scopes' => TwitchScopeService::REQUIRED_SCOPES,
        'twitch_data' => ['login' => 'streamer'],
    ]);

    $this->mock(TwitchTokenService::class, function ($mock) {
        $mock->shouldReceive('ensureValidToken')->andReturnTrue();
    });
});

/**
 * Fake Helix: every PATCH to /channels is recorded in $patches, and the
 * reads the renderer makes come back with a follower total of 1234 and a
 * channel titled "Old title" in Just Chatting.
 *
 * @param  array<int, array<string,mixed>>  $patches
 */
function fakeHelix(array &$patches, int $patchStatus = 204): void
{
    Http::fake(function (Request $request) use (&$patches, $patchStatus) {
        $url = $request->url();

        if ($request->method() === 'PATCH' && str_contains($url, 'helix/channels')) {
            $patches[] = $request->data();

            return Http::response('', $patchStatus);
        }

        if (str_contains($url, 'helix/channels/followers')) {
            return Http::response(['total' => 1234, 'data' => []]);
        }

        if (str_contains($url, 'helix/channels?')) {
            return Http::response(['data' => [[
                'broadcaster_id' => '73327367',
                'title' => 'Old title',
                'game_id' => '509658',
                'game_name' => 'Just Chatting',
            ]]]);
        }

        if (str_contains($url, 'helix/search/categories')) {
            return Http::response(['data' => [
                ['id' => '27471', 'name' => 'Minecraft', 'box_art_url' => 'https://cdn.example/{width}x{height}.jpg'],
            ]]);
        }

        return Http::response(['data' => []]);
    });
}

function livingTitleOn(User $user, string $template, array $extra = []): void
{
    $user->setPreference('living_title.enabled', true);
    $user->setPreference('living_title.template', $template);
    foreach ($extra as $key => $value) {
        $user->setPreference("living_title.{$key}", $value);
    }
    $user->save();
}

function postTwitchEventForTitle(User $user, string $type, array $event): TestResponse
{
    config(['app.twitch_webhook_secret' => 'test-webhook-secret']);

    $body = json_encode([
        'subscription' => ['id' => 'sub-'.$type, 'type' => $type, 'version' => '1'],
        'event' => array_merge([
            'broadcaster_user_id' => $user->twitch_id,
            'broadcaster_user_login' => 'streamer',
            'broadcaster_user_name' => 'Streamer',
        ], $event),
    ]);
    $messageId = 'msg-'.uniqid();
    $timestamp = now()->toIso8601String();
    $signature = 'sha256='.hash_hmac('sha256', $messageId.$timestamp.$body, 'test-webhook-secret');

    return test()->call('POST', '/api/twitch/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TYPE' => 'notification',
        'HTTP_TWITCH_EVENTSUB_MESSAGE_ID' => $messageId,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_TIMESTAMP' => $timestamp,
        'HTTP_TWITCH_EVENTSUB_MESSAGE_SIGNATURE' => $signature,
    ], $body);
}

// ──────────────────────────────────────────────────────────────────────────────
// Scope
// ──────────────────────────────────────────────────────────────────────────────

test('the login asks for the one write scope the title needs', function () {
    expect(LivingTitleService::SCOPE)->toBe('channel:manage:broadcast');
    expect(TwitchScopeService::REQUIRED_SCOPES)->toContain(LivingTitleService::SCOPE);
});

// ──────────────────────────────────────────────────────────────────────────────
// Save gate
// ──────────────────────────────────────────────────────────────────────────────

test('channel_title is refused inside the template, in a tag and in a condition', function (string $template) {
    $this->actingAs($this->user)
        ->from('/settings/title')
        ->patch('/settings/title', ['enabled' => true, 'template' => $template])
        ->assertRedirect('/settings/title')
        ->assertSessionHasErrors('template');

    expect(session('errors')->first('template'))->toContain('feed on itself');
    expect($this->user->fresh()->livingTitle()['enabled'])->toBeFalse();
})->with([
    'tag' => 'Now: [[[channel_title]]]',
    'condition' => '[[[if:channel_title == x]]]y[[[endif]]]',
]);

test('a template that renders differently every time is refused', function (string $template) {
    $problem = app(LivingTitleService::class)->problem($template);

    expect($problem)->toContain('different value every time');
})->with([
    'rand' => 'Lucky number [[[rand:1-10]]]',
    'list random' => '[[[c:list:games:random]]]',
]);

test('foreach and an unclosed if are refused in the shared save-gate voice', function () {
    $service = app(LivingTitleService::class);

    expect($service->problem('[[[foreach:subscribers as s]]]x[[[endforeach]]]'))->toContain('A message is one line');
    expect($service->problem('[[[if:c:wins > 3]]]hot'))->toContain('has no [[[endif]]]');
    expect($service->problem('Road to 2K | [[[followers_total]]] followers'))->toBeNull();
});

test('saving an enabled template dispatches a render at once and clears a pause', function () {
    Queue::fake();
    livingTitleOn($this->user, 'old', ['paused' => true, 'paused_title' => 'Someone typed this']);

    $this->actingAs($this->user)
        ->patch('/settings/title', ['enabled' => true, 'template' => '[[[followers_total]]] followers'])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = $this->user->fresh()->livingTitle();
    expect($settings['template'])->toBe('[[[followers_total]]] followers');
    expect($settings['enabled'])->toBeTrue();
    expect($settings['paused'])->toBeFalse();
    expect($settings['paused_title'])->toBeNull();

    Queue::assertPushed(SyncLivingTitle::class, fn (SyncLivingTitle $job) => $job->userId === $this->user->id);
});

test('switching off forgets what was written, so the next switch-on always writes', function () {
    Queue::fake();
    livingTitleOn($this->user, 'x', ['last_written' => 'x']);

    $this->actingAs($this->user)->patch('/settings/title', ['enabled' => false, 'template' => 'x'])->assertRedirect();

    expect($this->user->fresh()->livingTitle()['last_written'])->toBeNull();
    Queue::assertNothingPushed();
});

test('an empty template cannot be enabled', function () {
    Queue::fake();

    $this->actingAs($this->user)->patch('/settings/title', ['enabled' => true, 'template' => '   '])->assertRedirect();

    expect($this->user->fresh()->livingTitle()['enabled'])->toBeFalse();
    Queue::assertNothingPushed();
});

// ──────────────────────────────────────────────────────────────────────────────
// Sync: render, compare, write
// ──────────────────────────────────────────────────────────────────────────────

test('sync renders the template against live values and writes it to Twitch', function () {
    $patches = [];
    fakeHelix($patches);
    livingTitleOn($this->user, 'Road to 2K | [[[followers_total]]] followers | playing [[[channel_game]]]');

    app(LivingTitleService::class)->sync($this->user);

    expect($patches)->toBe([['title' => 'Road to 2K | 1234 followers | playing Just Chatting']]);

    $settings = $this->user->fresh()->livingTitle();
    expect($settings['last_written'])->toBe('Road to 2K | 1234 followers | playing Just Chatting');
    expect($settings['written_at'])->toBeInt();
    expect($settings['last_error'])->toBeNull();
});

test('sync does not write when the rendered title equals the last one written', function () {
    $patches = [];
    fakeHelix($patches);
    livingTitleOn($this->user, '[[[followers_total]]] followers', ['last_written' => '1234 followers']);

    app(LivingTitleService::class)->sync($this->user);

    expect($patches)->toBe([]);
});

test('sync writes nothing when the feature is off, paused, or renders empty', function (array $extra, string $template) {
    $patches = [];
    fakeHelix($patches);
    livingTitleOn($this->user, $template, $extra);

    app(LivingTitleService::class)->sync($this->user);

    expect($patches)->toBe([]);
})->with([
    'off' => [['enabled' => false], '[[[followers_total]]]'],
    'paused' => [['paused' => true], '[[[followers_total]]]'],
    'renders empty' => [[], '[[[c:does_not_exist]]]'],
]);

test('sync cuts the rendered title at 140 characters', function () {
    $patches = [];
    fakeHelix($patches);
    livingTitleOn($this->user, str_repeat('followers ', 20).'[[[followers_total]]]');

    app(LivingTitleService::class)->sync($this->user);

    expect($patches)->toHaveCount(1);
    expect(mb_strlen($patches[0]['title']))->toBe(140);
});

test('sync without the write scope records why and sends nothing', function () {
    $patches = [];
    fakeHelix($patches);
    $this->user->update(['twitch_scopes' => array_values(array_diff(TwitchScopeService::REQUIRED_SCOPES, [LivingTitleService::SCOPE]))]);
    livingTitleOn($this->user, '[[[followers_total]]]');

    app(LivingTitleService::class)->sync($this->user->fresh());

    expect($patches)->toBe([]);
    expect($this->user->fresh()->livingTitle()['last_error'])->toContain('Reauthorize');
});

test('a rejected write puts the previous title back as last_written', function () {
    $patches = [];
    fakeHelix($patches, patchStatus: 400);
    livingTitleOn($this->user, '[[[followers_total]]] followers', ['last_written' => 'Before']);

    app(LivingTitleService::class)->sync($this->user);

    expect($patches)->toHaveCount(1);
    $settings = $this->user->fresh()->livingTitle();
    expect($settings['last_written'])->toBe('Before');
    expect($settings['last_error'])->toContain('did not accept');
});

// ──────────────────────────────────────────────────────────────────────────────
// Pause: a title we did not write
// ──────────────────────────────────────────────────────────────────────────────

test('a channel.update carrying a foreign title pauses the feature and dispatches no render', function () {
    Queue::fake();
    Http::fake();
    livingTitleOn($this->user, '[[[followers_total]]]', ['last_written' => '1234']);

    postTwitchEventForTitle($this->user, 'channel.update', ['title' => 'I typed this in the dashboard', 'category_id' => '1', 'category_name' => 'x', 'language' => 'en'])
        ->assertOk();

    $settings = $this->user->fresh()->livingTitle();
    expect($settings['paused'])->toBeTrue();
    expect($settings['paused_title'])->toBe('I typed this in the dashboard');

    Queue::assertNotPushed(SyncLivingTitle::class);
});

test('a channel.update echoing our own title does not pause', function () {
    Http::fake();
    livingTitleOn($this->user, '[[[followers_total]]]', ['last_written' => '1234 followers']);

    postTwitchEventForTitle($this->user, 'channel.update', ['title' => '  1234   followers ', 'category_id' => '1', 'category_name' => 'x', 'language' => 'en'])
        ->assertOk();

    expect($this->user->fresh()->livingTitle()['paused'])->toBeFalse();
});

test('a channel.update before anything was written, or while off, does not pause', function (array $extra) {
    Http::fake();
    livingTitleOn($this->user, '[[[followers_total]]]', $extra);

    postTwitchEventForTitle($this->user, 'channel.update', ['title' => 'Whatever', 'category_id' => '1', 'category_name' => 'x', 'language' => 'en'])
        ->assertOk();

    expect($this->user->fresh()->livingTitle()['paused'])->toBeFalse();
})->with([
    'nothing written yet' => [['last_written' => null]],
    'switched off' => [['enabled' => false, 'last_written' => 'Old']],
]);

test('resume clears the pause and renders at once', function () {
    Queue::fake();
    livingTitleOn($this->user, '[[[followers_total]]]', ['paused' => true, 'paused_title' => 'Foreign', 'last_written' => 'Old']);

    $this->actingAs($this->user)->post('/settings/title/resume')->assertRedirect()->assertSessionHas('success');

    $settings = $this->user->fresh()->livingTitle();
    expect($settings['paused'])->toBeFalse();
    expect($settings['paused_title'])->toBeNull();
    Queue::assertPushed(SyncLivingTitle::class, 1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Triggers and debounce
// ──────────────────────────────────────────────────────────────────────────────

test('a stored event queues one render, and a burst of events still queues one', function () {
    Queue::fake();
    Http::fake();
    livingTitleOn($this->user, '[[[followers_total]]]');

    foreach (['a', 'b', 'c'] as $id) {
        postTwitchEventForTitle($this->user, 'channel.follow', [
            'user_id' => '9'.$id, 'user_login' => 'f'.$id, 'user_name' => 'F'.$id, 'followed_at' => now()->toIso8601String(),
        ])->assertOk();
    }

    Queue::assertPushed(SyncLivingTitle::class, 1);
});

test('the debounce window closes when the job runs, so a later event opens a new one', function () {
    Queue::fake();
    livingTitleOn($this->user, '[[[followers_total]]]');
    $service = app(LivingTitleService::class);

    $service->schedule($this->user);
    $service->schedule($this->user);
    Queue::assertPushed(SyncLivingTitle::class, 1);

    // The job's first act, before rendering anything.
    Cache::forget(LivingTitleService::pendingKey($this->user->id));

    $service->schedule($this->user);
    Queue::assertPushed(SyncLivingTitle::class, 2);
});

test('no render is queued for an account with the title switched off', function () {
    Queue::fake();
    Http::fake();

    postTwitchEventForTitle($this->user, 'channel.follow', [
        'user_id' => '91', 'user_login' => 'fa', 'user_name' => 'Fa', 'followed_at' => now()->toIso8601String(),
    ])->assertOk();

    Queue::assertNotPushed(SyncLivingTitle::class);
});

test('a control write queues a render', function () {
    Queue::fake();
    livingTitleOn($this->user, 'Wins: [[[c:wins]]]');

    $control = OverlayControl::create([
        'user_id' => $this->user->id,
        'overlay_template_id' => null,
        'key' => 'wins',
        'label' => 'Wins',
        'type' => 'counter',
        'value' => '0',
    ]);
    Queue::assertPushed(SyncLivingTitle::class, 1);

    Cache::forget(LivingTitleService::pendingKey($this->user->id));
    $control->writeValue('1');

    Queue::assertPushed(SyncLivingTitle::class, 2);
});

test('a control write on another account queues nothing here', function () {
    Queue::fake();
    livingTitleOn($this->user, 'Wins: [[[c:wins]]]');
    $other = User::factory()->create();

    OverlayControl::create([
        'user_id' => $other->id,
        'overlay_template_id' => null,
        'key' => 'wins',
        'label' => 'Wins',
        'type' => 'counter',
        'value' => '0',
    ]);

    Queue::assertNotPushed(SyncLivingTitle::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Settings page, preview and category
// ──────────────────────────────────────────────────────────────────────────────

test('the settings page carries the settings, the scope state and the channel as it is on Twitch', function () {
    $patches = [];
    fakeHelix($patches);
    livingTitleOn($this->user, '[[[followers_total]]] followers');

    $this->actingAs($this->user)
        ->get('/settings/title')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Title')
            ->where('livingTitle.enabled', true)
            ->where('livingTitle.template', '[[[followers_total]]] followers')
            ->where('hasScope', true)
            ->where('channel.title', 'Old title')
            ->where('channel.game_name', 'Just Chatting')
            ->where('maxLength', 140)
        );
});

test('the preview renders with real values and reports the cut', function () {
    $patches = [];
    fakeHelix($patches);

    $this->actingAs($this->user)
        ->postJson('/settings/title/preview', ['template' => '[[[followers_total]]] followers'])
        ->assertOk()
        ->assertJson(['resolved' => '1234 followers', 'length' => 14, 'truncated' => false]);

    $this->actingAs($this->user)
        ->postJson('/settings/title/preview', ['template' => str_repeat('x', 150)])
        ->assertOk()
        ->assertJson(['length' => 150, 'truncated' => true]);

    $this->actingAs($this->user)
        ->postJson('/settings/title/preview', ['template' => '[[[channel_title]]]'])
        ->assertStatus(422)
        ->assertJsonPath('errors.template.0', fn (string $m) => str_contains($m, 'feed on itself'));

    expect($patches)->toBe([]);
});

test('category search proxies Helix and needs two characters', function () {
    $patches = [];
    fakeHelix($patches);

    $this->actingAs($this->user)
        ->getJson('/settings/title/categories?q=mine')
        ->assertOk()
        ->assertJson(['categories' => [['id' => '27471', 'name' => 'Minecraft']]]);

    $this->actingAs($this->user)->getJson('/settings/title/categories?q=m')->assertStatus(422);
});

test('setting a category writes game_id once and never the title', function () {
    $patches = [];
    fakeHelix($patches);
    livingTitleOn($this->user, '[[[followers_total]]] followers', ['last_written' => 'Before']);

    $this->actingAs($this->user)
        ->post('/settings/title/category', ['id' => '27471', 'name' => 'Minecraft'])
        ->assertRedirect()
        ->assertSessionHas('success', 'Category set to Minecraft.');

    expect($patches)->toBe([['game_id' => '27471']]);
    expect($this->user->fresh()->livingTitle()['last_written'])->toBe('Before');
});

test('the settings routes require a login', function () {
    $this->get('/settings/title')->assertRedirect();
    $this->patch('/settings/title', ['enabled' => true, 'template' => 'x'])->assertRedirect();
});
