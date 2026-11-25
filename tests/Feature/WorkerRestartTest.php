<?php

use Symfony\Component\Yaml\Yaml;

/**
 * A queue worker that exits after a few jobs is restarted by Docker, and
 * Docker resets its restart backoff only for a container that ran at least
 * 10 seconds. A worker that gets through its --max-jobs faster than that is
 * treated as crash-looping: the delay doubles on every exit, up to a minute.
 * In a 55,000-follow flood the `queue` worker at --max-jobs=1000 restarted 41
 * times and stood idle for most of the last minutes, while every streamer's
 * broadcasts waited behind it (#343). Nothing logs this; the queue just stops.
 */
test('no queue worker in config/deploy.yml restarts after only a few thousand jobs', function () {
    $workers = 0;

    foreach (Yaml::parseFile(base_path('config/deploy.yml'))['servers'] as $role => $config) {
        $cmd = is_array($config) ? ($config['cmd'] ?? '') : '';
        if (! str_contains($cmd, 'queue:work')) {
            continue;
        }
        $workers++;

        preg_match('/--max-jobs=(\d+)/', $cmd, $m);
        expect((int) ($m[1] ?? 0))->toBeGreaterThanOrEqual(20000, "{$role} restarts too often to outrun Docker's backoff");
    }

    expect($workers)->toBeGreaterThan(0);
});
