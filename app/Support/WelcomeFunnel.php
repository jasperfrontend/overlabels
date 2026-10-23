<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * How far a new account gets from the dashboard towards a product on
 * stream. One set of first-time facts on the user, in the preferences
 * column beside `welcome_shown_at`: when they first reached the products
 * listing and through which link, when they first opened a product page and
 * which one, when they first installed something. Each step is written once
 * and never moved, so a visit after the first costs nothing.
 *
 * The welcome dialog and banner append `?from=` to their link, which is the
 * only way the listing can tell a banner click from a sidebar one. Any other
 * value reads as `direct`. The banner and dialog also get a first-click
 * stamp of their own, because someone who found the products through the
 * sidebar and later clicked the banner did both.
 *
 * The install stamp is kept here rather than read off recipe_instances
 * because an uninstall deletes that row, and someone who installed and
 * removed a product still made it through the funnel.
 *
 * Read by `Admin\AdminFunnelController`. An admin impersonating someone
 * stamps nothing: that visit is not theirs.
 */
final class WelcomeFunnel
{
    public const PREFERENCE = 'welcome_funnel';

    /** `?from=` value => the step it stamps. */
    public const SOURCES = [
        'welcome-banner' => 'banner_at',
        'welcome-dialog' => 'dialog_at',
    ];

    public const DIRECT = 'direct';

    public static function reachedListing(Request $request): void
    {
        $user = self::subject($request);
        if ($user === null) {
            return;
        }

        $from = $request->query('from');
        $source = is_string($from) && isset(self::SOURCES[$from]) ? $from : null;

        $changed = self::stamp($user, 'listing_at', ['listing_from' => $source ?? self::DIRECT]);
        if ($source !== null) {
            $changed = self::stamp($user, self::SOURCES[$source]) || $changed;
        }

        if ($changed) {
            $user->save();
        }
    }

    public static function openedProduct(Request $request, string $slug): void
    {
        $user = self::subject($request);

        if ($user !== null && self::stamp($user, 'product_at', ['product_slug' => $slug])) {
            $user->save();
        }
    }

    public static function installed(Request $request, string $slug): void
    {
        $user = self::subject($request);

        if ($user !== null && self::stamp($user, 'installed_at', ['installed_slug' => $slug])) {
            $user->save();
        }
    }

    /**
     * The account a visit belongs to, or null for a visitor and for an admin
     * looking through someone else's account.
     */
    private static function subject(Request $request): ?User
    {
        $user = $request->user();

        if (! $user instanceof User || $request->session()->has('impersonating_user_id')) {
            return null;
        }

        return $user;
    }

    /**
     * Write a step and its companions the first time only. Returns whether
     * anything changed, so the caller saves once.
     *
     * @param  array<string, string>  $with
     */
    private static function stamp(User $user, string $step, array $with = []): bool
    {
        if ($user->preference(self::PREFERENCE.'.'.$step) !== null) {
            return false;
        }

        $user->setPreference(self::PREFERENCE.'.'.$step, now()->timestamp);
        foreach ($with as $key => $value) {
            $user->setPreference(self::PREFERENCE.'.'.$key, $value);
        }

        return true;
    }
}
