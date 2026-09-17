<?php

use Illuminate\Support\Facades\File;

// `OverlayControl::tagIdentifier()` is the one place the tag key rule lives:
// broadcastKey() for a service-managed control, key otherwise. A call site
// that retypes the ternary inline is one more place to get it wrong (the
// OL-2609-060 audit found two after the claim said none remained), so this
// scans app/ for the shape and allows it only in the method itself.
test('no call site outside OverlayControl::tagIdentifier computes the tag key inline', function () {
    $pattern = '/source_managed\s*\?\s*\$\w+->broadcastKey\(\)\s*:\s*\$\w+->key\b/s';

    $offenders = [];

    foreach (File::allFiles(app_path()) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $relative = str_replace('\\', '/', $file->getRelativePathname());
        $matches = preg_match_all($pattern, $file->getContents());

        if ($relative === 'Models/OverlayControl.php') {
            expect($matches)->toBe(1, 'tagIdentifier() itself should hold exactly one inline computation');

            continue;
        }

        if ($matches > 0) {
            $offenders[] = $relative;
        }
    }

    expect($offenders)->toBe([]);
});
