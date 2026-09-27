<?php

namespace App\Http\Controllers;

use App\Events\ControlValueUpdated;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Models\UserChatPreset;
use App\Services\Recipes\RecipeCatalog;
use App\Support\ProductDesigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A streamer's own looks for a product, as its designer works them: save the
 * current look under a name, apply one, update one with the current look,
 * rename one, delete one.
 *
 * Every door answers JSON, because every one of them is pressed from the
 * designer and the designer never navigates - a page load would reload the
 * preview frame and throw away the chat in it (OL-2609-109).
 *
 * The client never posts values. Saving and updating read the overlay's
 * designer controls server-side (ProductDesigner::currentValues), so a bundle
 * can only ever hold what the value endpoint already accepted onto those
 * rows.
 *
 * Install-gated like the designer itself: 404 for a product with no
 * designer, for an account with no install, and for a preset that is not the
 * caller's or was saved on another product's designer.
 */
class SavedChatPresetController extends Controller
{
    public function __construct(private readonly RecipeCatalog $catalog) {}

    public function store(Request $request, string $slug): JsonResponse
    {
        [$user, $template, $designer, $product] = $this->resolve($request, $slug);

        $count = UserChatPreset::where('user_id', $user->id)->where('product', $product)->count();
        abort_if(
            $count >= UserChatPreset::MAX_PER_USER,
            422,
            'You have '.UserChatPreset::MAX_PER_USER.' saved looks already. Delete one to save another.'
        );

        $validated = $this->validateName($request, $user, $product);

        $preset = UserChatPreset::create([
            'user_id' => $user->id,
            'product' => $product,
            'name' => $validated['name'],
            'values' => ProductDesigner::currentValues($designer, $template),
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
        [$user, $template, , $product] = $this->resolve($request, $slug);
        $this->own($preset, $user, $product);

        foreach (ProductDesigner::applyValues($template, $preset->values) as $control) {
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
        [$user, $template, $designer, $product] = $this->resolve($request, $slug);
        $this->own($preset, $user, $product);

        $preset->update(['values' => ProductDesigner::currentValues($designer, $template)]);

        return response()->json(['preset' => $preset->fresh()->toDesigner()]);
    }

    public function rename(Request $request, string $slug, UserChatPreset $preset): JsonResponse
    {
        [$user, , , $product] = $this->resolve($request, $slug);
        $this->own($preset, $user, $product);

        $validated = $this->validateName($request, $user, $product, $preset);

        $preset->update(['name' => $validated['name']]);

        return response()->json(['preset' => $preset->fresh()->toDesigner()]);
    }

    public function destroy(Request $request, string $slug, UserChatPreset $preset): JsonResponse
    {
        [$user, , , $product] = $this->resolve($request, $slug);
        $this->own($preset, $user, $product);

        $preset->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * A look is only ever reachable from the designer it was saved on. Another
     * account's, or another product's, is a 404 like a look that never existed.
     */
    private function own(UserChatPreset $preset, User $user, string $product): void
    {
        abort_if($preset->user_id !== $user->id || $preset->product !== $product, 404);
    }

    /**
     * @return array{name: string}
     */
    private function validateName(Request $request, User $user, string $product, ?UserChatPreset $ignore = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name', ''))]);

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:'.UserChatPreset::NAME_MAX,
                Rule::unique('user_chat_presets', 'name')
                    ->where('user_id', $user->id)
                    ->where('product', $product)
                    ->ignore($ignore?->id),
            ],
        ], [
            'name.required' => 'Give the look a name.',
            'name.max' => 'A name is at most '.UserChatPreset::NAME_MAX.' characters.',
            'name.unique' => 'You already have a look with that name.',
        ]);
    }

    /**
     * The caller, the overlay their install created, the product's designer
     * block and the product's current slug. Mirrors what
     * ProductController::design() requires before it renders the page these
     * doors are pressed from.
     *
     * @return array{0: User, 1: OverlayTemplate, 2: array<string, mixed>, 3: string}
     */
    private function resolve(Request $request, string $slug): array
    {
        $slug = $this->catalog->canonicalSlugFor($slug) ?? $slug;
        $manifest = $this->catalog->find($slug);
        $designer = $manifest && ($manifest['listed'] ?? false) ? ProductDesigner::declared($manifest) : null;
        abort_if($designer === null, 404);

        $user = $request->user();

        $instance = RecipeInstance::where('user_id', $user->id)
            ->whereIn('recipe_id', Recipe::where('slug', $slug)->select('id'))
            ->orderByDesc('created_at')
            ->first();

        $template = $instance ? ProductDesigner::overlayFor($designer, $instance) : null;
        abort_if($template === null || $template->owner_id !== $user->id, 404);

        return [$user, $template, $designer, $slug];
    }
}
