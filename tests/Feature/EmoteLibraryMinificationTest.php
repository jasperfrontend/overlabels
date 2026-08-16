<?php

/*
 * The emote library's abstract base class guards itself with:
 *
 *     if (new.target.name === Emote.name) throw new Error('Base Emote class cannot be used');
 *
 * That compares CLASS NAMES at runtime. Minification renamed the base class and
 * all four subclasses to `e`, which made the guard true for every subclass, so
 * constructing any BTTV/FFZ/7TV emote threw.
 *
 * It broke in production only - dev is not minified - and it was completely
 * silent, because useEmoteParser wrapped the fetches in Promise.allSettled. The
 * visible symptom was third-party emotes rendering as plain text on prod while
 * Twitch emotes worked, since those come from IRC tag positions and never
 * construct a library object.
 *
 * `keepNames: true` in vite.config.mts is the fix. This test is the thing that
 * notices if it is ever removed, because nothing else would: every unit test
 * runs against unminified source, so the whole suite stays green while
 * production is broken.
 *
 * CI runs `npm run build` before Pest, so this has a real build to inspect.
 */

function emoteChunkSource(): ?string
{
    $manifestPath = public_path('build/manifest.json');

    if (! file_exists($manifestPath)) {
        return null;
    }

    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];

    foreach ($manifest as $key => $entry) {
        if (! str_contains($key, 'twitch-emoticons')) {
            continue;
        }

        $chunk = public_path('build/'.$entry['file']);

        return file_exists($chunk) ? file_get_contents($chunk) : null;
    }

    return null;
}

beforeEach(function () {
    $this->source = emoteChunkSource();

    if ($this->source === null) {
        $this->markTestSkipped('No production build present. Run `npm run build`.');
    }
});

it('keeps the emote base class name through minification', function () {
    // The guard compares against `Emote.name`. If the class were minified the
    // literal would be a single letter and this string would not appear.
    expect($this->source)->toContain('class Emote');
});

it('keeps every emote subclass name distinct from the base', function () {
    // These four are what actually broke: all of them collapsed to `e`, the
    // same name the base class got, so the guard fired on every construction.
    //
    // One needle per assertion: toContain() is variadic, so passing a second
    // argument as a "message" silently asserts it as another needle.
    foreach (['BTTVEmote', 'FFZEmote', 'SevenTVEmote', 'TwitchEmote'] as $subclass) {
        expect(str_contains($this->source, "class $subclass"))
            ->toBeTrue("$subclass lost its name to minification");
    }
});

it('still contains the guard it is protecting', function () {
    // If the library ever drops the abstract guard, this whole test file is
    // obsolete and keepNames may be reconsidered on its own merits. Failing
    // here is the signal to go and check, not a bug in itself.
    expect($this->source)->toContain('new.target.name');
});
