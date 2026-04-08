<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OverlayAccessToken;
use App\Services\AdminAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAccessTokenController extends Controller
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function index(Request $request): Response
    {
        $tokens = OverlayAccessToken::with('user:id,name,twitch_id')
            ->select(['id', 'user_id', 'name', 'token_prefix', 'is_active', 'expires_at', 'access_count', 'last_used_at', 'created_at'])
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('admin/tokens/index', [
            'tokens' => $tokens,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(OverlayAccessToken $token): Response
    {
        $token->load('user:id,name,twitch_id');

        $accessLogs = $token->accessLogs()
            ->latest('accessed_at')
            ->limit(50)
            ->get();

        return Inertia::render('admin/tokens/show', [
            'token' => $token,
            'accessLogs' => $accessLogs,
        ]);
    }

    public function prune(Request $request): RedirectResponse
    {
        $period = $request->input('period', '12');

        $query = OverlayAccessToken::where('access_count', 0);
        if ($period !== 'all') {
            $query->where('created_at', '<', now()->subMonths((int) $period));
        }

        $count = $query->count();
        $query->delete();

        $this->audit->log($request->user(), 'tokens.pruned', null, null, [
            'period' => $period,
            'deleted_count' => $count,
        ], $request);

        return back()->with('message', "Pruned {$count} unused token".($count === 1 ? '' : 's').'.');
    }

    public function destroy(Request $request, OverlayAccessToken $token): RedirectResponse
    {
        $this->audit->log($request->user(), 'token.deleted', 'OverlayAccessToken', $token->id, [
            'prefix' => $token->token_prefix,
            'user_id' => $token->user_id,
        ], $request);

        $token->accessLogs()->delete();
        $token->delete();

        return redirect()->route('admin.tokens.index')->with('message', 'Token deleted.');
    }
}
