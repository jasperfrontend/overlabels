<?php

use App\Models\BotChatOutbox;
use App\Models\ListAppender;
use App\Models\OptionSet;
use App\Models\OverlayControl;
use App\Models\User;
use App\Services\Lists\ListAppendService;
use App\Services\TwitchApiService;
use App\Support\ListItems;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * A random-mode control has no stored value: every resolveDisplayValue()
 * is a fresh roll. One chat command is one query, so every template that
 * fires from it (the value written to the list AND the reply spoken in
 * chat) must see the SAME roll. The chatter is told one number and the
 * list must hold that number, not a second roll.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    $stub = new class extends TwitchApiService
    {
        public function __construct() {}

        public function getExtendedUserData(string $accessToken, string $twitchId): array
        {
            return [];
        }
    };
    app()->instance(TwitchApiService::class, $stub);
});

it('writes the same random roll to the list that it speaks in the success reply', function () {
    $user = User::factory()->create([
        'bot_enabled' => true,
        'twitch_data' => ['login' => 'streamer_r'],
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);

    OverlayControl::create([
        'overlay_template_id' => null,
        'user_id' => $user->id,
        'key' => 'random1000',
        'type' => 'number',
        'value' => '0',
        'config' => ['min' => 0, 'max' => 1000000, 'random' => true],
        'source_managed' => false,
    ]);

    $built = ListItems::freshFromValues([], 1);
    $list = OptionSet::create([
        'user_id' => $user->id,
        'slug' => 'hot_pool',
        'items' => $built['items'],
        'next_item_id' => $built['next_id'],
        'min_items' => 0,
        'user_editable' => true,
    ]);

    $appender = ListAppender::create([
        'user_id' => $user->id,
        'target_list_id' => $list->id,
        'command' => 'hot',
        'value_template' => '[[[bot:from_user]]] is [[[c:random1000]]]% hot',
        'success_reply' => '[[[bot:from_user]]] is [[[c:random1000]]]% hot ([[[c:random1000]]]%)',
        'dedup_policy' => ListAppender::DEDUP_NONE,
        'max_size' => null,
        'enabled' => true,
    ]);

    $svc = app(ListAppendService::class);
    $mismatches = [];

    for ($i = 0; $i < 5; $i++) {
        $result = $svc->fire($appender, $user, [
            'channel_login' => 'streamer_r',
            'command' => 'hot',
            'chatter_id' => (string) (1000 + $i),
            'chatter_login' => 'chatter'.$i,
            'chatter_display_name' => 'Chatter'.$i,
            'badges' => [],
            'args' => '',
        ]);

        expect($result['fired'])->toBeTrue();

        preg_match('/is (\d+)% hot$/', $result['value'], $v);
        preg_match('/is (\d+)% hot \((\d+)%\)$/', $result['reply'], $r);

        // Within one resolve() the two occurrences agree (one control map).
        expect($r[1])->toBe($r[2]);

        if ($v[1] !== $r[1]) {
            $mismatches[] = "list={$v[1]} reply={$r[1]}";
        }
    }

    expect(BotChatOutbox::where('user_id', $user->id)->count())->toBe(5);

    expect($mismatches)->toBe([], 'value_template and success_reply rolled independently: '.implode(', ', $mismatches));
});
