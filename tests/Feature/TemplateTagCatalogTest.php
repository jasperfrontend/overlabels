<?php

use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Models\User;
use App\Services\JsonTemplateParserService;
use App\Services\TemplateDataMapperService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A Twitch payload shaped like getExtendedUserData() returns.
 */
function twitchPayload(string $broadcasterType = '', array $overrides = []): array
{
    return array_replace_recursive([
        'user' => [
            'id' => '73327367',
            'login' => 'streamer',
            'display_name' => 'Streamer',
            'type' => '',
            'broadcaster_type' => $broadcasterType,
            'description' => 'hello',
            'profile_image_url' => 'https://cdn.example/avatar.png',
            'offline_image_url' => '',
            'view_count' => 0,
            'created_at' => '2020-01-01T00:00:00Z',
        ],
        'channel' => [
            'broadcaster_id' => '73327367',
            'broadcaster_login' => 'streamer',
            'broadcaster_name' => 'Streamer',
            'broadcaster_language' => 'en',
            'game_id' => '509658',
            'game_name' => 'Just Chatting',
            'title' => 'Hello world',
            'delay' => 0,
            'tags' => ['Gaming', 'Fun'],
            'content_classification_labels' => [],
            'is_branded_content' => false,
        ],
        'channel_followers' => [
            'total' => 1234,
            'data' => [[
                'user_id' => '999',
                'user_login' => 'bob',
                'user_name' => 'Bob',
                'followed_at' => '2026-01-01T00:00:00Z',
            ]],
        ],
        'followed_channels' => ['total' => 5, 'data' => []],
        'subscribers' => ['total' => 42, 'points' => 90, 'data' => []],
        'goals' => ['data' => []],
    ], $overrides);
}

function syncFor(User $user, array $payload): array
{
    return app(JsonTemplateParserService::class)->syncTagsForUser($user, $payload);
}

function tagNamesFor(User $user): array
{
    return TemplateTag::where('user_id', $user->id)->orderBy('tag_name')->pluck('tag_name')->all();
}

beforeEach(function () {
    $this->user = User::factory()->create();
});

/*
|--------------------------------------------------------------------------
| The drift guard
|--------------------------------------------------------------------------
|
| Before Aug 2026 the mapping, the categories, the descriptions and the sample
| data were four hand-maintained lists. They disagreed: the tag browser offered
| `followers_latest_name` and `channel_tags`, which the mapping could not
| produce, so both rendered nothing. These are the assertions that make that
| unrepresentable.
*/

it('produces every tag the categories advertise', function () {
    $mapper = app(TemplateDataMapperService::class);

    $advertised = collect($mapper->getTagCategories())
        ->flatMap(fn ($category) => $category['tags'])
        // event.* tags come from an EventSub payload at render time, not from
        // the Twitch snapshot, so they are declared rather than mapped.
        ->reject(fn ($tag) => str_starts_with($tag, 'event.'))
        ->values();

    $produced = array_keys($mapper->mapTwitchDataForTemplates(twitchPayload(), 'overlay'));

    expect($advertised->diff($produced)->all())->toBe([]);
});

it('gives every catalogue tag a category, a description and a sample', function () {
    $mapper = app(TemplateDataMapperService::class);

    $categorised = collect($mapper->getTagCategories())->flatMap(fn ($c) => $c['tags'])->all();
    $described = array_keys($mapper->getAvailableTemplateTags());
    $sampled = array_keys($mapper->getSampleTemplateData());

    foreach (array_keys(TemplateDataMapperService::tagCatalog()) as $tagName) {
        expect($categorised)->toContain($tagName)
            ->and($described)->toContain($tagName)
            ->and($sampled)->toContain($tagName);
    }
});

/*
|--------------------------------------------------------------------------
| Determinism
|--------------------------------------------------------------------------
*/

it('offers the same tags regardless of what the account currently contains', function () {
    // The old walker derived tag names from the payload, so an account with no
    // followers that night simply never got followers_latest_*. The catalogue
    // must not care.
    $full = twitchPayload('affiliate');
    $empty = [
        'user' => ['broadcaster_type' => 'affiliate'],
        'channel' => [],
        'channel_followers' => ['total' => 0, 'data' => []],
        'followed_channels' => ['total' => 0, 'data' => []],
        'subscribers' => ['total' => 0, 'points' => 0, 'data' => []],
        'goals' => ['data' => []],
    ];

    syncFor($this->user, $full);
    $fromFull = tagNamesFor($this->user);

    $other = User::factory()->create();
    syncFor($other, $empty);

    expect(tagNamesFor($other))->toBe($fromFull);
});

