<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Services\SignupNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendSignupNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private SignupNotificationService $signupNotificationService;

    /**
     * Create the event listener.
     */
    public function __construct(SignupNotificationService $signupNotificationService)
    {
        $this->signupNotificationService = $signupNotificationService;
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        $this->signupNotificationService->sendSignupNotification($event->user);
    }
}
