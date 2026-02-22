<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Kit;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\TemplateTag;
use App\Models\TemplateTagCategory;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $query = User::withTrashed()
            ->withCount('overlayTemplates')
            ->withCount('overlayAccessTokens');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('twitch_id', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        if (! $request->boolean('include_deleted')) {
            $query->whereNull('deleted_at');
        }

        $users = $query->latest()->paginate(25)->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'include_deleted']),
        ]);
    }

    public function show(int $id): Response
    {
        $user = User::withTrashed()->findOrFail($id);

        $recentTemplates = OverlayTemplate::where('owner_id', $user->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'name', 'slug', 'type', 'is_public', 'created_at']);

        $accessTokens = $user->overlayAccessTokens()
            ->select(['id', 'name', 'token_prefix', 'is_active', 'expires_at', 'access_count', 'last_used_at'])
            ->latest()
            ->get();

        $recentAuditEntries = AdminAuditLog::where('target_type', 'User')
            ->where('target_id', $user->id)
            ->with('admin:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'recentTemplates' => $recentTemplates,
            'accessTokens' => $accessTokens,
            'recentAuditEntries' => $recentAuditEntries,
        ]);
    }

    public function updateRole(Request $request, int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $admin = $request->user();

        $request->validate(['role' => 'required|in:user,admin']);

        if ($user->id === $admin->id) {
            return back()->withErrors(['role' => 'You cannot change your own role.']);
        }

        if ($user->isGhostUser()) {
            return back()->withErrors(['role' => 'Cannot change the ghost user role.']);
        }

        // Last-admin guard
        if ($user->role === 'admin' && $request->input('role') === 'user') {
            $adminCount = User::where('role', 'admin')->count();
            if ($adminCount <= 1) {
                return back()->withErrors(['role' => 'Cannot remove the last admin role.']);
            }
        }

        $oldRole = $user->role;
        $user->update(['role' => $request->input('role')]);

        $this->audit->log($admin, 'user.role_changed', 'User', $user->id, [
            'from' => $oldRole,
            'to' => $request->input('role'),
        ], $request);

        return back()->with('message', 'Role updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $admin = $request->user();

        $request->validate([
            'strategy' => 'required|in:delete_content,assign_ghost',
        ]);

        if ($user->isGhostUser()) {
            return back()->withErrors(['strategy' => 'Cannot delete the ghost user.']);
        }

        if ($user->id === $admin->id) {
            return back()->withErrors(['strategy' => 'Cannot delete your own account.']);
        }

        if ($request->input('strategy') === 'assign_ghost') {
            $ghost = User::ghostUser();

            OverlayTemplate::where('owner_id', $user->id)->update(['owner_id' => $ghost->id]);
            Kit::where('owner_id', $user->id)->update(['owner_id' => $ghost->id]);
            OverlayControl::where('user_id', $user->id)->update(['user_id' => $ghost->id]);
            TemplateTag::where('user_id', $user->id)->update(['user_id' => $ghost->id]);
            TemplateTagCategory::where('user_id', $user->id)->update(['user_id' => $ghost->id]);
        }

        // Always delete access tokens and eventsub subscriptions (security)
        $user->overlayAccessTokens()->delete();
        $user->eventsubSubscriptions()->delete();

        $this->audit->log($admin, 'user.deleted', 'User', $user->id, [
            'strategy' => $request->input('strategy'),
            'user_name' => $user->name,
            'twitch_id' => $user->twitch_id,
        ], $request);

        $user->delete();

        return redirect()->route('admin.users.index')->with('message', 'User deleted successfully.');
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($id);
        $admin = $request->user();

        $user->restore();

        $this->audit->log($admin, 'user.restored', 'User', $user->id, [
            'user_name' => $user->name,
        ], $request);

        return back()->with('message', 'User restored successfully.');
    }
}
