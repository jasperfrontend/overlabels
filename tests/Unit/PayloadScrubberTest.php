<?php

use App\Services\External\PayloadScrubber;

// The key names here are the ones observed on real stored production rows on
// 2026-09-18: every Ko-fi event carried `email` and `verification_token`, some
// carried `discord_username`, and `shipping` was present on all of them,
// populated only once a shop order or commission comes in.

it('drops contact and postal keys from a flat payload', function () {
    $clean = PayloadScrubber::scrub([
        'from_name' => 'Alice',
        'amount' => '5.00',
        'email' => 'alice@example.com',
        'shipping' => ['street_address' => '1 Example Road'],
        'discord_username' => 'alice#0001',
        'discord_userid' => '123',
    ]);

    expect($clean)->toBe(['from_name' => 'Alice', 'amount' => '5.00']);
});

it('drops our own webhook secret so a credential does not sit in the event log', function () {
    $clean = PayloadScrubber::scrub([
        'verification_token' => 'the-secret',
        'type' => 'Donation',
    ]);

    expect($clean)->toBe(['type' => 'Donation']);
});

it('reaches keys nested at any depth', function () {
    $clean = PayloadScrubber::scrub([
        'data' => [
            'username' => 'Bob',
            'email' => 'bob@example.com',
            'commission' => [
                'title' => 'A drawing',
                'shipping_address' => ['city' => 'Amsterdam'],
            ],
        ],
    ]);

    expect($clean['data'])->toHaveKey('username');
    expect($clean['data'])->not->toHaveKey('email');
    expect($clean['data']['commission'])->toHaveKey('title');
    expect($clean['data']['commission'])->not->toHaveKey('shipping_address');
});

it('reaches keys inside a list of items', function () {
    $clean = PayloadScrubber::scrub([
        'shop_items' => [
            ['direct_link_code' => 'abc', 'email' => 'buyer@example.com'],
            ['direct_link_code' => 'def'],
        ],
    ]);

    expect($clean['shop_items'][0])->toBe(['direct_link_code' => 'abc']);
    expect($clean['shop_items'][1])->toBe(['direct_link_code' => 'def']);
});

it('matches key names regardless of case', function () {
    $clean = PayloadScrubber::scrub([
        'Email' => 'a@example.com',
        'SHIPPING' => ['x' => 1],
        'Message' => 'kept',
    ]);

    expect($clean)->toBe(['Message' => 'kept']);
});

it('leaves a payload with nothing sensitive in it untouched', function () {
    $payload = [
        'from_name' => 'Alice',
        'message' => 'Hello!',
        'amount' => '5.00',
        'currency' => 'USD',
        'nested' => ['item_name' => 'A mug', 'price' => 12],
    ];

    expect(PayloadScrubber::scrub($payload))->toBe($payload);
});

it('keeps numeric list keys intact', function () {
    $clean = PayloadScrubber::scrub(['tags' => ['a', 'b', 'c']]);

    expect($clean['tags'])->toBe(['a', 'b', 'c']);
});
