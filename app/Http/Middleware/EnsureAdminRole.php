<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isAdmin()) {
            return $next($request);
        }

        // During impersonation, HandleImpersonation has already swapped auth to the target
        // user. Allow access if the real admin (stored in session) is an admin.
        $realAdminId = $request->session()->get('real_admin_id');
        if ($realAdminId) {
            $realAdmin = User::find($realAdminId);
            if ($realAdmin?->isAdmin()) {
                return $next($request);
            }
        }

        abort(404);
    }
}
