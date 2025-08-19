<?php

namespace App\Http\Middleware;

use App\Models\StorageAccount;
use App\Services\Storage\StorageServiceFactory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class HandleStorageTokenRefresh
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->refreshExpiredTokens();
        }

        return $next($request);
    }

    /**
     * Refresh expired tokens for the authenticated user
     */
    private function refreshExpiredTokens(): void
    {
        $expiredAccounts = Auth::user()->storageAccounts()
            ->where('is_active', true)
            ->get()
            ->filter(function ($account) {
                return $account->needsTokenRefresh();
            });

        foreach ($expiredAccounts as $account) {
            try {
                $service = StorageServiceFactory::create($account);
                $service->refreshToken();
                
                Log::info('Storage token refreshed successfully', [
                    'user_id' => $account->user_id,
                    'provider' => $account->provider,
                    'account_id' => $account->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to refresh storage token', [
                    'user_id' => $account->user_id,
                    'provider' => $account->provider,
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
                
                // Deactivate account if refresh fails
                $account->update(['is_active' => false]);
            }
        }
    }
}