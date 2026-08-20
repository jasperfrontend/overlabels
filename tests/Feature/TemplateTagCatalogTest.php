<?php

use App\Models\User;
use App\Services\TemplateDataMapperService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            'avatar' => 'https://cdn.example/avatar.png',
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

function browserTagNames(array $browser): array
{
    $names = [];

    foreach ($browser as $category) {
        foreach ($category['tags'] as $tag) {
            $names[] = $tag['tag_name'];
        }
    }

    sort($names);

    return $names;
}

function browserTags(array $browser): Collection
{
    return collect($browser)->flatMap(fn ($c) => $c['tags'])->keyBy('tag_name');
}

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
| Tags are a constant, not per-user rows
|--------------------------------------------------------------------------
|
| The tables that stored a copy per user are gone. Production held 1155 rows
| for 82 distinct names across 19 accounts, none of them ever edited. The
| browser is built from TAG_CATALOG on each request.
*/

it('offers the same tags no matter whose data is passed in', function () {
    $mapper = app(TemplateDataMapperService::class);

    $affiliate = $mapper->tagBrowser(twitchPayload('affiliate'));
    $plebeian = $mapper->tagBrowser(twitchPayload(''));
    $nothing = $mapper->tagBrowser([]);

    expect(browserTagNames($plebeian))->toBe(browserTagNames($affiliate))
        ->and(browserTagNames($nothing))->toBe(browserTagNames($affiliate));
});

it('covers the whole catalogue and nothing else', function () {
    $browser = app(TemplateDataMapperService::class)->tagBrowser(twitchPayload());

    $expected = array_keys(TemplateDataMapperService::tagCatalog());
    sort($expected);

    expect(browserTagNames($browser))->toBe($expected);
});

it('leaves the event category out of the browser', function () {
    // event.* tags exist only inside an alert render, so there is no value to
    // show beside them.
    $browser = app(TemplateDataMapperService::class)->tagBrowser(twitchPayload());

    expect($browser)->not->toHaveKey('event');
});

it('has no tables left to store tags in', function () {
    $tables = collect(DB::select('select tablename from pg_tables where schemaname = current_schema()'))
        ->pluck('tablename');

    expect($tables)->not->toContain('template_tags')
        ->and($tables)->not->toContain('template_tag_categories')
        ->and($tables)->not->toContain('template_tag_jobs')
        ->and($tables)->not->toContain('user_templates');
});

/*
|--------------------------------------------------------------------------
| Live values
|--------------------------------------------------------------------------
*/

it('shows the value a tag actually renders, not the raw payload value', function () {
    $tags = browserTags(app(TemplateDataMapperService::class)->tagBrowser(twitchPayload()));

    expect($tags['channel_tags']['sample_data'])->toBe('Gaming, Fun')
        ->and($tags['followers_total']['sample_data'])->toBe(1234)
        ->and($tags['followers_latest_date']['sample_data'])->toBeInt();
});

it('falls back to the catalogue sample when the account has no value at that path', function () {
    $tags = browserTags(app(TemplateDataMapperService::class)->tagBrowser(twitchPayload()));

    // The account has two channel tags, so index 7 resolves to nothing.
    expect($tags['channel_tags_7']['sample_data'])->toBe('')
        ->and($tags['channel_tags_7']['is_live'])->toBeFalse()
        ->and($tags['channel_tags_0']['is_live'])->toBeTrue();
});

it('marks nothing live when Twitch data is unavailable', function () {
    $tags = browserTags(app(TemplateDataMapperService::class)->tagBrowser([]));

    expect($tags->filter(fn ($t) => $t['is_live']))->toBeEmpty();
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

it('still renders a tag the account has no data for as empty', function () {
    // This is why no gate is needed: a non-affiliate's subscriber tags resolve,
    // they just resolve to nothing, which renders as nothing.
    $mapped = app(TemplateDataMapperService::class)
        ->mapForTemplate(twitchPayload(''), 'overlay', ['subscribers_latest_user_name']);

    expect($mapped)->toHaveKey('subscribers_latest_user_name')
        ->and($mapped['subscribers_latest_user_name'])->toBe('');
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
| must always mean "me" cannot live in that namespace.
*/

it('keeps every channel tag clear of the event payload keys that overwrite tag data', function () {
    $mapper = app(TemplateDataMapperService::class);

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
    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates(twitchPayload(), 'overlay');

    expect($mapped['channel_avatar'])->toBe('https://cdn.example/avatar.png')
        ->and($mapped['channel_avatar'])->toBe($mapped['user_avatar']);
});

/*
|--------------------------------------------------------------------------
| The page and the editor's list
|--------------------------------------------------------------------------
*/

it('serves the tag browser to a connected user', function () {
    $user = User::factory()->create(['access_token' => 'token', 'twitch_id' => '73327367']);

    $this->actingAs($user)
        ->get('/tags')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('TemplateTagGenerator')
            ->has('tags.channel.tags')
            ->has('liveValues')
        );
});

it('refuses the tag browser to a user with no Twitch connection', function () {
    $user = User::factory()->create(['access_token' => null]);

    $this->actingAs($user)->get('/tags')->assertForbidden();
});

it('serves the same catalogue as JSON for the template editor', function () {
    $user = User::factory()->create(['access_token' => 'token', 'twitch_id' => '73327367']);

    $response = $this->actingAs($user)->getJson('/api/template-tags')->assertOk();

    $tags = $response->json('tags');

    expect($response->json('success'))->toBeTrue()
        ->and(browserTagNames($tags))->toBe(browserTagNames(
            app(TemplateDataMapperService::class)->tagBrowser([])
        ));
});

/*
|--------------------------------------------------------------------------
| List-valued tags join instead of JSON-encoding
|--------------------------------------------------------------------------
|
| channel_content_labels used to render the literal `[]` into an overlay, which
| is what every account without content classification labels set would see.
*/

it('renders channel_content_labels as nothing when the account has no labels', function () {
    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates(twitchPayload(), 'overlay');

    expect($mapped['channel_content_labels'])->toBe('');
});

it('joins channel_content_labels with commas when the account has labels', function () {
    $payload = twitchPayload();
    $payload['channel']['content_classification_labels'] = ['Gambling', 'DrugsIntoxication'];

    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates($payload, 'overlay');

    expect($mapped['channel_content_labels'])->toBe('Gambling, DrugsIntoxication');
});

it('does not print the word Array if Twitch ever returns label objects', function () {
    // GET /channels documents plain strings, but the PATCH body for the same
    // field uses {id, is_enabled} objects. A bare implode() on that shape would
    // raise "Array to string conversion" and put "Array" on someone's stream.
    $payload = twitchPayload();
    $payload['channel']['content_classification_labels'] = [
        ['id' => 'Gambling', 'is_enabled' => true],
    ];

    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates($payload, 'overlay');

    expect($mapped['channel_content_labels'])->toBe('')
        ->and($mapped['channel_content_labels'])->not->toContain('Array');
});

it('leaves channel_tags joining exactly as it was', function () {
    $payload = twitchPayload();
    $payload['channel']['tags'] = ['Gaming', 'Fun', 'Community'];

    $mapped = app(TemplateDataMapperService::class)->mapTwitchDataForTemplates($payload, 'overlay');

    expect($mapped['channel_tags'])->toBe('Gaming, Fun, Community');
});
