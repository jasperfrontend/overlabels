<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Twitch Chat Plinko's settings, at /settings/integrations/plinko: the word
 * the board listens for. The page only exists for an account that has Plinko
 * installed - a setting for a product you do not have is noise - and the
 * integrations overview lists it on the same condition (IntegrationController).
 *
 * The word lives in the user's preferences, not on the install, so it
 * survives an uninstall and a reinstall.
 */
class PlinkoSettingsController extends Controller
{
    public const string PRODUCT_SLUG = 'twitch-chat-plinko';

    public static function installedFor(User $user): bool
    {
        return RecipeInstance::where('user_id', $user->id)
            ->whereIn('recipe_id', Recipe::where('slug', self::PRODUCT_SLUG)->select('id'))
            ->exists();
    }

    public function show(Request $request): Response
    {
        $this->ensureInstalled($request->user());

        return Inertia::render('settings/integrations/plinko', [
            'plinkoCommand' => $request->user()->plinkoCommand(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $user = $request->user();
        $this->ensureInstalled($user);

        // A pasted `!drop` or ` Drop ` means drop: forgive the `!`, the case
        // and the spaces, and refuse only what could never be a command.
        $request->merge([
            'plinko_command' => strtolower(trim(ltrim(trim((string) $request->input('plinko_command')), '!'))),
        ]);
        $validated = $request->validate([
            'plinko_command' => ['required', 'string', 'regex:'.User::PLINKO_COMMAND_PATTERN],
        ], [
            'plinko_command.required' => 'Type the word your chat will use.',
            'plinko_command.regex' => 'Use one word: letters, numbers or _, up to 25 characters.',
        ]);

        $user->setPreference('plinko.command', $validated['plinko_command']);
        $user->save();

        return back()->with('success', 'Plinko command saved. Reload your Plinko overlay in OBS to use it.');
    }

    private function ensureInstalled(User $user): void
    {
        abort_unless(self::installedFor($user), 404);
    }
}
