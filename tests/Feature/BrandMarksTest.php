<?php

use App\Support\BrandMarks;

/**
 * The brand tiles on the homepage hero and the product pages all come from
 * App\Support\BrandMarks through <x-brand-icons>. These pin that every mark
 * can actually be drawn, and that the pages render the tiles by name.
 */
it('gives every mark a name, a tile colour and something to draw', function () {
    foreach (BrandMarks::MARKS as $key => $mark) {
        expect($mark['name'])->not->toBeEmpty()
            ->and($mark['bg'])->toMatch('/^#[0-9a-f]{6}$/i')
            ->and($mark['scale'])->toBeGreaterThan(0)->toBeLessThanOrEqual(100);

        if (isset($mark['img'])) {
            expect(public_path(ltrim($mark['img'], '/')))->toBeFile();
        } else {
            expect($mark['paths'])->not->toBeEmpty()
                ->and($mark['viewBox'])->not->toBeEmpty()
                ->and($mark['fg'])->toMatch('/^#[0-9a-f]{6}$/i');
        }
    }
});

it('lets a caller recolour a tile', function () {
    expect(BrandMarks::resolve(['icon' => 'twitch', 'bg' => '#000000'])['bg'])->toBe('#000000')
        ->and(BrandMarks::resolve('twitch')['bg'])->toBe('#9146ff');
});

it('refuses a mark it does not know', function () {
    BrandMarks::resolve('mixer');
})->throws(InvalidArgumentException::class);

it('shows where products run on the homepage and on a product page', function () {
    $this->get('/')->assertOk()->assertSee('aria-label="StreamElements"', escape: false);

    $this->get(route('products.show', 'chat-tower'))
        ->assertOk()
        ->assertSee('aria-label="OBS Studio"', escape: false)
        ->assertDontSee('Shows emotes from');

    $this->get(route('products.show', 'chat-emote-bubbles'))
        ->assertOk()
        ->assertSee('Shows emotes from')
        ->assertSee('aria-label="FrankerFaceZ"', escape: false);
});
