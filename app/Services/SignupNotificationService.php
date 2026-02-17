<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SignupNotificationService
{
    public function sendSignupNotification(User $user): bool
    {
        try {
            $notificationEmail = config('mail.signup_notification_email');

            if (! $notificationEmail) {
                Log::warning('Signup notification email not configured');

                return false;
            }

            Mail::raw($this->buildEmailContent($user), function ($message) use ($notificationEmail) {
                $message->to($notificationEmail)
                    ->subject('New User Signup - '.config('app.name'))
                    ->from(config('mail.from.address'), config('app.name'));
            });

            Log::info('Signup notification sent', [
                'user_id' => $user->id,
                'twitch_id' => $user->twitch_id,
                'username' => $user->name,
                'notification_email' => $notificationEmail,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to send signup notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    private function buildEmailContent(User $user): string
    {
        return sprintf(
            "New user signup notification\n\n".
            "User Details:\n".
            "- Name: %s\n".
            "- Email: %s\n".
            "- Twitch ID: %s\n".
            "- Signup Time: %s\n".
            "- Profile URL: https://twitch.tv/%s\n\n".
            'This notification was sent from %s',
            $user->name ?: 'N/A',
            $user->email ?: 'N/A',
            $user->twitch_id,
            $user->created_at->format('Y-m-d H:i:s T'),
            $user->name ?: $user->twitch_id,
            config('app.url')
        );
    }
}
