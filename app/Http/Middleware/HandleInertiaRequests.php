<?php

namespace App\Http\Middleware;

use App\Models\StreamState;
use App\Models\User;
use App\Services\LockdownService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),

            'auth' => [
                'user' => $request->user() ? $request->user()->only([
                    'id',
                    'name',
                    'email',
                    'twitch_id',
                    'avatar',
                    'icon',
                    'onboarded_at',
                    'role',
                    'locale',
                ]) : null,
            ],
            'ziggy' => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'message' => fn () => $request->session()->get('message'),
                'type' => fn () => $request->session()->get('type'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'isAdmin' => fn () => $request->user()?->isAdmin() ?? false,
            'lockdown' => fn () => app(LockdownService::class)->getStatus(),
            'streamState' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return null;
                }

                $state = StreamState::where('user_id', $user->id)->with('currentSession')->first();
                if (! $state) {
                    return ['state' => 'offline', 'confidence' => 0, 'startedAt' => null];
                }

                return [
                    'state' => $state->state,
                    'confidence' => $state->confidence,
                    'startedAt' => $state->isConfidentlyLive() && $state->currentSession
                        ? $state->currentSession->started_at->toISOString()
                        : null,
                ];
            },
            'impersonating' => function () use ($request) {
                $targetId = $request->session()->get('impersonating_user_id');
                if (! $targetId) {
                    return null;
                }
                $target = User::find($targetId);

                return [
                    'real_admin_id' => $request->session()->get('real_admin_id'),
                    'target_user_id' => $targetId,
                    'target_name' => $target?->name,
                ];
            },
        ];
    }
}
