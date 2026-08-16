<?php

/*
 * Development-only code must not reach a production build.
 *
 * The chat hose synthesizes thousands of fake chat messages a second. It is
 * gated on `import.meta.env.VITE_CHAT_HOSE === '1'`, which Vite inlines at
 * BUILD time so an ordinary build dead-code-eliminates the import site and the
 * module never enters the graph.
 *
 * That is a compile-time guarantee rather than a runtime check, which is the
 * right shape - there is no flag to leave switched on by accident. But it is
 * only a guarantee while the gate is written in a form the bundler can fold.
 * Changing it to a runtime condition, or importing the module statically,
 * would silently ship the whole thing.
 *
 * So this inspects the BUILT output. No unit test can cover it: the guard's
 * entire job happens during bundling. CI runs `npm run build` (without the
 * flag) before Pest, so this has a real artifact to look at.
 */

function builtAssetSources(): array
{
    $dir = public_path('build/assets');

    if (! is_dir($dir)) {
        return [];
    }

    $sources = [];
    foreach (glob($dir.'/*.js') as $file) {
        $sources[basename($file)] = file_get_contents($file);
    }

    return $sources;
}

beforeEach(function () {
    $this->assets = builtAssetSources();

    if ($this->assets === []) {
        $this->markTestSkipped('No production build present. Run `npm run build`.');
    }
});

it('ships no chat hose in a production build', function () {
    // If this fails, check that the VITE_CHAT_HOSE build ran last and rebuild
    // without it before assuming a real regression.
    foreach ($this->assets as $name => $source) {
        expect(str_contains($source, '__olChatHose'))
            ->toBeFalse("$name contains the chat hose; the VITE_CHAT_HOSE gate is not eliminating it");
    }
});

it('emits no chunk named after a dev module', function () {
    foreach (array_keys($this->assets) as $name) {
        expect($name)->not->toContain('chatHose');
    }
});

it('keeps the build-time gate in a form the bundler can fold', function () {
    // A runtime check (a query param, a window flag, a server-sent boolean)
    // would compile in and defeat the whole arrangement. The literal comparison
    // against import.meta.env is what makes elimination possible.
    $renderer = file_get_contents(resource_path('js/components/OverlayRenderer.vue'));

    expect($renderer)->toContain("import.meta.env.VITE_CHAT_HOSE === '1'");
});

it('never imports the dev module statically', function () {
    // A top-level import pulls the module into the graph regardless of any
    // guard around its use.
    foreach (glob(resource_path('js/**/*.{ts,vue}'), GLOB_BRACE) as $file) {
        $source = file_get_contents($file);

        expect(preg_match("/^import .*dev\/chatHose/m", $source))
            ->toBe(0, basename($file).' imports the chat hose statically; use a dynamic import inside the build-time guard');
    }
});
