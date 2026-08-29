<?php

/**
 * The app name is appended to every page title in ONE place, the `title`
 * callback passed to createInertiaApp in resources/js/app.ts. A page that also
 * writes it into its own <title> gets it twice, and the browser tab reads
 * "Bot commands - Overlabels • Overlabels".
 *
 * That happened on 8 of the page components before anyone noticed, because it
 * only shows in the tab: the server-rendered <title> that crawlers read is
 * built separately and was always correct. Nothing else would catch it, so
 * this does.
 */

/** @return array<int, string> */
function vueTitleSources(): array
{
    $roots = [resource_path('js/pages'), resource_path('js/layouts')];
    $files = [];

    foreach ($roots as $root) {
        if (! is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'vue') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

test('no page appends the app name to its own title', function () {
    // The app name used as a SUFFIX after a separator, which is the bug. Two
    // patterns rather than one, because the attribute form has to be anchored
    // to `title="` specifically: a looser match also catches
    // `<meta name="description" content="Recent stream events - Overlabels">`,
    // which is a description ending in the app name and is not this bug.
    // A title that legitimately contains the word, like "Overlabels for
    // designers", is left alone either way.
    $separator = '[-\x{2013}\x{2014}\x{2022}|]';
    $patterns = [
        '/'.$separator.'\s*Overlabels\s*<\/title>/u',
        '/\btitle="[^"]*'.$separator.'\s*Overlabels"/u',
    ];

    $offenders = [];

    foreach (vueTitleSources() as $path) {
        $contents = (string) file_get_contents($path);
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents)) {
                $offenders[] = str_replace('\\', '/', str_replace(resource_path('js'), '', $path));
                break;
            }
        }
    }

    expect($offenders)->toBe([], 'These pages append the app name themselves, so it renders twice: '
        .implode(', ', $offenders));
});

test('the global title template still supplies the app name', function () {
    // The test above is only safe while something else is adding the suffix.
    // If this callback ever goes, every page title becomes bare instead.
    $appTs = (string) file_get_contents(resource_path('js/app.ts'));

    expect($appTs)->toMatch('/title:\s*\(title\)\s*=>/')
        ->and($appTs)->toContain('appName');
});

test('the app name falls back to this project rather than the framework', function () {
    // VITE_APP_NAME is set in every real environment, so this fallback only
    // shows up when it is not - a fresh clone, or a build where the variable
    // did not make it through. Inheriting the starter kit's default put
    // "Laravel" in the browser tab of every page in that case.
    $appTs = (string) file_get_contents(resource_path('js/app.ts'));

    expect($appTs)->toContain("VITE_APP_NAME || 'Overlabels'")
        ->and($appTs)->not->toContain("'Laravel'");
});
