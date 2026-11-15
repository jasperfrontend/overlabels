<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\ProductInstall;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\Recipes\RecipeCatalog;
use App\Support\ProductDesigner;
use App\Support\WelcomeFunnel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Does a signup make it to a product on stream? One row per account that
 * signed up in the window, with the first time it reached each step of
 * the welcome path (see WelcomeFunnel), and the totals per step on top.
 *
 * Below it, every product install in the window (see ProductInstall):
 * installed, first shown by an overlay link, uninstalled. Whether a live
 * install is broken - its overlay deleted by hand, or no overlay link left
 * on the account - is worked out here on every visit, never logged.
 */
class AdminFunnelController extends Controller
{
    /** Window choices in days; 0 is every account. */
    private const array WINDOWS = [7, 30, 90, 0];

    private const int DEFAULT_WINDOW = 30;

    /** Enough for every signup this project has had, several times over. */
    private const int ROW_LIMIT = 500;

    public function index(Request $request, RecipeCatalog $catalog): Response
    {
        $days = (int) $request->query('days', (string) self::DEFAULT_WINDOW);
        $days = in_array($days, self::WINDOWS, true) ? $days : self::DEFAULT_WINDOW;

        $rows = $this->signups($days);
        $installs = $this->installs($days);

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
                'on_stream' => $count('on_stream_at'),
            ],
            'installs' => $installs->all(),
            'install_totals' => [
                'installed' => $installs->count(),
                'on_stream' => $installs->where('state', 'on_stream')->count(),
                'waiting' => $installs->where('state', 'waiting')->count(),
                'broken' => $installs->where('state', 'broken')->count(),
                'uninstalled' => $installs->where('state', 'uninstalled')->count(),
                'uninstalled_after_showing' => $installs->where('state', 'uninstalled')->whereNotNull('first_loaded_at')->count(),
            ],
            'products' => collect($catalog->all())->map(fn (array $manifest) => $manifest['name'] ?? $manifest['slug'])->all(),
            'days' => $days,
            'windows' => self::WINDOWS,
            'limited' => $rows->count() === self::ROW_LIMIT,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function signups(int $days): \Illuminate\Support\Collection
    {
        // Installs made before the funnel existed have no stamp, or a later
        // one; the install history fills that in.
        $firstInstall = DB::table('product_installs')
            ->selectRaw('min(installed_at)')
            ->whereColumn('product_installs.user_id', 'users.id');

        $firstShown = DB::table('product_installs')
            ->selectRaw('min(first_loaded_at)')
            ->whereColumn('product_installs.user_id', 'users.id');

        $liveInstalls = DB::table('product_installs')
            ->selectRaw('count(*)')
            ->whereColumn('product_installs.user_id', 'users.id')
            ->whereNotNull('recipe_instance_id');

        $allInstalls = DB::table('product_installs')
            ->selectRaw('count(*)')
            ->whereColumn('product_installs.user_id', 'users.id');

        return User::query()
            ->where('is_system_user', false)
            ->when($days > 0, fn ($query) => $query->where('created_at', '>=', now()->subDays($days)))
            ->select(['id', 'name', 'avatar', 'created_at', 'preferences'])
            ->selectSub($firstInstall, 'first_install_at')
            ->selectSub($firstShown, 'first_shown_at')
            ->selectSub($liveInstalls, 'live_installs')
            ->selectSub($allInstalls, 'all_installs')
            ->latest()
            ->limit(self::ROW_LIMIT)
            ->get()
            ->map(function (User $user) {
                $step = fn (string $key) => $user->preference(WelcomeFunnel::PREFERENCE.'.'.$key);
                // The stamp postdates installs made before the funnel existed,
                // so the earlier of the two is the first install.
                $installedAt = collect([
                    $step('installed_at'),
                    $user->first_install_at ? Carbon::parse($user->first_install_at)->timestamp : null,
                ])->filter()->min();

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
                    'on_stream_at' => $user->first_shown_at ? Carbon::parse($user->first_shown_at)->timestamp : null,
                    // Installed something once and has nothing installed now.
                    'all_removed' => (int) $user->all_installs > 0 && (int) $user->live_installs === 0,
                ];
            })
            ->values();
    }

    /**
     * Every install in the window, newest first, with the state it is in now.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function installs(int $days): \Illuminate\Support\Collection
    {
        /** @var Collection<int, ProductInstall> $installs */
        $installs = ProductInstall::query()
            ->with('user:id,name,avatar,is_system_user')
            ->when($days > 0, fn ($query) => $query->where('installed_at', '>=', now()->subDays($days)))
            ->orderByDesc('installed_at')
            ->limit(self::ROW_LIMIT)
            ->get()
            ->filter(fn (ProductInstall $install) => $install->user && ! $install->user->is_system_user);

        // What a live install created, and which of it still exists.
        $maps = RecipeInstance::whereIn('id', $installs->pluck('recipe_instance_id')->filter())
            ->pluck('primitive_map', 'id');
        $overlayIds = $maps->flatMap(fn ($map) => array_values($map['overlays'] ?? []))->unique();
        $existing = OverlayTemplate::whereIn('id', $overlayIds)->pluck('id')->flip();

        // Accounts that still hold an overlay link that works. The designer's
        // preview link does not put anything on stream.
        $linked = OverlayAccessToken::whereIn('user_id', $installs->pluck('user_id')->unique())
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($query) => $query->whereNull('metadata->purpose')->orWhere('metadata->purpose', '!=', ProductDesigner::TOKEN_PURPOSE))
            ->distinct()
            ->pluck('user_id')
            ->flip();

        return $installs->map(function (ProductInstall $install) use ($maps, $existing, $linked) {
            $live = $install->recipe_instance_id !== null;
            $map = $live ? ($maps[$install->recipe_instance_id] ?? []) : [];

            $overlayDeleted = $live && collect($map['overlays'] ?? [])->contains(fn ($id) => ! $existing->has($id));
            $noLinks = $live && ! $linked->has($install->user_id);

            $state = match (true) {
                ! $live => 'uninstalled',
                $overlayDeleted || $noLinks => 'broken',
                $install->first_loaded_at !== null => 'on_stream',
                default => 'waiting',
            };

            return [
                'id' => $install->id,
                'user_id' => $install->user_id,
                'name' => $install->user->name,
                'avatar' => $install->user->avatar,
                'product_slug' => $install->product_slug,
                'installed_at' => $install->installed_at->timestamp,
                'first_loaded_at' => $install->first_loaded_at?->timestamp,
                'uninstalled_at' => $install->uninstalled_at?->timestamp,
                'state' => $state,
                'overlay_deleted' => $overlayDeleted,
                'no_links' => $noLinks,
            ];
        })->values();
    }
}
