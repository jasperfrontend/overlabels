<?php

namespace App\Http\Controllers;

use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\BotModeratedChannels;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeInstaller;
use App\Support\OverlayMarkdown;
use App\Support\ProductSetup;
use App\Support\WiringFacts;
use App\Support\WiringReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * A product is a listed recipe: something a streamer installs in one click
 * and finishes setting up from the same page. The catalogue is the repo
 * (RecipeCatalog); the install is RecipeInstaller; the "what is left for
 * you to do" block is the product's wiring circuit.
 *
 * Index and show are public. A visitor can read what a product does before
 * having an account; the install button is where the login happens.
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly RecipeCatalog $catalog,
        private readonly RecipeInstaller $installer,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $installed = $user ? $this->installedSlugs($user) : [];

        $products = collect($this->catalog->listed())
            ->map(fn (array $manifest) => [
                'slug' => $manifest['slug'],
                'name' => $manifest['name'],
                'description' => $manifest['description'],
                'requires_bot' => (bool) ($manifest['requires_bot'] ?? false),
                'hero' => $manifest['hero'] ?? null,
                'installed' => in_array($manifest['slug'], $installed, true),
            ])
            ->values()
            ->all();

        view()->share('og', [
            'title' => 'Products - Overlabels',
            'description' => 'Things viewers can do in your chat and see on your stream. Installed in one click, set up from one page.',
            'url' => route('products.index'),
        ]);

        return Inertia::render('products/index', [
            'products' => $products,
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $manifest = $this->listedManifest($slug);
        $user = $request->user();
        $instance = $user ? $this->instanceFor($user, $slug) : null;

        // Coming back to the page mid-setup is the moment to re-ask Twitch
        // about mod status rather than serve a five-minute-old answer, since
        // typing /mod overlabels is the step people leave for.
        $inSetup = $user && $instance && ProductSetup::activeSlug($user) === $slug;
        if ($inSetup) {
            app(BotModeratedChannels::class)->forget();
        }

        $overlays = collect($manifest['installs']['overlays'] ?? [])
            ->map(function (array $overlay) use ($slug) {
                $doc = OverlayMarkdown::parse(
                    (string) file_get_contents(RecipeInstaller::directoryFor($slug).DIRECTORY_SEPARATOR.basename($overlay['file']))
                );

                return ['ref' => $overlay['ref'], 'name' => $doc['name'], 'type' => $doc['type'], 'description' => $doc['description']];
            })
            ->values()
            ->all();

        view()->share('og', [
            'title' => $manifest['name'].' - Overlabels product',
            'description' => $manifest['description'],
            'url' => route('products.show', $slug),
        ]);

        $installed = $instance ? $this->installedView($instance) : null;

        // The page seeing nothing left is what ends the flow: the banner's
        // "Go to installed product" is just a link here.
        if ($inSetup && $installed && $installed['remaining'] === 0) {
            ProductSetup::end($user);
        }

        return Inertia::render('products/show', [
            'product' => [
                'slug' => $manifest['slug'],
                'name' => $manifest['name'],
                'description' => $manifest['description'],
                'requires_bot' => (bool) ($manifest['requires_bot'] ?? false),
                'hero' => $manifest['hero'] ?? null,
                'integrations' => $manifest['installs']['integrations'] ?? [],
                'overlays' => $overlays,
                'lists' => collect($manifest['installs']['lists'] ?? [])
                    ->map(fn (array $list) => ['slug' => $list['slug'], 'label' => $list['label'] ?? $list['slug']])
                    ->values()
                    ->all(),
                // Every chat command the install creates, in one list for the
                // page, with a line saying what kind it is.
                'commands' => collect($manifest['installs']['list_appenders'] ?? [])
                    ->map(fn (array $appender) => [
                        'command' => $appender['command'],
                        'kind' => 'appender',
                        'detail' => 'Anyone in chat can type it to get on the list.',
                    ])
                    ->concat(collect($manifest['installs']['bot_aliases'] ?? [])->map(fn (array $alias) => [
                        'command' => $alias['command'],
                        'kind' => 'alias',
                        'detail' => 'Short for '.ltrim($alias['target'], '!').'. An alias, so you can rename it.',
                    ]))
                    ->concat(collect($manifest['installs']['bot_commands'] ?? [])->map(fn (array $command) => [
                        'command' => $command['command'],
                        'kind' => 'command',
                        'detail' => 'A bot command with its own reply. Yours to edit.',
                    ]))
                    ->values()
                    ->all(),
                'notes' => $manifest['notes'] ?? [],
                'ready_message' => $manifest['ready_message'] ?? null,
            ],
            'installed' => $installed,
        ]);
    }

    public function install(Request $request, string $slug): RedirectResponse
    {
        $manifest = $this->listedManifest($slug);
        $user = $request->user();

        // The result is visible where the button was: the page turns into
        // its installed state. No toast on top of that.
        if ($this->instanceFor($user, $slug)) {
            return redirect()->route('products.show', $slug);
        }

        $recipe = $this->catalog->sync($manifest);

        try {
            $this->installer->install($recipe, $user, $slug);
        } catch (RuntimeException $e) {
            return redirect()->route('products.show', $slug)->withErrors(['install' => $e->getMessage()]);
        }

        // From here every app page carries the setup banner until the
        // product page sees nothing left, or the person says not now.
        ProductSetup::start($user, $slug);

        return redirect()->route('products.show', $slug)->with('success', $manifest['name'].' is installed.');
    }

    public function dismissSetup(Request $request): RedirectResponse
    {
        ProductSetup::end($request->user());

        return back();
    }

    public function uninstall(Request $request, string $slug): RedirectResponse
    {
        $manifest = $this->listedManifest($slug);
        $instance = $this->instanceFor($request->user(), $slug);

        // Nothing installed is nothing to undo; the page already shows that.
        if (! $instance) {
            return redirect()->route('products.show', $slug);
        }

        try {
            $this->installer->uninstall($instance);
        } catch (RuntimeException $e) {
            return redirect()->route('products.show', $slug)->withErrors(['uninstall' => $e->getMessage()]);
        }

        if (ProductSetup::activeSlug($request->user()) === $slug) {
            ProductSetup::end($request->user());
        }

        return redirect()->route('products.show', $slug)->with('success', $manifest['name'].' is uninstalled.');
    }

    /**
     * @return array<string, mixed>
     */
    private function listedManifest(string $slug): array
    {
        $manifest = $this->catalog->find($slug);

        if ($manifest === null || ! ($manifest['listed'] ?? false)) {
            abort(404);
        }

        return $manifest;
    }

    /**
     * @return list<string>
     */
    private function installedSlugs(User $user): array
    {
        return RecipeInstance::query()
            ->where('user_id', $user->id)
            ->join('recipes', 'recipes.id', '=', 'recipe_instances.recipe_id')
            ->pluck('recipes.slug')
            ->unique()
            ->values()
            ->all();
    }

    private function instanceFor(User $user, string $slug): ?RecipeInstance
    {
        return RecipeInstance::with('recipe')
            ->where('user_id', $user->id)
            ->whereIn('recipe_id', Recipe::where('slug', $slug)->select('id'))
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * The installed product as the page shows it: its circuit, and a link to
     * each overlay the install created.
     *
     * @return array<string, mixed>
     */
    private function installedView(RecipeInstance $instance): array
    {
        $report = WiringReport::build(['products' => [WiringFacts::productSubject($instance)]]);
        $circuit = collect($report)->firstWhere('key', 'products');

        $overlays = collect($instance->primitive_map['overlays'] ?? [])
            ->map(function (int $id, string $ref) {
                $template = OverlayTemplate::find($id);

                return $template ? ['ref' => $ref, 'name' => $template->name, 'slug' => $template->slug, 'id' => $template->id] : null;
            })
            ->filter()
            ->values()
            ->all();

        $subject = $circuit['subjects'][0] ?? null;

        return [
            'installed_at' => $instance->created_at->toIso8601String(),
            'subject' => $subject,
            'remaining' => (int) ($subject['missing'] ?? 0),
            'overlays' => $overlays,
            'removes' => $this->installer->removals($instance),
        ];
    }
}
