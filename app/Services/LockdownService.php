<?php

namespace App\Services;

use App\Models\OverlayAccessToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class LockdownService
{
    private const CACHE_KEY = 'app.lockdown';

    public function isActive(): bool
    {
        return Cache::get(self::CACHE_KEY, ['active' => false])['active'] === true;
    }

    public function getStatus(): array
    {
        return Cache::get(self::CACHE_KEY, ['active' => false]);
    }

    public function activate(User $admin, Request $request, string $reason = ''): void
    {
        // 1. Deactivate all currently-active tokens and remember their IDs for restoration
        $suspendedIds = OverlayAccessToken::where('is_active', true)->pluck('id')->toArray();
        OverlayAccessToken::whereIn('id', $suspendedIds)->update(['is_active' => false]);

        // 2. Flush all non-admin sessions
        $adminIds = User::where('role', 'admin')->pluck('id')->toArray();
        DB::table('sessions')
            ->whereNotIn('user_id', $adminIds)
            ->delete();

        // 3. Set cache flag (forever — only cleared by deactivate())
        Cache::forever(self::CACHE_KEY, [
            'active' => true,
            'activated_at' => now()->toISOString(),
            'activated_by' => $admin->id,
            'activated_by_name' => $admin->name,
            'reason' => $reason,
            'suspended_token_ids' => $suspendedIds,
        ]);

        // 4. Audit log
        app(AdminAuditService::class)->log(
            $admin,
            'system.lockdown.activated',
            null,
            null,
            [
                'reason' => $reason,
                'tokens_suspended' => count($suspendedIds),
                'sessions_flushed' => true,
            ],
            $request
        );
    }

    public function deactivate(User $admin, Request $request): void
    {
        $status = $this->getStatus();

        // Restore tokens that were suspended during this lockdown
        if (! empty($status['suspended_token_ids'])) {
            OverlayAccessToken::whereIn('id', $status['suspended_token_ids'])
                ->update(['is_active' => true]);
        }

        Cache::forget(self::CACHE_KEY);

        app(AdminAuditService::class)->log(
            $admin,
            'system.lockdown.deactivated',
            null,
            null,
            [
                'tokens_restored' => count($status['suspended_token_ids'] ?? []),
                'was_activated_by' => $status['activated_by'] ?? null,
                'was_activated_at' => $status['activated_at'] ?? null,
            ],
            $request
        );
    }
}
