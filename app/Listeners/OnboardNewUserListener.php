<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Jobs\OnboardNewUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class OnboardNewUserListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(UserRegistered $event): void
    {
        // 5s delay so SetupUserEventSubSubscriptions (dispatched from OAuth callback) gets a head start
        OnboardNewUser::dispatch($event->user)->delay(now()->addSeconds(5));
    }
}
