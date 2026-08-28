<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\UserDeletionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AccountController extends Controller
{
    // Inertia::location, not redirect(). Same reason as logout in
    // AuthenticatedSessionController::destroy: the target is '/', a plain Blade
    // view with no X-Inertia header. A 302/303 gets followed by the XHR itself,
    // which then holds a full HTML document Inertia cannot parse and shows it in
    // the error modal. 409 + X-Inertia-Location makes the client do a real
    // navigation instead, which is what a session teardown wants anyway.
    public function destroy(Request $request, UserDeletionService $deletion): Response
    {
        $request->validate([
            'confirmation' => 'required|string',
        ]);

        if ($request->input('confirmation') !== 'DELETE ACCOUNT') {
            throw ValidationException::withMessages([
                'confirmation' => 'You must type DELETE ACCOUNT exactly to confirm.',
            ]);
        }

        $user = $request->user();

        if ($user->isGhostUser()) {
            throw ValidationException::withMessages([
                'confirmation' => 'The system user cannot be deleted.',
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $deletion->eraseAccount($user);

        return Inertia::location(route('home'));
    }
}