it('is idempotent', function () {
    $payload = twitchPayload('affiliate');

    $first = syncFor($this->user, $payload);
    $second = syncFor($this->user, $payload);

    expect($first['tags'])->toBeGreaterThan(0)
        ->and($second['tags'])->toBe(0)
        ->and($second['removed'])->toBe(0)
        ->and(tagNamesFor($this->user))->toBe(array_keys(collect(TemplateDataMapperService::tagCatalog())->sortKeys()->all()));
});

/*
|--------------------------------------------------------------------------
| Pruning (what CleanupRedundantTags used to half-do)
|--------------------------------------------------------------------------
*/

it('deletes rows that are not in the catalogue', function () {
    syncFor($this->user, twitchPayload());

    $category = TemplateTagCategory::where('user_id', $this->user->id)->first();

    // The exact artefacts the old walker produced.
    foreach (['channel_count', 'user', 'channel_followers_pagination_cursor', 'channel_content_classification_labels_0'] as $junk) {
        TemplateTag::factory()->create([
            'user_id' => $this->user->id,
            'category_id' => $category->id,
            'tag_name' => $junk,
        ]);
    }

    $result = syncFor($this->user, twitchPayload());

    expect($result['removed'])->toBe(4)
        ->and(tagNamesFor($this->user))->not->toContain('channel_count')
        ->and(tagNamesFor($this->user))->not->toContain('channel_followers_pagination_cursor');
});

it('drops a category once nothing is left in it', function () {
    syncFor($this->user, twitchPayload());

    // A category holding nothing but artefacts goes when they are pruned.
    $orphan = TemplateTagCategory::create([
        'user_id' => $this->user->id,
        'name' => 'legacy',
        'display_name' => 'Legacy',
        'description' => 'from the old walker',
        'is_group' => false,
        'sort_order' => 99,
    ]);
    TemplateTag::factory()->create([
        'user_id' => $this->user->id,
        'category_id' => $orphan->id,
        'tag_name' => 'channel_followers_pagination_cursor',
    ]);

    syncFor($this->user, twitchPayload());

    expect(TemplateTagCategory::where('user_id', $this->user->id)->pluck('name'))->not->toContain('legacy');
});

/*
|--------------------------------------------------------------------------
| No tailoring: everyone gets the same list
|--------------------------------------------------------------------------
|
| Withholding the subscriber and goal tags from non-affiliates was built and
| then removed. Twitch serves those endpoints only to affiliates, so the tags
| resolve to 0 or empty for everyone else - and empty renders as nothing, which
| is the same outcome as not having the tag. Meanwhile the starter template
| every account copies references subscribers_*, so hiding them left a
| non-affiliate looking at a tag in their own overlay that the browser denied
| existed.
*/

it('offers the subscriber and goal tags to an account that is not an affiliate', function () {
    syncFor($this->user, twitchPayload(''));

    expect(tagNamesFor($this->user))
        ->toContain('subscribers_total')
        ->toContain('goals_latest_target');
});

it('gives an affiliate and a non-affiliate byte-identical tag lists', function () {
    $plebeian = User::factory()->create();
    $affiliate = User::factory()->create();

    syncFor($plebeian, twitchPayload(''));
    syncFor($affiliate, twitchPayload('partner'));

    expect(tagNamesFor($plebeian))->toBe(tagNamesFor($affiliate));
});

it('still renders a withheld-looking tag as empty rather than absent', function () {
    // The reason no gate is needed: a non-affiliate's subscriber tags resolve,
    // they just resolve to nothing.
    $mapped = app(TemplateDataMapperService::class)
        ->mapForTemplate(twitchPayload(''), 'overlay', ['subscribers_latest_user_name']);

    expect($mapped)->toHaveKey('subscribers_latest_user_name')
        ->and($mapped['subscribers_latest_user_name'])->toBe('');
});

