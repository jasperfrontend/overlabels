<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\UserDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function destroy(Request $request, UserDeletionService $deletion): RedirectResponse
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

        return redirect('/')->with('message', 'Your account and all data have been permanently deleted.');
    }
}
