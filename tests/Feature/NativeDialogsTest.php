<?php

use Symfony\Component\Finder\Finder;

/*
 * Native window.confirm() / window.alert() are banned in the frontend.
 *
 * Two reasons, and the second is the one that bites silently. They are
 * unstyled browser chrome that ignores the app's theme entirely - and they
 * block the renderer, which freezes browser automation until a human clicks
 * the dialog by hand. Both are replaced by useConfirm() + ConfirmDialog.
 *
 * The replacement is promise-based, so a call site that forgets `await`
 * gets a Promise back - always truthy - and the guarded action fires with
 * no confirmation at all. TypeScript cannot catch that: `!somePromise` is
 * perfectly valid. This test is the only thing standing in front of it.
 */

/** @return array<string, string> relative path => contents */
function frontendSources(): array
{
    $files = Finder::create()
        ->files()
        ->in(resource_path('js'))
        ->name(['*.vue', '*.ts']);

    $sources = [];
    foreach ($files as $file) {
        $sources[str_replace('\\', '/', $file->getRelativePathname())] = $file->getContents();
    }

    return $sources;
}

/**
 * Call sites of confirm()/alert(), excluding declarations, comments, and
 * property access like `foo.confirm(`.
 *
 * @return list<string> "path:line  code"
 */
function dialogCallSites(string $path, string $contents, bool $awaitedOnly): array
{
    $hits = [];

    foreach (explode("\n", $contents) as $i => $line) {
        $trimmed = ltrim($line);

        // Comments and doc blocks describe these calls constantly.
        if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
            continue;
        }

        // Declarations of our own replacements, not uses of the native ones.
        if (preg_match('/function\s+(confirm|alert)\s*\(/', $line)) {
            continue;
        }

        // No space before the paren: prose in the template layer says things
        // like "Every alert (follows, subs, raids) plays inside this overlay",
        // which is not a call and must not be flagged.
        if (! preg_match('/(^|[^.\w])(window\.)?(confirm|alert)\(/', $line)) {
            continue;
        }

        $isAwaited = (bool) preg_match('/await\s+(confirm|alert)\(/', $line);

        if ($awaitedOnly ? ! $isAwaited : true) {
            $hits[] = $path.':'.($i + 1).'  '.trim($line);
        }
    }

    return $hits;
}

it('never calls native confirm() or alert() in the frontend', function () {
    $offenders = [];

    foreach (frontendSources() as $path => $contents) {
        foreach (dialogCallSites($path, $contents, awaitedOnly: true) as $hit) {
            $offenders[] = $hit;
        }
    }

    expect($offenders)->toBe([], sprintf(
        "Native dialog call, or a useConfirm() call missing its `await`:\n%s\n\n".
        "Use `const { confirm, alert } = useConfirm()` and await the result.",
        implode("\n", $offenders)
    ));
});

it('mounts exactly one ConfirmDialog in the app layout', function () {
    $layout = file_get_contents(resource_path('js/layouts/AppLayout.vue'));

    // The dialog's state is a module-level singleton. If nothing renders the
    // component on a page, every confirm() on it resolves to a dialog nobody
    // can see and the awaiting caller hangs forever.
    expect(substr_count($layout, '<ConfirmDialog />'))->toBe(1);
});

it('mounts ConfirmDialog on pages that confirm outside the app layout', function () {
    $missing = [];

    foreach (frontendSources() as $path => $contents) {
        if (! str_starts_with($path, 'pages/')) {
            continue;
        }
        if (! preg_match('/await\s+(confirm|alert)\(/', $contents)) {
            continue;
        }
        if (str_contains($contents, 'AppLayout')) {
            continue;
        }
        if (str_contains($contents, '<ConfirmDialog />')) {
            continue;
        }

        $missing[] = $path;
    }

    expect($missing)->toBe([], sprintf(
        "These pages confirm but render outside AppLayout, so no dialog is mounted:\n%s",
        implode("\n", $missing)
    ));
});
