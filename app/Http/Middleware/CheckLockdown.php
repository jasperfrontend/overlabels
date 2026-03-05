<?php

namespace App\Http\Middleware;

use App\Services\LockdownService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLockdown
{
    public function __construct(private readonly LockdownService $lockdown) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->lockdown->isActive()) {
            return response()->json(['message' => 'System under maintenance.'], 503);
        }

        return $next($request);
    }
}
