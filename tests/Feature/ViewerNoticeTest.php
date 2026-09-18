<?php

use App\Services\ViewerNoticeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;

uses(DatabaseTransactions::class);

// Notice, not gate. A consent prompt before anything worked would mean keeping
// an acceptance record for every viewer who ever typed in an Overlabels
// channel, including everyone who ignored it - a bigger and more permanent
// viewer database than the one it would exist to justify. This tells them
// instead, once, on the reply they were getting anyway.

beforeEach(fn () => Cache::flush());

it('appends the notice the first time a viewer is seen', function () {
    $decorated = app(ViewerNoticeService::class)->decorate('Alice checked in from Amsterdam!', '555000');

    expect($decorated)->toContain('Alice checked in from Amsterdam!')
        ->and($decorated)->toContain('overlabels.com/viewers');
});

it('says nothing the second time', function () {
    $service = app(ViewerNoticeService::class);

    $service->decorate('first', '555000');
    $second = $service->decorate('second', '555000');

    expect($second)->toBe('second');
});

it('tells a different viewer separately', function () {
    $service = app(ViewerNoticeService::class);

    $service->decorate('first', '555000');

    expect($service->decorate('hello', '999999'))->toContain('overlabels.com/viewers');
});

it('leaves a reply alone when there is no chatter id', function () {
    expect(app(ViewerNoticeService::class)->decorate('hello', null))->toBe('hello');
    expect(app(ViewerNoticeService::class)->decorate('hello', ''))->toBe('hello');
});

it('never decorates an empty reply into a bare advert', function () {
    expect(app(ViewerNoticeService::class)->decorate('', '555000'))->toBe('');
});

// Twitch cuts a chat message at 500 characters. Appending the notice to a
// reply that is already long would push the interesting half off the end.
it('leaves a long reply undecorated rather than truncating it', function () {
    $long = str_repeat('a', 470);

    expect(app(ViewerNoticeService::class)->decorate($long, '555000'))->toBe($long);
});

it('tells a viewer again after they have asked to be forgotten', function () {
    $service = app(ViewerNoticeService::class);

    $service->decorate('first', '555000');
    expect($service->decorate('second', '555000'))->toBe('second');

    $service->forget('555000');

    expect($service->decorate('third', '555000'))->toContain('overlabels.com/viewers');
});
