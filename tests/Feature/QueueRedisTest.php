<?php

use Symfony\Component\Yaml\Yaml;

/**
 * The job queue runs on its own Redis (#343), and every way of getting that
 * wrong is silent: point the queue at the cache Redis, or give the queue
 * Redis an eviction policy or no persistence, and jobs disappear under
 * memory pressure or on a restart with no error anywhere - an alert that
 * never fires. So config/deploy.yml is held to it here.
 */
function deployConfig(): array
{
    return Yaml::parseFile(base_path('config/deploy.yml'));
}

test('the queue Redis never evicts and keeps its jobs across a restart', function () {
    $cmd = deployConfig()['accessories']['redis-queue']['cmd'];

    expect($cmd)->toContain('--maxmemory-policy noeviction')
        ->and($cmd)->toContain('--appendonly yes')
        ->and(deployConfig()['accessories']['redis-queue']['directories'] ?? [])->not->toBeEmpty();
});

test('the queue connection points at the queue Redis, not the cache Redis', function () {
    $env = deployConfig()['env']['clear'];

    expect(config('queue.connections.redis.connection'))->toBe('queue')
        ->and($env['REDIS_QUEUE_HOST'])->toBe('overlabels-redis-queue')
        ->and($env['REDIS_QUEUE_HOST'])->not->toBe($env['REDIS_HOST']);
});

test('the deploy boots the queue Redis', function () {
    expect(file_get_contents(base_path('.github/workflows/deploy.yml')))
        ->toContain('kamal accessory boot redis-queue');
});
