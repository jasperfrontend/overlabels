<?php

use App\Jobs\SetupUserEventSubSubscriptions;
use App\Models\User;
use App\Models\UserEventsubSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

/**
 * `GET /api/eventsub-health-check` was an unauthenticated route that, besides
 * reporting stats, dispatched SetupUserEventSubSubscriptions for every failed
 * subscription and every unconnected auto-connect user - a mutation behind a
 * bare GET, exempt from the ban middleware. Nothing called it (a week of
 * proxy logs, zero hits) and the hourly `eventsub:monitor --fix` does the
 * same work from the scheduler. Removed 2026-08-26.
 */
test('the unauthenticated eventsub health check route no longer exists', function () {
    Bus::fake();

    $user = User::factory()->create(['eventsub_auto_connect' => true, 'eventsub_connected_at' => null]);
    UserEventsubSubscription::factory()->for($user)->create(['status' => 'authorization_revoked']);

    $this->getJson('/api/eventsub-health-check')->assertNotFound();

    Bus::assertNotDispatched(SetupUserEventSubSubscriptions::class);
});
