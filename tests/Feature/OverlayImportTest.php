<?php

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\OverlayShareService;
use App\Support\OverlayMarkdown;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;

uses(DatabaseTransactions::class);

/**
 * `/overlay/{slug}/public.md` is the export and `POST /templates/import` reads
 * it back. The contract is the round trip: export a template, import the
 * export, and every field and every control comes back identical. The awkward
 * content in these fixtures (pipes, backticks, multi-line descriptions) is
 * deliberate - each one broke a naive reading of the markdown at some point.
 */
function importOwner(): User
{
    return User::factory()->create([
        'twitch_id' => (string) fake()->unique()->randomNumber(9),
    ]);
}

function importSource(User $owner, array $attrs = []): OverlayTemplate
{
    return OverlayTemplate::factory()->create(array_merge([
        'owner_id' => $owner->id,
        'fork_of_id' => null,
        'type' => 'static',
        'is_public' => true,
        'slug' => 'src-'.fake()->unique()->lexify('????????'),
        'name' => 'Math Engine: Showcase "v2"',
        'description' => "A BRB scene.\n\nEvery moving part is an Expression Control.",
        'head' => '<link href="https://fonts.googleapis.com/css2?family=Albert+Sans" rel="stylesheet">',
        'html' => "<div class=\"scene\">\n  <!-- fence in a comment: ``` -->\n  <b>[[[c:dice]]]</b> | [[[channel_name]]]\n</div>",
        'css' => ":root {\n  --dice: [[[c:dice]]];\n  --sa: [[[c:spiro_a|round:2]]]deg;\n}\n#grid { color: red; }",
        'metadata' => null,
    ], $attrs));
}

function importExport(OverlayTemplate $template): string
{
    return app(OverlayShareService::class)->markdown(
        $template,
        route('overlay.public', $template->slug).'.md',
    );
}

function importControls(OverlayTemplate $template, User $owner): void
{
    $defs = [
        ['key' => 'matrix', 'type' => 'number', 'label' => 'Matrix | size', 'value' => '160',
            'config' => ['min' => 0, 'max' => null, 'step' => 1, 'reset_value' => 160, 'random' => false, 'random_interval' => null]],
        ['key' => 'spiro_a', 'type' => 'expression', 'label' => 'Arm angle',
            'description' => "Degrees, 25 per second.\nWraps at 360.",
            'config' => ['expression' => 'mod(now_ms() / 40, 360)']],
        ['key' => 'dice', 'type' => 'expression', 'label' => 'Dice',
            'config' => ['expression' => 'c.matrix > 100 || c.matrix < 10 ? floor(c.matrix / 7) : 1']],
        ['key' => 'greeting', 'type' => 'text', 'label' => 'Greeting', 'value' => 'hi | `there`'],
        ['key' => 'hype', 'type' => 'counter', 'value' => '3',
            'config' => ['min' => 0, 'max' => null, 'step' => 2, 'reset_value' => 0, 'random' => false, 'random_interval' => null]],
        ['key' => 'muted', 'type' => 'boolean', 'value' => '1'],
    ];

    foreach ($defs as $i => $def) {
        OverlayControl::createForTemplate($template, $owner, $def + ['sort_order' => $i, 'value' => $def['value'] ?? null, 'config' => $def['config'] ?? null]);
    }
}

function importUpload(string $markdown): UploadedFile
{
    return UploadedFile::fake()->createWithContent('overlay.md', $markdown);
}

// ──────────────────────────────────────────────────────────────────────────────
// Round trip
// ──────────────────────────────────────────────────────────────────────────────

test('a static overlay survives export then import with every field and control intact', function () {
    $author = importOwner();
    $source = importSource($author);
    importControls($source, $author);

    $importer = importOwner();

    $response = $this->actingAs($importer)->post(route('templates.import'), [
        'file' => importUpload(importExport($source)),
    ]);

    $copy = OverlayTemplate::where('owner_id', $importer->id)->firstOrFail();

    $response->assertRedirect(route('templates.show', $copy));

    expect($copy->name)->toBe($source->name)
        ->and($copy->type)->toBe('static')
        ->and($copy->description)->toBe($source->description)
        ->and($copy->head)->toBe($source->head)
        ->and($copy->html)->toBe($source->html)
        ->and($copy->css)->toBe($source->css)
        ->and($copy->is_public)->toBeFalse()
        ->and($copy->slug)->not->toBe($source->slug)
        ->and($copy->template_tags)->toContain('c:dice', 'channel_name');

    $expected = $source->controls()->orderBy('sort_order')->get();
    $actual = $copy->controls()->orderBy('sort_order')->get();

    expect($actual)->toHaveCount($expected->count());

    foreach ($expected as $i => $control) {
        $imported = $actual[$i];

        expect($imported->key)->toBe($control->key)
            ->and($imported->type)->toBe($control->type)
            ->and($imported->label)->toBe($control->label)
            ->and($imported->source)->toBeNull();

        if ($control->type === 'expression') {
            expect($imported->config['expression'])->toBe($control->config['expression'])
                ->and($imported->config['dependencies'])->toBe(OverlayControl::extractExpressionDependencies($control->config['expression']));
        } else {
            expect($imported->value)->toBe($control->value)
                ->and($imported->config)->toBe($control->config);
        }
    }

    // Newlines cannot survive a one-line list item; the rest of the text does.
    expect($actual->firstWhere('key', 'spiro_a')->description)->toBe('Degrees, 25 per second. Wraps at 360.');
});

