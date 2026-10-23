<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\WelcomeFunnel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Does a signup make it to a product on stream? One row per account that
 * signed up in the window, with the first time it reached each step of
 * the welcome path (see WelcomeFunnel), and the totals per step on top.
 */
class AdminFunnelController extends Controller
{
    /** Window choices in days; 0 is every account. */
    private const array WINDOWS = [7, 30, 90, 0];

    private const int DEFAULT_WINDOW = 30;

    /** Enough for every signup this project has had, several times over. */
    private const int ROW_LIMIT = 500;

    public function index(Request $request): Response
    {
        $days = (int) $request->query('days', (string) self::DEFAULT_WINDOW);
        $days = in_array($days, self::WINDOWS, true) ? $days : self::DEFAULT_WINDOW;

        // Installs made before the funnel existed have no stamp, and an
        // uninstall deletes the row; the stamp first, the row as fallback.
        $firstInstall = DB::table('recipe_instances')
            ->selectRaw('min(created_at)')
            ->whereColumn('recipe_instances.user_id', 'users.id');

        $users = User::query()
            ->where('is_system_user', false)
            ->when($days > 0, fn ($query) => $query->where('created_at', '>=', now()->subDays($days)))
            ->select(['id', 'name', 'avatar', 'created_at', 'preferences'])
            ->selectSub($firstInstall, 'first_install_at')
            ->latest()
            ->limit(self::ROW_LIMIT)
            ->get();

        $rows = $users->map(function (User $user) {
            $step = fn (string $key) => $user->preference(WelcomeFunnel::PREFERENCE.'.'.$key);
            $installedAt = $step('installed_at')
                ?? ($user->first_install_at ? Carbon::parse($user->first_install_at)->timestamp : null);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'signed_up_at' => $user->created_at->timestamp,
                'welcomed_at' => $user->preference('welcome_shown_at'),
                'listing_at' => $step('listing_at'),
                'listing_from' => $step('listing_from'),
                'banner_at' => $step('banner_at'),
                'dialog_at' => $step('dialog_at'),
                'product_at' => $step('product_at'),
                'product_slug' => $step('product_slug'),
                'installed_at' => $installedAt,
                'installed_slug' => $step('installed_slug'),
            ];
        })->values();

        $count = fn (string $key) => $rows->whereNotNull($key)->count();

        return Inertia::render('admin/funnel/index', [
            'rows' => $rows->all(),
            'totals' => [
                'signed_up' => $rows->count(),
                'welcomed' => $count('welcomed_at'),
                'listing' => $count('listing_at'),
                'banner' => $count('banner_at'),
                'dialog' => $count('dialog_at'),
                'product' => $count('product_at'),
                'installed' => $count('installed_at'),
            ],
            'days' => $days,
            'windows' => self::WINDOWS,
            'limited' => $rows->count() === self::ROW_LIMIT,
        ]);
    }
}
