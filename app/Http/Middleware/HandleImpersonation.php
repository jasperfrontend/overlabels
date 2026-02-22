<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        $impersonatingId = $request->session()->get('impersonating_user_id');
        $realAdminId = $request->session()->get('real_admin_id');

        if ($impersonatingId && $realAdminId) {
            $realAdmin = User::find($realAdminId);

            if ($realAdmin && $realAdmin->isAdmin()) {
                $targetUser = User::find($impersonatingId);

                if ($targetUser) {
                    Auth::setUser($targetUser);
                }
            }
        }

        return $next($request);
    }
}