test('an alert overlay round-trips its sound, TTS, delay and chat message', function () {
    $author = importOwner();
    $source = importSource($author, [
        'type' => 'alert',
        'alert_sound_url' => 'https://images.overlabels.com/sounds/ding.mp3',
        'tts_message' => '[[[event.user_name]]] says `hi`',
        'tts_delay_ms' => 750,
        'chat_message' => 'Thanks [[[event.user_name]]]!',
    ]);

    $importer = importOwner();

    $this->actingAs($importer)->post(route('templates.import'), [
        'file' => importUpload(importExport($source)),
    ])->assertRedirect();

    $copy = OverlayTemplate::where('owner_id', $importer->id)->firstOrFail();

    expect($copy->type)->toBe('alert')
        ->and($copy->alert_sound_url)->toBe($source->alert_sound_url)
        ->and($copy->tts_message)->toBe($source->tts_message)
        ->and($copy->tts_delay_ms)->toBe(750)
        ->and($copy->chat_message)->toBe($source->chat_message);
});

test('the parser reads exactly what the emitter wrote for awkward values', function () {
    $author = importOwner();
    $source = importSource($author);
    importControls($source, $author);

    $doc = OverlayMarkdown::parse(importExport($source));

    $byKey = collect($doc['controls'])->keyBy('key');

    expect($doc['name'])->toBe('Math Engine: Showcase "v2"')
        ->and($byKey['greeting']['value'])->toBe('hi | `there`')
        ->and($byKey['matrix']['label'])->toBe('Matrix | size')
        ->and($byKey['dice']['config']['expression'])->toBe('c.matrix > 100 || c.matrix < 10 ? floor(c.matrix / 7) : 1')
        ->and($byKey['hype']['config'])->toBe(['min' => 0, 'max' => null, 'step' => 2, 'reset_value' => 0, 'random' => false, 'random_interval' => null])
        ->and($byKey['muted']['value'])->toBe('1');
});

// ──────────────────────────────────────────────────────────────────────────────
// Refusals
// ──────────────────────────────────────────────────────────────────────────────

test('a file that is not an overlay document is refused and nothing is created', function () {
    $importer = importOwner();

    $this->actingAs($importer)->from(route('templates.index'))->post(route('templates.import'), [
        'file' => importUpload("# Shopping list\n\n- eggs\n- milk\n"),
    ])->assertRedirect(route('templates.index'))->assertSessionHasErrors('file');

    expect(OverlayTemplate::where('owner_id', $importer->id)->exists())->toBeFalse();
});

test('an invalid control inside the document rolls the whole import back', function () {
    $author = importOwner();
    $source = importSource($author);
    importControls($source, $author);

    $markdown = str_replace('`[[[c:dice]]]` | expression', '`[[[c:dice]]]` | teleporter', importExport($source));

    $importer = importOwner();

    $this->actingAs($importer)->from(route('templates.index'))->post(route('templates.import'), [
        'file' => importUpload($markdown),
    ])->assertRedirect(route('templates.index'))->assertSessionHasErrors('file');

    expect(OverlayTemplate::where('owner_id', $importer->id)->exists())->toBeFalse()
        ->and(OverlayControl::where('user_id', $importer->id)->exists())->toBeFalse();
});

test('importing requires a login', function () {
    $this->post(route('templates.import'), ['file' => importUpload('---')])
        ->assertRedirect();
});

// ──────────────────────────────────────────────────────────────────────────────
// Exporting a private overlay
// ──────────────────────────────────────────────────────────────────────────────

test('the owner can fetch the .md of their own private overlay', function () {
    $author = importOwner();
    $source = importSource($author, ['is_public' => false]);

    $this->actingAs($author)
        ->get(route('overlay.public.markdown', $source->slug))
        ->assertOk()
        ->assertSee('name: '.app(OverlayShareService::class)->yamlScalar($source->name), false);
});

test('a private overlay .md is still 404 for everyone else', function () {
    $author = importOwner();
    $source = importSource($author, ['is_public' => false]);

    $this->get(route('overlay.public.markdown', $source->slug))->assertNotFound();
    $this->actingAs(importOwner())->get(route('overlay.public.markdown', $source->slug))->assertNotFound();
});
