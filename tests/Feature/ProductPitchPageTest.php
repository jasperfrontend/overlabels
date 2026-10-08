<?php

use App\Services\Recipes\RecipeCatalog;
use Illuminate\Support\Collection;

/**
 * The full product page (products/page.blade.php). Every chat product carries
 * a `pitch` block in its manifest and gets this page; the donation alerts
 * carry none and keep the short page. The setup steps are derived from
 * requires_bot rather than written per product, so they are pinned here too.
 */
function listedProducts(string $category): Collection
{
    return collect(app(RecipeCatalog::class)->listed())->where('category', $category);
}

it('gives every chat product a pitch and no alert one', function () {
    $products = listedProducts('product');
    expect($products)->not->toBeEmpty();

    foreach ($products as $product) {
        expect($product)->toHaveKey('pitch');
    }

    foreach (listedProducts('alert') as $alert) {
        expect($alert)->not->toHaveKey('pitch');
    }
});

it('renders every section of a chat product page', function () {
    foreach (listedProducts('product') as $product) {
        $pitch = $product['pitch'];
        $response = $this->get(route('products.show', $product['slug']))->assertOk();

        $response->assertSee($pitch['tagline']);
        $response->assertSee('One command. Three things happen.');
        $response->assertSee($pitch['reasons_heading']);
        $response->assertSee('Before you click.');
        $response->assertSee($pitch['closing']);
        $response->assertSee('Works with');
        $response->assertSee('Is it really free?');
        $response->assertSee('Can I remove it later?');

        foreach ($pitch['plays'] as $play) {
            $response->assertSee(str_replace('`', '', $play['title']));
        }
        foreach ($pitch['faq'] as $item) {
            $response->assertSee(str_replace('`', '', $item['q']));
        }

        // No pretend OBS title bar on a demo: the page has its own heading.
        $response->assertDontSee('live in OBS');
    }
});

it('shows the homepage demos without the OBS title bar', function () {
    // ", live in OBS" is the bar's own wording ("Chat Tower, live in OBS"); the
    // Controls section further down says "live in OBS" in a sentence.
    $this->get('/')->assertOk()->assertDontSee(', live in OBS');
});

it('derives the setup steps from whether the product needs the bot', function () {
    $tower = $this->get(route('products.show', 'chat-tower'))->assertOk();
    $tower->assertSee('Ready in three steps');
    $tower->assertSee('Switch on the bot');
    $tower->assertSee('<code class="pp-code">!stack</code>', escape: false);

    $bubbles = $this->get(route('products.show', 'chat-emote-bubbles'))->assertOk();
    $bubbles->assertSee('Ready in two steps');
    $bubbles->assertDontSee('Switch on the bot');
});

it('shows the designer presets as looks when the manifest declares none', function () {
    $bubbles = app(RecipeCatalog::class)->find('chat-emote-bubbles');
    expect($bubbles['pitch'])->not->toHaveKey('looks');

    $response = $this->get(route('products.show', 'chat-emote-bubbles'))->assertOk();
    foreach ($bubbles['designer']['presets'] as $preset) {
        $response->assertSee($preset['label']);
    }
});

it('links the home location note to the Chat Checkin settings', function () {
    $this->get(route('products.show', 'chat-checkin'))
        ->assertOk()
        ->assertSee('href="'.route('settings.integrations.checkin.show').'"', escape: false);
});

it('ships every picture a pitch points at', function () {
    foreach (listedProducts('product') as $product) {
        $pitch = $product['pitch'];
        $paths = array_merge(
            array_filter(array_map(fn ($play) => $play['art']['src'] ?? null, $pitch['plays'])),
            array_filter(array_map(fn ($look) => $look['image'] ?? null, $pitch['looks'] ?? [])),
        );

        foreach ($paths as $path) {
            expect(public_path(ltrim($path, '/')))->toBeFile();
        }
    }
});

it('keeps the short page for a donation alert', function () {
    $alert = listedProducts('alert')->first();

    $this->get(route('products.show', $alert['slug']))
        ->assertOk()
        ->assertSee($alert['name'])
        ->assertDontSee('One command. Three things happen.');
});

it('uses a pitch\'s own search title and description when it names them', function () {
    $pitch = listedProducts('product')->firstWhere('slug', 'twitch-chat-overlay')['pitch'];

    $this->get(route('products.show', 'twitch-chat-overlay'))
        ->assertOk()
        ->assertSee('<title>'.e($pitch['meta_title']).'</title>', escape: false)
        ->assertSee('<meta name="description" content="'.e($pitch['meta_description']).'" />', escape: false);
});

it('keeps the generic search title for a pitch that names none', function () {
    $this->get(route('products.show', 'chat-tower'))
        ->assertOk()
        ->assertSee('<title>Chat Tower - Overlabels product</title>', escape: false);
});

it('turns a [text](url) in an answer into a link, off-site ones in a new tab', function () {
    $this->get(route('products.show', 'twitch-chat-overlay'))
        ->assertOk()
        ->assertSee('<a href="https://discord.gg/qANdj5QPpd" class="cursor-pointer text-sky-500 hover:underline" target="_blank" rel="noopener noreferrer">Overlabels Discord</a>', escape: false)
        ->assertSee('<a href="/help/llms-txt" class="cursor-pointer text-sky-500 hover:underline">Ask an AI to do it for you</a>', escape: false)
        ->assertDontSee('](', escape: false);
});

it('gives the Twitch Chat Overlay the three-knob designer on its real stylesheet', function () {
    $html = $this->get(route('products.show', 'twitch-chat-overlay'))->assertOk()->getContent();

    expect($html)->toContain('data-looks-teaser')
        ->and($html)->toContain('data-look="vapor"')
        // The overlay's own rules, with every control tag filled in.
        ->and($html)->toContain('.skin-terminal .name::before')
        ->and($html)->not->toContain('[[[c:');
});

it('keeps the looks as text on a product without the three-knob designer', function () {
    $this->get(route('products.show', 'chat-emote-bubbles'))
        ->assertOk()
        ->assertDontSee('data-looks-teaser', escape: false);
});
