<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\Finder\Finder;

uses(DatabaseTransactions::class);

/*
 * Isolation in this suite is opt-in: a test file declares DatabaseTransactions
 * (or RefreshDatabase) or its rows are committed for good. Omission is silent -
 * the file passes, and the leak only surfaces later as junk piling up in
 * whatever database the suite was pointed at.
 *
 * This guards the omission at authoring time. It is deliberately narrow: it
 * flags only files that persist Eloquent rows, so the ~22 pure-logic test files
 * that never touch a database stay transaction-free and fast.
 */

/**
 * Short class name => FQCN, from a file's `use` imports.
 *
 * @return array<string, class-string>
 */
function importedClasses(string $contents): array
{
    preg_match_all('/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?;/m', $contents, $matches, PREG_SET_ORDER);

    $imports = [];

    foreach ($matches as $match) {
        $fqcn = $match[1];
        $alias = $match[2] ?? substr((string) strrchr('\\'.$fqcn, '\\'), 1);
        $imports[$alias] = $fqcn;
    }

    return $imports;
}

/**
 * Eloquent models a test file persists rows for.
 *
 * Resolving each match against the file's imports is what keeps this honest:
 * `Request::create()` and `Http::fake()` look identical to a bare regex, so the
 * class has to actually be a Model before it counts as a write.
 *
 * @return list<string>
 */
function persistingWrites(string $contents): array
{
    $found = [];

    if (preg_match('/DB::table\([^)]*\)->(insert|updateOrInsert)\(/', $contents)) {
        $found[] = 'DB::table()->insert()';
    }

    $imports = importedClasses($contents);

    preg_match_all(
        '/(\w+)::(factory|create|forceCreate|firstOrCreate|updateOrCreate|insert)\(/',
        $contents,
        $matches,
        PREG_SET_ORDER
    );

    foreach ($matches as [$whole, $class, $method]) {
        $fqcn = $imports[$class] ?? null;

        if ($fqcn !== null && is_subclass_of($fqcn, Model::class)) {
            $found[] = $class.'::'.$method.'()';
        }
    }

    return array_values(array_unique($found));
}

test('every test file that persists rows declares an isolation trait', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(base_path('tests'))->name('*Test.php') as $file) {
        $contents = $file->getContents();

        if (preg_match('/uses\([^)]*(DatabaseTransactions|RefreshDatabase)/', $contents)) {
            continue;
        }

        if ($writes = persistingWrites($contents)) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            $offenders[] = $path.' ('.implode(', ', $writes).')';
        }
    }

    expect($offenders)->toBe([], 'These test files persist rows but never roll them back. '
        .'Add `uses(DatabaseTransactions::class);` to each: '.implode('; ', $offenders));
});

test('the write detector tells a model apart from a lookalike', function () {
    $modelWrite = <<<'PHP'
        <?php
        use App\Models\User;
        $u = User::factory()->create();
        PHP;

    $lookalike = <<<'PHP'
        <?php
        use Illuminate\Http\Request;
        $r = Request::create('/', 'POST');
        PHP;

    $aliased = <<<'PHP'
        <?php
        use App\Models\OverlayTemplate as Tpl;
        Tpl::firstOrCreate([]);
        PHP;

    expect(persistingWrites($modelWrite))->toBe(['User::factory()'])
        ->and(persistingWrites($lookalike))->toBe([])
        ->and(persistingWrites($aliased))->toBe(['Tpl::firstOrCreate()'])
        ->and(persistingWrites('<?php $x = 1;'))->toBe([]);
});
