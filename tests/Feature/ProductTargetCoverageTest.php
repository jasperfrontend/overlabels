<?php

use App\Services\External\ExternalServiceRegistry;
use App\Support\ProductSetup;
use Illuminate\Support\Facades\File;

/**
 * A product setup step points at one control by name, and the page carrying
 * that control marks itself `data-product-target="<key>"`. Nothing connects the
 * two ends but the string.
 *
 * When they disagree nothing breaks and nothing complains: useProductFocus()
 * looks for an element, does not find one, and gives up after about a second.
 * The banner still names the step and still links to the page, so the only
 * symptom is that the thing you were sent for does not light up - which nobody
 * would file a bug about and nobody would notice in review. That is exactly the
 * kind of silent rot the help-context tests exist for, and this is the same
 * shape of guard for the other direction.
 */
function productTargetSources(): string
{
    return collect(File::allFiles(resource_path('js')))
        ->filter(fn ($file) => in_array($file->getExtension(), ['vue', 'ts'], true))
        ->map(fn ($file) => $file->getContents())
        ->implode("\n");
}

it('has a marked element for every fixed step target', function () {
    $sources = productTargetSources();

    $missing = [];
    foreach (ProductSetup::STEPS as $key => $step) {
        $target = $step['target'] ?? null;
        if ($target === null) {
            continue;
        }

        if (! str_contains($sources, 'data-product-target="'.$target.'"')) {
            $missing[] = "{$key} points at '{$target}'";
        }
    }

    expect($missing)->toBe([], sprintf(
        "Step targets with no element carrying them: %s\nMark the control with data-product-target, or drop the target from ProductSetup::STEPS.",
        implode('; ', $missing),
    ));
});

it('has a marked element for the per-service integration targets', function () {
    // ProductSetup::targetFor() emits `integration-<service>` for whichever
    // declared service is still unfinished, so the key is one of a closed set
    // rather than a fixed string. The integrations list binds it across its
    // rows, which a literal search cannot see - so the binding itself is what
    // is asserted here.
    $bound = collect(File::allFiles(resource_path('js')))
        ->filter(fn ($file) => $file->getExtension() === 'vue')
        ->contains(function ($file) {
            foreach (preg_split('/\R/', $file->getContents()) as $line) {
                if (str_contains($line, 'data-product-target') && str_contains($line, 'integration-')) {
                    return true;
                }
            }

            return false;
        });

    expect($bound)->toBeTrue(
        'Nothing binds data-product-target to an integration- key, so the integration step points at an element that does not exist.'
    );

    expect(ExternalServiceRegistry::services())->not->toBeEmpty();
});

it('marks no element with a target the backend never emits', function () {
    // The other direction: an attribute left behind after a step was renamed or
    // removed glows for nobody and reads as wiring that still exists.
    $known = collect(ProductSetup::STEPS)
        ->pluck('target')
        ->filter()
        ->all();

    preg_match_all('/data-product-target="([a-z0-9_-]+)"/', productTargetSources(), $matches);

    $orphans = array_values(array_unique(array_filter(
        $matches[1],
        fn (string $target) => ! in_array($target, $known, true),
    )));

    expect($orphans)->toBe([], 'Elements marked with a target no step emits: '.implode(', ', $orphans));
});
