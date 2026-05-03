<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\ExternalIntegration;
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
    /**
     * Donation-style integrations whose donations_received seed value can be
     * (re)set from the admin panel. Each entry is [service_key => display_name].
     */
    private const SEEDABLE_SERVICES = [
        'kofi' => 'Ko-fi',
        'streamlabs' => 'StreamLabs',
        'streamelements' => 'StreamElements',
        'fourthwall' => 'Fourthwall',
        'bmac' => 'Buy Me a Coffee',
    ];

    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $query = User::withTrashed()
            ->withCount('overlayTemplates')
            ->withCount('overlayAccessTokens');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                    ->orWhere('twitch_id', 'like', "%$search%");
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

        $integrations = ExternalIntegration::where('user_id', $user->id)
            ->whereIn('service', array_keys(self::SEEDABLE_SERVICES))
            ->get()
            ->keyBy('service');

        $integrationSeeds = collect(self::SEEDABLE_SERVICES)
            ->map(function (string $label, string $service) use ($integrations) {
                $integration = $integrations->get($service);
                $settings = $integration?->settings ?? [];

                return [
                    'service' => $service,
                    'label' => $label,
                    'connected' => $integration !== null,
                    'seed_set' => ! empty($settings['donations_seed_set']),
                    'seed_value' => $settings['donations_seed_value'] ?? null,
                ];
            })
            ->values()
            ->all();

        $activeBan = $user->bans()->notExpired()->latest()->first();

        return Inertia::render('admin/users/show', [
            'user' => $user,
            'recentTemplates' => $recentTemplates,
            'accessTokens' => $accessTokens,
            'recentAuditEntries' => $recentAuditEntries,
            'integrationSeeds' => $integrationSeeds,
            'isBanned' => $user->isBanned(),
            'activeBan' => $activeBan ? [
                'id' => $activeBan->id,
                'comment' => $activeBan->comment,
                'expired_at' => $activeBan->expired_at?->toISOString(),
                'created_at' => $activeBan->created_at->toISOString(),
                'ip' => $activeBan->ip,
            ] : null,
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
        $user = User::withTrashed()->findOrFail($id);
        $admin = $request->user();

        $request->validate([
            'strategy' => 'required|in:delete_content,assign_ghost,delete_all',
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

            // Delete tags/categories that would conflict with existing ghost user rows,
            // then reassign the rest.
            $existingGhostTagKeys = TemplateTag::where('user_id', $ghost->id)
                ->select('category_id')
                ->get()
                ->map(fn ($t) => $t->category_id.':'.$t->tag_name)
                ->all();
            TemplateTag::where('user_id', $user->id)
                ->get()
                ->each(function ($tag) use ($existingGhostTagKeys) {
                    if (in_array($tag->category_id.':'.$tag->tag_name, $existingGhostTagKeys)) {
                        $tag->delete();
                    }
                });
            TemplateTag::where('user_id', $user->id)->update(['user_id' => $ghost->id]);

            $existingGhostCategoryNames = TemplateTagCategory::where('user_id', $ghost->id)
                ->pluck('name')
                ->all();
            TemplateTagCategory::where('user_id', $user->id)
                ->whereIn('name', $existingGhostCategoryNames)
                ->delete();
            TemplateTagCategory::where('user_id', $user->id)->update(['user_id' => $ghost->id]);
        }

        if ($request->input('strategy') === 'delete_all') {
            // Detach templates from kits (pivot), then delete kits
            Kit::where('owner_id', $user->id)->each(function ($kit) {
                $kit->templates()->detach();
                $kit->delete();
            });

            // Detach templates from alert targeting pivot, then delete
            OverlayTemplate::where('owner_id', $user->id)->each(function ($template) {
                $template->targetStaticOverlays()->detach();
                $template->kits()->detach();
                $template->controls()->delete();
                $template->eventMappings()->delete();
                $template->delete();
            });

            OverlayControl::where('user_id', $user->id)->delete();
            TemplateTag::where('user_id', $user->id)->delete();
            TemplateTagCategory::where('user_id', $user->id)->delete();
            ExternalIntegration::where('user_id', $user->id)->delete();
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

    /**
     * Admin override for the donations_received seed value on any donation-
     * style integration (Ko-fi, StreamLabs, StreamElements, Fourthwall, BMAC).
     * Bypasses the one-time lock the user-facing settings enforce, so admins
     * can correct mistakes after the fact.
     */
    public function updateIntegrationSeed(Request $request, int $id, string $service): RedirectResponse
    {
        if (! array_key_exists($service, self::SEEDABLE_SERVICES)) {
            abort(404);
        }

        $user = User::withTrashed()->findOrFail($id);
        $admin = $request->user();

        $validated = $request->validate([
            'initial_count' => 'required|integer|min:0|max:9999999',
        ]);

        $integration = ExternalIntegration::where('user_id', $user->id)
            ->where('service', $service)
            ->first();

        if (! $integration) {
            $label = self::SEEDABLE_SERVICES[$service];

            return back()->withErrors(['initial_count' => "User has no $label integration."]);
        }

        OverlayControl::where('user_id', $user->id)
            ->where('source', $service)
            ->where('key', 'donations_received')
            ->where('source_managed', true)
            ->update(['value' => (string) $validated['initial_count']]);

        $integration->settings = array_merge($integration->settings ?? [], [
            'donations_seed_set' => true,
            'donations_seed_value' => $validated['initial_count'],
        ]);
        $integration->save();

        $this->audit->log($admin, 'user.integration_seed_updated', 'User', $user->id, [
            'service' => $service,
            'initial_count' => $validated['initial_count'],
        ], $request);

        return back()->with('message', self::SEEDABLE_SERVICES[$service].' seed value updated.');
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
