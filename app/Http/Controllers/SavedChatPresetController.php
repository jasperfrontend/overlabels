<?php

namespace App\Http\Controllers;

use App\Events\ControlValueUpdated;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Models\UserChatPreset;
use App\Support\ChatPresets;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A streamer's own looks for the Twitch Chat product, as the designer works
 * them: save the current look under a name, apply one, update one with the
 * current look, rename one, delete one.
 *
 * Every door answers JSON, because every one of them is pressed from the
 * designer and the designer never navigates - a page load would reload the
 * preview frame and throw away the chat in it (OL-2609-109).
 *
 * The client never posts values. Saving and updating read the overlay's
 * controls server-side (ChatPresets::currentValues), so a bundle can only
 * ever hold what the value endpoint already accepted onto those rows.
 *
 * Install-gated like the designer itself: 404 for any product but Twitch
 * Chat, for an account with no install, and for a preset that is not the
 * caller's.
 */
class SavedChatPresetController extends Controller
{
    public function store(Request $request, string $slug): JsonResponse
    {
        [$user, $template] = $this->resolve($request, $slug);

        $count = UserChatPreset::where('user_id', $user->id)->count();
        abort_if(
            $count >= UserChatPreset::MAX_PER_USER,
            422,
            'You have '.UserChatPreset::MAX_PER_USER.' saved looks already. Delete one to save another.'
        );

        $validated = $this->validateName($request, $user);

        $preset = UserChatPreset::create([
            'user_id' => $user->id,
            'name' => $validated['name'],
            'values' => ChatPresets::currentValues($template),
        ]);

        return response()->json(['preset' => $preset->toDesigner()], 201);
    }

    /**
     * Same shape as ProductController::applyPreset()'s JSON branch: every
     * control written is broadcast, and the values come back so the knobs
     * move in the same beat the overlay does.
     */
    public function apply(Request $request, string $slug, UserChatPreset $preset): JsonResponse
    {
        [$user, $template] = $this->resolve($request, $slug);
        abort_if($preset->user_id !== $user->id, 404);

        foreach (ChatPresets::applyValues($template, $preset->values) as $control) {
            ControlValueUpdated::dispatch(
                $template->slug,
                $control->broadcastKey(),
                $control->type,
                (string) $control->value,
                $user->twitch_id,
                null,
                null,
                null
            );
        }

        return response()->json(['values' => $preset->values]);
    }

    /** Capture the overlay's current look into this preset, replacing what it held. */
    public function overwrite(Request $request, string $slug, UserChatPreset $preset): JsonResponse
    {
        [$user, $template] = $this->resolve($request, $slug);
        abort_if($preset->user_id !== $user->id, 404);

        $preset->update(['values' => ChatPresets::currentValues($template)]);

        return response()->json(['preset' => $preset->fresh()->toDesigner()]);
    }

    public function rename(Request $request, string $slug, UserChatPreset $preset): JsonResponse
    {
        [$user] = $this->resolve($request, $slug);
        abort_if($preset->user_id !== $user->id, 404);

        $validated = $this->validateName($request, $user, $preset);

        $preset->update(['name' => $validated['name']]);

        return response()->json(['preset' => $preset->fresh()->toDesigner()]);
    }

    public function destroy(Request $request, string $slug, UserChatPreset $preset): JsonResponse
    {
        [$user] = $this->resolve($request, $slug);
        abort_if($preset->user_id !== $user->id, 404);

        $preset->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return array{name: string}
     */
    private function validateName(Request $request, User $user, ?UserChatPreset $ignore = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:'.UserChatPreset::NAME_MAX,
                Rule::unique('user_chat_presets', 'name')
                    ->where('user_id', $user->id)
                    ->ignore($ignore?->id),
            ],
        ], [
            'name.required' => 'Give the look a name.',
            'name.max' => 'A name is at most '.UserChatPreset::NAME_MAX.' characters.',
            'name.unique' => 'You already have a look with that name.',
        ]);
    }

    /**
     * The caller and the chat overlay their install created. Mirrors what
     * ProductController::design() requires before it renders the page these
     * doors are pressed from.
     *
     * @return array{0: User, 1: OverlayTemplate}
     */
    private function resolve(Request $request, string $slug): array
    {
        abort_unless(ChatPresets::has($slug), 404);

        $user = $request->user();

        $instance = RecipeInstance::where('user_id', $user->id)
            ->whereIn('recipe_id', Recipe::where('slug', $slug)->select('id'))
            ->orderByDesc('created_at')
            ->first();

        $template = $instance ? ChatPresets::overlayFor($instance) : null;
        abort_if($template === null || $template->owner_id !== $user->id, 404);

        return [$user, $template];
    }
}
