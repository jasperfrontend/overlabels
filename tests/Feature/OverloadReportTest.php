<?php

use App\Events\AlertTriggered;
use App\Events\TwitchEventReceived;
use App\Http\Controllers\TwitchEventSubController;
use App\Jobs\CloseBusyStretch;
use App\Models\BotChatOutbox;
use App\Models\OverloadReport;
use App\Models\StreamState;
use App\Models\TwitchEvent;
use App\Models\User;
use App\Services\BotModeratedChannels;
use App\Services\MapSlugService;
use App\Services\OverloadReports\BusyStretch;
use App\Services\OverloadReports\ReportKinds;
use App\Services\TwitchApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * Overlabels Overload Report (#345): a busy stretch while live becomes a
 * public timeline page. Numbers and timestamps only.
 */
beforeEach(function () {
    Queue::fake([CloseBusyStretch::class]);
    $this->mock(BotModeratedChannels::class, fn ($mock) => $mock->shouldReceive('moderates')->andReturn(null));
});

function overloadStreamer(bool $live = true, array $prefs = []): User
{
    $user = User::factory()->create(['twitch_id' => (string) random_int(10_000_000, 99_999_999), 'name' => 'HostChannel']);
    if ($prefs !== []) {
        $user->setPreference('overload_report', $prefs)->save();
    }
    StreamState::forUser($user)->forceFill($live ? ['state' => 'live', 'confidence' => 1.0] : ['state' => 'offline', 'confidence' => 0.0])->save();

    return $user->fresh();
}

function overloadFollows(User $user, int $count): void
{
    for ($i = 0; $i < $count; $i++) {
        app(BusyStretch::class)->note($user, 'channel.follow', ['user_name' => "Viewer{$i}", 'user_id' => (string) (700 + $i)]);
    }
}

/** Let the quiet spell pass and run the closer, as the lane would. */
function overloadClose(User $user): ?OverloadReport
{
    test()->travel(BusyStretch::settings($user)['quiet_seconds'] + 1)->seconds();

    return app(BusyStretch::class)->close($user);
}

it('makes a report from a busy stretch while live, and schedules its closer on the lane', function () {
    $user = overloadStreamer();

    overloadFollows($user, 50);

    Queue::assertPushed(CloseBusyStretch::class, 1);
    $report = overloadClose($user);

    expect($report)->not->toBeNull()
        ->and($report->event_count)->toBe(50)
        ->and($report->timeline)->toHaveCount(1)
        ->and($report->timeline[0]['kind'])->toBe('follows')
        ->and($report->timeline[0]['count'])->toBe(50)
        ->and($report->code)->toMatch('/^\d{8}$/');
});

it('makes nothing below the threshold, and forgets the stretch', function () {
    $user = overloadStreamer();
    overloadFollows($user, 49);

    expect(overloadClose($user))->toBeNull()
        ->and(OverloadReport::count())->toBe(0)
        ->and(app(BusyStretch::class)->close($user))->toBeNull();
});

it('does not close a stretch that is still busy, and tries again later', function () {
    $user = overloadStreamer();
    overloadFollows($user, 30);

    test()->travel(30)->seconds();
    overloadFollows($user, 30);
    test()->travel(31)->seconds();

    expect(app(BusyStretch::class)->close($user))->toBeNull();
    Queue::assertPushed(CloseBusyStretch::class, 2);

    expect(overloadClose($user)->event_count)->toBe(60);
});

it('makes no report while offline, unless test mode is on for the hour', function () {
    $offline = overloadStreamer(live: false);
    overloadFollows($offline, 60);
    expect(overloadClose($offline))->toBeNull();

    $testing = overloadStreamer(live: false, prefs: ['test_until' => now()->addHour()->toIso8601String()]);
    overloadFollows($testing, 60);
    expect(overloadClose($testing))->not->toBeNull();

    $expired = overloadStreamer(live: false, prefs: ['test_until' => now()->subMinute()->toIso8601String()]);
    overloadFollows($expired, 60);
    expect(overloadClose($expired))->toBeNull();
});

it('judges live at the START of the stretch, so a raid at the end of a stream still gets its report', function () {
    $user = overloadStreamer();
    overloadFollows($user, 10);
    StreamState::forUser($user)->forceFill(['state' => 'offline', 'confidence' => 0.0])->save();
    overloadFollows($user, 50);

    expect(overloadClose($user)->event_count)->toBe(60);
});

it('counts only ticked kinds; channel points are off by default', function () {
    $user = overloadStreamer();
    for ($i = 0; $i < 60; $i++) {
        app(BusyStretch::class)->note($user, 'channel.channel_points_custom_reward_redemption.add', ['reward' => ['title' => 'Hydrate']]);
    }
    expect(overloadClose($user))->toBeNull();

    $unticked = overloadStreamer(prefs: ['events' => ['follows' => false]]);
    overloadFollows($unticked, 60);
    expect(overloadClose($unticked))->toBeNull();

    $off = overloadStreamer(prefs: ['enabled' => false]);
    overloadFollows($off, 60);
    expect(overloadClose($off))->toBeNull();
});

it('draws runs of one kind, a dot per raid and gift bomb, and keeps no viewer', function () {
    $user = overloadStreamer();
    $stretch = app(BusyStretch::class);

    overloadFollows($user, 20);
    $stretch->note($user, 'channel.subscription.gift', ['total' => 10, 'tier' => '1000', 'user_name' => 'Gifter']);
    $stretch->note($user, 'channel.subscription.gift', ['total' => 5, 'tier' => '2000', 'user_name' => 'Gifter2']);
    // Its recipients are never counted again.
    $stretch->note($user, 'channel.subscribe', ['is_gift' => true, 'user_name' => 'Lucky']);
    $stretch->note($user, 'channel.raid', ['viewers' => 1544, 'from_broadcaster_user_name' => 'Raider']);
    overloadFollows($user, 12);
    $stretch->note($user, 'channel.cheer', ['bits' => 100, 'user_name' => 'Cheerer', 'message' => 'hi']);
    $stretch->note($user, 'channel.cheer', ['bits' => 1100, 'user_name' => 'Cheerer2', 'message' => 'yo']);

    $report = overloadClose($user);
    $dots = array_map(fn ($dot) => ReportKinds::title($dot).' '.ReportKinds::line($dot), $report->timeline);

    expect($dots)->toBe([
        'Follows! 20 new followers.',
        'Gift subs! 10x Tier 1 gift sub received.',
        'Gift subs! 5x Tier 2 gift sub received.',
        'Raid! 1,544 viewers.',
        'Follows! 12 new followers.',
        'Bits! 1,200 bits in 2 cheers.',
    ])->and($report->event_count)->toBe(20 + 15 + 1 + 12 + 2);

    $stored = json_encode([$report->timeline, $report->totals]);
    foreach (['Viewer', 'Gifter', 'Lucky', 'Raider', 'Cheerer', 'hi', '700'] as $leak) {
        expect($stored)->not->toContain($leak);
    }
});

it('counts a hype train once, and its updates raise the level without counting', function () {
    $user = overloadStreamer(prefs: ['threshold' => 5]);
    $stretch = app(BusyStretch::class);

    // An update with no train in the stretch opens nothing.
    $stretch->note($user, 'channel.hype_train.progress', ['level' => 2]);
    expect(overloadClose($user))->toBeNull();

    $stretch->note($user, 'channel.hype_train.begin', ['level' => 1]);
    $stretch->note($user, 'channel.hype_train.progress', ['level' => 3]);
    overloadFollows($user, 4);
    $stretch->note($user, 'channel.hype_train.end', ['level' => 4]);

    $report = overloadClose($user);
    expect($report->event_count)->toBe(5)
        ->and(ReportKinds::line($report->timeline[0]))->toBe('Level 4 reached.');
});

it('counts a flood tick as its individual events', function () {
    $user = overloadStreamer();
    app(BusyStretch::class)->note($user, 'channel.follow', ['user_name' => 'Newest'], 5400);

    $report = overloadClose($user);
    expect($report->event_count)->toBe(5400)
        ->and(ReportKinds::summary($report->totals, 180))->toBe('5,400 follows in 3 minutes');
});

it('notes events handled by the lane', function () {
    $this->mock(TwitchApiService::class, function ($mock) {
        $mock->shouldReceive('enrichEventWithUserAvatars')->andReturnUsing(fn ($token, $event) => $event);
        $mock->shouldIgnoreMissing();
    });
    Event::fake([TwitchEventReceived::class, AlertTriggered::class]);
    $user = overloadStreamer(prefs: ['threshold' => 3]);

    for ($i = 0; $i < 3; $i++) {
        $data = ['subscription' => ['type' => 'channel.follow'], 'event' => [
            'broadcaster_user_id' => $user->twitch_id, 'user_id' => (string) (900 + $i), 'user_name' => "V{$i}", 'user_login' => "v{$i}",
        ]];
        $row = TwitchEvent::create(['user_id' => $user->id, 'event_type' => 'channel.follow', 'event_data' => $data['event'], 'twitch_timestamp' => now()]);
        app(TwitchEventSubController::class)->processStoredEvent($row, $data);
    }

    expect(overloadClose($user)?->event_count)->toBe(3);
});

it('posts one line in chat only from an enabled bot that is a moderator', function () {
    $this->mock(BotModeratedChannels::class, fn ($mock) => $mock->shouldReceive('moderates')->andReturn(true));
    $modded = overloadStreamer();
    $modded->forceFill(['bot_enabled' => true])->save();
    overloadFollows($modded, 50);
    $report = overloadClose($modded);

    $line = BotChatOutbox::where('user_id', $modded->id)->sole()->message;
    expect($line)->toStartWith('Overlabels Overload Report: 50 follows in ')
        ->and($line)->toEndWith($report->url().' (!ol report stop turns these off)');

    $botOff = overloadStreamer();
    overloadFollows($botOff, 50);
    overloadClose($botOff);
    expect(BotChatOutbox::where('user_id', $botOff->id)->count())->toBe(0);
});

it('posts nothing when the bot is not a moderator', function () {
    $this->mock(BotModeratedChannels::class, fn ($mock) => $mock->shouldReceive('moderates')->andReturn(false));
    $user = overloadStreamer();
    $user->forceFill(['bot_enabled' => true])->save();
    overloadFollows($user, 50);
    overloadClose($user);

    expect(BotChatOutbox::count())->toBe(0);
});

it('serves the public page and its Markdown, noindex, and 404s on anything else', function () {
    $user = overloadStreamer();
    overloadFollows($user, 50);
    $report = overloadClose($user);
    $slug = app(MapSlugService::class)->encode($user->twitch_id);

    $this->get("/report/{$slug}/{$report->code}")
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('noindex, nofollow', false)
        ->assertSee('HostChannel')
        ->assertSee('50 new followers.')
        ->assertSee('kept until');

    $this->get("/report/{$slug}/{$report->code}.md")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('# Overlabels Overload Report', false)
        ->assertSee('Follows! 50 new followers.', false);

    $wrong = $report->code === '00000000' ? '00000001' : '00000000';
    $this->get("/report/{$slug}/{$wrong}")->assertNotFound();
    $this->get("/report/nope/{$report->code}")->assertNotFound();
    $other = app(MapSlugService::class)->encode(overloadStreamer()->twitch_id);
    $this->get("/report/{$other}/{$report->code}")->assertNotFound();

    $this->travel(OverloadReport::KEEP_DAYS + 1)->days();
    $this->get("/report/{$slug}/{$report->code}")->assertNotFound();
});

it('saves settings, and test mode keeps the hour it started with', function () {
    $user = overloadStreamer();
    $payload = [
        'enabled' => true, 'threshold' => 80, 'quiet_seconds' => 90,
        'events' => array_map(fn () => true, ReportKinds::KINDS), 'test_mode' => true,
    ];

    $this->actingAs($user)->patch('/settings/overload-report', $payload)->assertRedirect();
    $user->refresh();
    $first = BusyStretch::settings($user)['test_until'];
    expect(BusyStretch::settings($user)['threshold'])->toBe(80)
        ->and(BusyStretch::testModeOn($user))->toBeTrue();

    $this->travel(10)->minutes();
    $this->actingAs($user)->patch('/settings/overload-report', $payload)->assertRedirect();
    expect(BusyStretch::settings($user->refresh())['test_until'])->toBe($first);

    $this->actingAs($user)->patch('/settings/overload-report', [...$payload, 'events' => ['nope' => true]])->assertSessionHasErrors('events');
    $this->actingAs($user)->get('/settings/overload-report')->assertOk();
});

it('lists kept reports on the dashboard', function () {
    $user = overloadStreamer();
    overloadFollows($user, 50);
    overloadClose($user);

    $this->actingAs($user)->get('/dashboard')
        ->assertInertia(fn ($page) => $page->has('overloadReports', 1)->where('overloadReports.0.summary', fn ($s) => str_starts_with($s, '50 follows in ')));
});

it('prints the page itself through Gotenberg, in the reader\'s timezone, behind basic auth', function () {
    config(['services.gotenberg' => ['url' => 'https://printer.test', 'username' => 'u', 'password' => 'p']]);
    Http::fake(['printer.test/*' => Http::response('%PDF-1.4 fake', 200, ['Content-Type' => 'application/pdf'])]);

    $user = overloadStreamer();
    overloadFollows($user, 50);
    $report = overloadClose($user);
    $slug = app(MapSlugService::class)->encode($user->twitch_id);

    $this->get("/report/{$slug}/{$report->code}")->assertSee('Download as PDF')->assertDontSee('window.print()', false);

    $this->get("/report/{$slug}/{$report->code}.pdf?tz=Europe/Amsterdam")
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertSee('%PDF-1.4 fake', false);

    Http::assertSent(function ($request) use ($report) {
        $html = collect($request->data())->firstWhere('name', 'files')['contents'] ?? '';
        $amsterdam = $report->started_at->copy()->setTimezone('Europe/Amsterdam')->format('H:i');

        return $request->url() === 'https://printer.test/forms/chromium/convert/html'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('u:p'))
            && str_contains($html, '50 new followers.')
            && str_contains($html, $amsterdam)
            && str_contains($html, '(Europe/Amsterdam)')
            && ! str_contains($html, '<script')
            && ! str_contains($html, 'Download as');
    });
});

it('falls back to the browser\'s print when the printer is missing or fails', function () {
    $user = overloadStreamer();
    overloadFollows($user, 50);
    $report = overloadClose($user);
    $slug = app(MapSlugService::class)->encode($user->twitch_id);

    config(['services.gotenberg.url' => null]);
    $this->get("/report/{$slug}/{$report->code}")->assertSee('window.print()', false)->assertDontSee('Download as PDF');
    $this->get("/report/{$slug}/{$report->code}.pdf")->assertStatus(503);

    config(['services.gotenberg' => ['url' => 'https://printer.test', 'username' => 'u', 'password' => 'p']]);
    Http::fake(['printer.test/*' => Http::response('nope', 500)]);
    $this->get("/report/{$slug}/{$report->code}.pdf?tz=Not/AZone")->assertStatus(503);
});
