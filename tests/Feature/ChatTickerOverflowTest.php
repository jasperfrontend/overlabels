<?php

use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Recipes\RecipeInstaller;
use App\Support\ChatPresets;
use App\Support\OverlayMarkdown;
use Illuminate\Foundation\Testing\DatabaseTransactions;

uses(DatabaseTransactions::class);

/**
 * A ticker is one line and Twitch allows 500 characters per message. The
 * recipe caps a ticker message body at about 200 characters with an ellipsis,
 * and a migration puts the same rule into overlays installed before it had one.
 */
const TICKER_OLD_RULE = '.layout-ticker .msg { white-space: nowrap; overflow-wrap: normal; }';

const TICKER_MIGRATION = 'database/migrations/2026_09_22_160000_cap_ticker_message_length_on_installed_chat_overlays.php';

function tickerRecipeCss(): string
{
    $file = RecipeInstaller::directoryFor(ChatPresets::PRODUCT).DIRECTORY_SEPARATOR.'chat.md';

    return OverlayMarkdown::parse(file_get_contents($file))['css'];
}

it('caps a ticker message body at about 200 characters and cuts it with an ellipsis', function () {
    $css = tickerRecipeCss();

    preg_match('/\.layout-ticker \.body \{([^}]*)\}/', $css, $body);
    expect($body)->not->toBeEmpty('the recipe has no .layout-ticker .body rule');

    $declarations = $body[1];
    expect($declarations)->toContain('max-width: 200ch')
        ->and($declarations)->toContain('overflow: hidden')
        ->and($declarations)->toContain('text-overflow: ellipsis')
        // Without this a flex item refuses to shrink below its content width,
        // and the cap does nothing.
        ->and($declarations)->toContain('min-width: 0');

    // The body only clips inside a flex row; the old inline-flow rule is gone.
    // A flex row drops the whitespace between the spans, and Bubbles has no
    // colon to carry a margin, so the gap is what keeps a name off its message.
    preg_match('/\.layout-ticker \.msg \{([^}]*)\}/', $css, $msg);
    expect($msg[1] ?? '')->toContain('display: inline-flex')
        ->and($msg[1] ?? '')->toContain('column-gap: 0.25em')
        ->and($css)->not->toContain(TICKER_OLD_RULE);
});

it('rewrites the old ticker rule on an installed overlay, and leaves any other CSS alone', function () {
    $user = User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
        'twitch_data' => ['login' => 'ticker'.fake()->unique()->randomNumber(5)],
    ]);

    $old = "html { margin: 0; }\n".TICKER_OLD_RULE."\n.mine { color: red; }";
    $installed = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'css' => $old]);
    $edited = OverlayTemplate::factory()->create(['owner_id' => $user->id, 'css' => '.layout-ticker .msg { white-space: normal; }']);

    $migration = require base_path(TICKER_MIGRATION);
    $migration->up();

    $css = $installed->fresh()->css;
    expect($css)->not->toContain(TICKER_OLD_RULE)
        ->and($css)->toContain('.layout-ticker .body { min-width: 0; max-width: 200ch; overflow: hidden; text-overflow: ellipsis; }')
        ->and($css)->toStartWith("html { margin: 0; }\n")
        ->and($css)->toEndWith("\n.mine { color: red; }")
        ->and($edited->fresh()->css)->toBe('.layout-ticker .msg { white-space: normal; }');

    $migration->down();

    expect($installed->fresh()->css)->toBe($old);
});

it('installs the capped rule fresh, so the migration has nothing to do on a new install', function () {
    expect(tickerRecipeCss())->not->toContain(TICKER_OLD_RULE);
});
