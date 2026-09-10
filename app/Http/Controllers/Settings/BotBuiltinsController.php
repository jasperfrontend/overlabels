<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BotBuiltin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The built-in bot commands, per channel. The `enabled` and `permission_level`
 * columns have existed since the registry was seeded and the command map has
 * always filtered on them - this is the surface that finally writes them.
 *
 * A command a product or the platform owns is frozen: `update()` refuses it
 * rather than relying on the page not to offer the knob, the same arrangement
 * source-managed controls have in OverlayControl::setValue().
 */
class BotBuiltinsController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $builtins = BotBuiltin::where('user_id', $user->id)
            ->orderBy('command')
            ->get()
            ->map(fn (BotBuiltin $b) => [
                'id' => $b->id,
                'command' => $b->command,
                'permission_level' => $b->permission_level,
                'enabled' => $b->enabled,
                'owner' => $b->owner(),
                'service' => $b->service(),
                'editable' => $b->editable(),
            ])
            ->all();

        return Inertia::render('settings/bot/builtins/Index', [
            'builtins' => $builtins,
            'permissionLevels' => BotBuiltin::PERMISSION_LEVELS,
            'botEnabled' => (bool) $user->bot_enabled,
        ]);
    }

    public function update(Request $request, BotBuiltin $botBuiltin): RedirectResponse
    {
        abort_unless($botBuiltin->user_id === $request->user()->id, 404);
        abort_unless($botBuiltin->editable(), 403, 'This command is managed for you and cannot be changed.');

        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'permission_level' => ['required', 'string', 'in:'.implode(',', BotBuiltin::PERMISSION_LEVELS)],
        ]);

        $botBuiltin->update([
            'enabled' => $data['enabled'],
            'permission_level' => $data['permission_level'],
        ]);

        return back();
    }
}
