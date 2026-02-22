<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        if ($user->isGhostUser()) {
            return back()->withErrors(['impersonate' => 'Cannot impersonate the ghost user.']);
        }

        if ($user->id === $admin->id) {
            return back()->withErrors(['impersonate' => 'Cannot impersonate yourself.']);
        }

        $request->session()->put('real_admin_id', $admin->id);
        $request->session()->put('impersonating_user_id', $user->id);

        $this->audit->log($admin, 'impersonation.started', 'User', $user->id, [
            'target_name' => $user->name,
            'target_twitch_id' => $user->twitch_id,
        ], $request);

        return redirect()->route('dashboard.index');
    }

    public function stop(Request $request): RedirectResponse
    {
        $realAdminId = $request->session()->get('real_admin_id');
        $targetUserId = $request->session()->get('impersonating_user_id');

        $request->session()->forget(['real_admin_id', 'impersonating_user_id']);

        if ($realAdminId) {
            $admin = User::find($realAdminId);

            if ($admin && $targetUserId) {
                $this->audit->log($admin, 'impersonation.stopped', 'User', $targetUserId, [], $request);
            }
        }

        return redirect()->route('admin.dashboard');
    }
}
