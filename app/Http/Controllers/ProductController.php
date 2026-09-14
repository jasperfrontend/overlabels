<?php

namespace App\Http\Controllers;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEvent;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\OverlayAccessLog;
use App\Models\OverlayAccessToken;
use App\Models\OverlayTemplate;
use App\Models\Recipe;
use App\Models\RecipeInstance;
use App\Models\User;
use App\Services\BotModeratedChannels;
use App\Services\Recipes\RecipeCatalog;
use App\Services\Recipes\RecipeIngredients;
use App\Services\Recipes\RecipeInstaller;
use App\Support\OverlayMarkdown;
use App\Support\ProductSetup;
use App\Support\ServiceConnections;
use App\Support\ServiceTestGuides;
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

        $installed = $instance ? $this->installedView($instance, $slug) : null;

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
                // The questions the install asks. Integrations are handed over
                // as written, placeholders and all: the page fills them from
                // whatever is currently picked, so the list of what the click
                // gives you follows the pick.
                'ingredients' => RecipeIngredients::declared($manifest),
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

        // The answers to the product's questions, keyed by ingredient key.
        // Whether each one is a choice the recipe offers is the installer's
        // call, and its refusal lands on the page like any other.
        $ingredients = $request->validate([
            'ingredients' => ['sometimes', 'array'],
            'ingredients.*' => ['string', 'max:50'],
        ])['ingredients'] ?? [];

        $recipe = $this->catalog->sync($manifest);

        try {
            $this->installer->install($recipe, $user, $slug, null, $ingredients);
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
     * The installed product as the page shows it: its circuit, and each
     * overlay the install created with what the page needs to say about it.
     *
     * A static overlay is the thing that goes into OBS. An alert never does:
     * it fires on an event and renders inside the static overlays it targets,
     * so for one the page says what it fires on and where it shows, read live
     * from the same rows the Triggers and Targeting tabs read, and offers no
     * OBS step at all. `more_events` is what else the connected service can
     * send that the alert does not yet fire on, for the line that points at
     * the Triggers tab.
     *
     * `test_guide` is how to make the picked service send a test event, and
     * `landed` is the latest such event since the install, so the page can
     * say the alert fired rather than leave the person guessing. Both come
     * from the resolved manifest: which service this install actually
     * connected, not the placeholder the catalogue file carries.
     *
     * `services` is every donation service the product's alerts fire on,
     * each with where it stands for this person and the one thing that
     * connects it, so the page can offer the connect for all of them right
     * there rather than call itself done after the first. Read from the
     * same trigger rows as `fires_on`, so a trigger deleted on the Triggers
     * tab takes its row with it.
     *
     * @return array<string, mixed>
     */
    private function installedView(RecipeInstance $instance, string $slug): array
    {
        $report = WiringReport::build(['products' => [WiringFacts::productSubject($instance)]]);
        $circuit = collect($report)->firstWhere('key', 'products');
        $services = array_values($instance->resolvedManifest()['installs']['integrations'] ?? []);

        // Every (service, event_type) pair an installed alert fires on, for
        // the "did it land" lookup below.
        $firing = [];

        $overlays = collect($instance->primitive_map['overlays'] ?? [])
            ->map(function (int $id, string $ref) use ($services, &$firing) {
                $template = OverlayTemplate::find($id);
                if (! $template) {
                    return null;
                }

                $overlay = ['ref' => $ref, 'name' => $template->name, 'slug' => $template->slug, 'id' => $template->id, 'type' => $template->type];

                if ($template->type === 'alert') {
                    $external = ExternalEventTemplateMapping::where('overlay_template_id', $template->id)
                        ->where('enabled', true)
                        ->get();

                    $overlay['fires_on'] = EventTemplateMapping::where('template_id', $template->id)
                        ->where('enabled', true)
                        ->get()
                        ->map(fn (EventTemplateMapping $m) => $m->event_type_display)
                        ->concat($external->map(fn (ExternalEventTemplateMapping $m) => ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$m->service][$m->event_type] ?? "{$m->service} {$m->event_type}"))
                        ->values()
                        ->all();
                    $overlay['targets'] = $template->targetStaticOverlays()->pluck('name')->all();

                    $more = [];
                    foreach ($services as $service) {
                        $firingTypes = $external->where('service', $service)->pluck('event_type')->all();
                        foreach (ExternalEventTemplateMapping::SERVICE_EVENT_TYPES[$service] ?? [] as $eventType => $label) {
                            if (! in_array($eventType, $firingTypes, true)) {
                                $more[] = $label;
                            }
                        }
                    }
                    $overlay['more_events'] = $more;

                    foreach ($external as $m) {
                        $firing[] = ['service' => $m->service, 'event_type' => $m->event_type];
                    }
                }

                return $overlay;
            })
            ->filter()
            ->values()
            ->all();

        $subject = $circuit['subjects'][0] ?? null;

        $rows = ExternalIntegration::where('user_id', $instance->user_id)->get()->keyBy('service');
        $returnTo = route('products.show', $slug, false);
        $connectable = collect($firing)
            ->pluck('service')
            ->unique()
            ->filter(fn (string $service) => ServiceConnections::has($service))
            ->map(fn (string $service) => ServiceConnections::for($service, $rows->get($service), $returnTo))
            ->values()
            ->all();

        return [
            'installed_at' => $instance->created_at->toIso8601String(),
            'subject' => $subject,
            'remaining' => (int) ($subject['missing'] ?? 0),
            'overlays' => $overlays,
            'your_overlays' => $this->yourOverlays($instance, $overlays),
            'ingredients' => $instance->ingredients ?? [],
            'services' => $connectable,
            'test_guide' => ServiceTestGuides::firstFor($services),
            'landed' => $this->landed($instance, $firing),
            'removes' => $this->installer->removals($instance),
        ];
    }

    /**
     * For a product that installs an alert and no static overlay: what the
     * OBS beat can say. The alert renders inside every static overlay the
     * person has in OBS, and an overlay link is per account, so the only
     * sign of which overlays OBS actually loads is the access log: the
     * slugs a link has served lately. When that names any, the beat says
     * the alert already shows inside them and offers nothing; otherwise it
     * offers the five most recently edited, and says how many more there
     * are. A product with a stage of its own gets an empty answer.
     *
     * @param  list<array<string, mixed>>  $overlays
     * @return array{loaded: bool, overlays: list<array{id: int, name: string}>, total: int}
     */
    private function yourOverlays(RecipeInstance $instance, array $overlays): array
    {
        $none = ['loaded' => false, 'overlays' => [], 'total' => 0];

        if (collect($overlays)->contains(fn (array $overlay) => $overlay['type'] !== 'alert')) {
            return $none;
        }

        $statics = OverlayTemplate::where('owner_id', $instance->user_id)
            ->where('type', 'static')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'slug']);

        if ($statics->isEmpty()) {
            return $none;
        }

        $served = OverlayAccessLog::query()
            ->whereIn('token_id', OverlayAccessToken::where('user_id', $instance->user_id)->select('id'))
            ->where('accessed_at', '>=', now()->subDays(30))
            ->whereNotNull('template_slug')
            ->distinct()
            ->pluck('template_slug')
            ->all();

        $loaded = $statics->filter(fn (OverlayTemplate $template) => in_array($template->slug, $served, true));
        $pick = $loaded->isNotEmpty() ? $loaded : $statics->take(5);

        return [
            'loaded' => $loaded->isNotEmpty(),
            'overlays' => $pick->map(fn (OverlayTemplate $template) => ['id' => $template->id, 'name' => $template->name])->values()->all(),
            'total' => $statics->count(),
        ];
    }

    /**
     * The latest event since the install that one of its alerts fires on, as
     * the sentence the finished band says: who, and how much. Null until one
     * arrives. Read from the stored event rather than remembered by the page,
     * so a reload after the test tip still says it landed.
     *
     * @param  list<array{service: string, event_type: string}>  $firing
     * @return array{from_name: string, formatted_amount: string, at: ?string}|null
     */
    private function landed(RecipeInstance $instance, array $firing): ?array
    {
        if ($firing === []) {
            return null;
        }

        $event = ExternalEvent::where('user_id', $instance->user_id)
            ->where('created_at', '>=', $instance->created_at)
            ->where(function ($query) use ($firing) {
                foreach ($firing as $pair) {
                    $query->orWhere(fn ($q) => $q->where('service', $pair['service'])->where('event_type', $pair['event_type']));
                }
            })
            ->latest()
            ->first();

        if (! $event) {
            return null;
        }

        $tags = $event->normalized_payload ?? [];

        return [
            'from_name' => (string) ($tags['event.from_name'] ?? ''),
            'formatted_amount' => (string) ($tags['event.formatted_amount'] ?? ''),
            'at' => $event->created_at?->toIso8601String(),
        ];
    }
}
