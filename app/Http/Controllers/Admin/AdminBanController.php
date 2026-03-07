<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Mchev\Banhammer\IP;
use Mchev\Banhammer\Models\Ban;

class AdminBanController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $query = Ban::with(['bannable', 'createdBy']);

        // Filter by status
        $status = $request->input('status', 'active');
        if ($status === 'active') {
            $query->notExpired();
        } elseif ($status === 'expired') {
            $query->expired();
        }

        // Filter by type
        if ($request->input('type') === 'user') {
            $query->whereNotNull('bannable_type');
        } elseif ($request->input('type') === 'ip') {
            $query->whereNull('bannable_type');
        }

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ip', 'like', "%{$search}%")
                    ->orWhere('comment', 'like', "%{$search}%");
            });
        }

        $bans = $query->latest()->paginate(50)->withQueryString();

        // Stats
        $activeQuery = Ban::notExpired();
        $stats = [
            'active' => (clone $activeQuery)->count(),
            'user_bans' => (clone $activeQuery)->whereNotNull('bannable_type')->count(),
            'ip_bans' => (clone $activeQuery)->whereNull('bannable_type')->count(),
        ];

        return Inertia::render('admin/bans/index', [
            'bans' => $bans,
            'filters' => $request->only(['status', 'type', 'search']),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $this->resolveAdmin($request);

        $request->validate([
            'type' => 'required|in:user,ip',
            'user_id' => 'required_if:type,user|nullable|integer|exists:users,id',
            'ip' => 'required_if:type,ip|nullable|ip',
            'comment' => 'nullable|string|max:500',
            'duration' => 'nullable|in:1h,6h,24h,7d,30d,permanent',
        ]);

        $expiredAt = $this->resolveExpiry($request->input('duration'));

        if ($request->input('type') === 'user') {
            return $this->banUser($request, $admin, $expiredAt);
        }

        return $this->banIp($request, $admin, $expiredAt);
    }

    public function destroy(Request $request, Ban $ban): RedirectResponse
    {
        $admin = $this->resolveAdmin($request);

        $metadata = ['comment' => $ban->comment, 'ip' => $ban->ip];

        if ($ban->bannable_type && $ban->bannable) {
            $metadata['user_name'] = $ban->bannable->name ?? null;
            $metadata['user_id'] = $ban->bannable_id;
        }

        $ban->forceDelete();

        $this->audit->log($admin, 'ban.removed', 'Ban', $ban->id, $metadata, $request);

        return back()->with('message', 'Ban removed.');
    }

    public function banFromSession(Request $request): RedirectResponse
    {
        $admin = $this->resolveAdmin($request);

        $request->validate([
            'session_id' => 'required|string',
            'ban_user' => 'boolean',
            'ban_ip' => 'boolean',
            'comment' => 'nullable|string|max:500',
            'duration' => 'nullable|in:1h,6h,24h,7d,30d,permanent',
        ]);

        $session = DB::table('sessions')->where('id', $request->input('session_id'))->first();

        if (! $session) {
            return back()->withErrors(['session_id' => 'Session not found.']);
        }

        $expiredAt = $this->resolveExpiry($request->input('duration'));
        $messages = [];

        // Ban user
        if ($request->boolean('ban_user') && $session->user_id) {
            $user = User::find($session->user_id);

            if ($user) {
                if ($user->isAdmin()) {
                    return back()->withErrors(['ban_user' => 'Cannot ban an admin.']);
                }

                if ($user->isGhostUser()) {
                    return back()->withErrors(['ban_user' => 'Cannot ban the ghost user.']);
                }

                $ban = $user->ban([
                    'comment' => $request->input('comment'),
                    'ip' => $session->ip_address,
                    'expired_at' => $expiredAt,
                    'created_by_type' => User::class,
                    'created_by_id' => $admin->id,
                ]);

                DB::table('sessions')->where('user_id', $user->id)->delete();

                $this->audit->log($admin, 'ban.created', 'User', $user->id, [
                    'type' => 'user',
                    'comment' => $request->input('comment'),
                    'expired_at' => $expiredAt,
                    'from_session' => true,
                ], $request);

                $messages[] = "User {$user->name} banned.";
            }
        }

        // Ban IP
        if ($request->boolean('ban_ip') && $session->ip_address) {
            if (! IP::isBanned($session->ip_address)) {
                IP::ban($session->ip_address, [
                    'comment' => $request->input('comment'),
                    'created_by_id' => $admin->id,
                    'created_by_type' => User::class,
                ], $expiredAt);

                DB::table('sessions')->where('ip_address', $session->ip_address)->delete();

                $this->audit->log($admin, 'ban.created', 'Ban', null, [
                    'type' => 'ip',
                    'ip' => $session->ip_address,
                    'comment' => $request->input('comment'),
                    'expired_at' => $expiredAt,
                    'from_session' => true,
                ], $request);

                $messages[] = "IP {$session->ip_address} banned.";
            } else {
                $messages[] = "IP {$session->ip_address} already banned.";
            }
        }

        // Always invalidate the specific session
        DB::table('sessions')->where('id', $request->input('session_id'))->delete();

        return back()->with('message', implode(' ', $messages) ?: 'Session invalidated.');
    }

    private function banUser(Request $request, User $admin, ?string $expiredAt): RedirectResponse
    {
        $user = User::findOrFail($request->input('user_id'));

        if ($user->isAdmin()) {
            return back()->withErrors(['user_id' => 'Cannot ban an admin.']);
        }

        if ($user->isGhostUser()) {
            return back()->withErrors(['user_id' => 'Cannot ban the ghost user.']);
        }

        if ($user->id === $admin->id) {
            return back()->withErrors(['user_id' => 'Cannot ban yourself.']);
        }

        if ($user->isBanned()) {
            return back()->withErrors(['user_id' => 'User is already banned.']);
        }

        $user->ban([
            'comment' => $request->input('comment'),
            'expired_at' => $expiredAt,
            'created_by_type' => User::class,
            'created_by_id' => $admin->id,
        ]);

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->audit->log($admin, 'ban.created', 'User', $user->id, [
            'type' => 'user',
            'user_name' => $user->name,
            'comment' => $request->input('comment'),
            'expired_at' => $expiredAt,
        ], $request);

        return back()->with('message', "User {$user->name} has been banned.");
    }

    private function banIp(Request $request, User $admin, ?string $expiredAt): RedirectResponse
    {
        $ip = $request->input('ip');

        if (IP::isBanned($ip)) {
            return back()->withErrors(['ip' => 'This IP is already banned.']);
        }

        IP::ban($ip, [
            'comment' => $request->input('comment'),
            'created_by_id' => $admin->id,
            'created_by_type' => User::class,
        ], $expiredAt);

        DB::table('sessions')->where('ip_address', $ip)->delete();

        $this->audit->log($admin, 'ban.created', 'Ban', null, [
            'type' => 'ip',
            'ip' => $ip,
            'comment' => $request->input('comment'),
            'expired_at' => $expiredAt,
        ], $request);

        return back()->with('message', "IP {$ip} has been banned.");
    }

    private function resolveExpiry(?string $duration): ?string
    {
        return match ($duration) {
            '1h' => now()->addHour()->format('Y-m-d H:i:s'),
            '6h' => now()->addHours(6)->format('Y-m-d H:i:s'),
            '24h' => now()->addDay()->format('Y-m-d H:i:s'),
            '7d' => now()->addWeek()->format('Y-m-d H:i:s'),
            '30d' => now()->addMonth()->format('Y-m-d H:i:s'),
            default => null, // permanent
        };
    }

    private function resolveAdmin(Request $request): User
    {
        // During impersonation, get the real admin
        if ($realAdminId = $request->session()->get('real_admin_id')) {
            return User::findOrFail($realAdminId);
        }

        return $request->user();
    }
}