/*
|--------------------------------------------------------------------------
| Stored samples
|--------------------------------------------------------------------------
*/

it('stores the sample a tag actually renders, not the raw payload value', function () {
    syncFor($this->user, twitchPayload());

    $tags = TemplateTag::where('user_id', $this->user->id)->get()->keyBy('tag_name');

    expect($tags['channel_tags']->sample_data)->toBe('Gaming, Fun')
        ->and($tags['followers_latest_date']->sample_data)->toBeInt()
        ->and($tags['followers_total']->sample_data)->toBe(1234);
});

it('falls back to the catalogue sample when the account has no value at that path', function () {
    syncFor($this->user, twitchPayload());

    $tag = TemplateTag::where('user_id', $this->user->id)->where('tag_name', 'channel_tags_7')->first();

    // The account has two channel tags, so index 7 resolves to nothing.
    expect($tag->sample_data)->toBe('');
});

/*
|--------------------------------------------------------------------------
| Rendering contract
|--------------------------------------------------------------------------
*/

it('renders channel_tags as a joined string', function () {
    // Advertised, described and sampled for months while the mapping emitted it
    // under the name channel_tags_count as a JSON array.
    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates(twitchPayload(), 'overlay');

    expect($mapped['channel_tags'])->toBe('Gaming, Fun')
        ->and($mapped)->not->toHaveKey('channel_tags_count');
});

it('renders nothing rather than erroring when a tag has no value', function () {
    syncFor($this->user, twitchPayload());

    $tag = TemplateTag::where('user_id', $this->user->id)->where('tag_name', 'channel_tags_7')->first();

    // formatData() has a `: string` return type; a null here used to raise a
    // TypeError, which is an Error and slips past the controller's catch.
    expect($tag->getFormattedOutput(twitchPayload()))->toBe('');
});

/*
|--------------------------------------------------------------------------
| Why channel_avatar lives in the channel namespace
|--------------------------------------------------------------------------
|
| OverlayRenderer.vue spreads each inbound EventSub payload straight into the
| overlay's tag data. Twitch names the acting user's fields `user_*`, so a
| single follow repoints every bare user_* tag at the follower - deliberate,
| and what the user_* callout in TemplateTagsList.vue warns about. A tag that
| must always mean "me" therefore cannot live in that namespace.
*/

it('keeps every channel tag clear of the event payload keys that overwrite tag data', function () {
    $mapper = app(TemplateDataMapperService::class);

    // Top-level keys an event payload can carry, as the overlay would spread them.
    $eventKeys = collect($mapper->getTagCategories()['event']['tags'])
        ->map(fn ($tag) => substr($tag, strlen('event.')))
        ->reject(fn ($key) => str_contains($key, '.'))
        ->values();

    $channelTags = collect(TemplateDataMapperService::tagCatalog())
        ->filter(fn ($spec) => $spec['category'] === 'channel')
        ->keys();

    expect($channelTags->intersect($eventKeys)->all())->toBe([]);
});

it('documents the user_* collision it is working around', function () {
    // If this ever comes back empty, the spread stopped clobbering user_* and
    // the warning shown on the template editor needs revisiting.
    $mapper = app(TemplateDataMapperService::class);

    $eventKeys = collect($mapper->getTagCategories()['event']['tags'])
        ->map(fn ($tag) => substr($tag, strlen('event.')))
        ->reject(fn ($key) => str_contains($key, '.'));

    $userTags = collect(TemplateDataMapperService::tagCatalog())
        ->filter(fn ($spec) => $spec['category'] === 'user')
        ->keys();

    expect($userTags->intersect($eventKeys)->sort()->values()->all())
        ->toBe(['user_avatar', 'user_id', 'user_login', 'user_name']);
});

it('resolves channel_avatar to the owner profile image, alongside user_avatar', function () {
    $payload = twitchPayload();
    $payload['channel']['avatar'] = $payload['user']['profile_image_url'];

    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates($payload, 'overlay');

    expect($mapped['channel_avatar'])->toBe('https://cdn.example/avatar.png')
        ->and($mapped['channel_avatar'])->toBe($mapped['user_avatar']);
});

it('offers channel_avatar to every account', function () {
    syncFor($this->user, twitchPayload(''));

    expect(tagNamesFor($this->user))->toContain('channel_avatar');
});
