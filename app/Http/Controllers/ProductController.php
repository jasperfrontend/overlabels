<?php

namespace App\Http\Controllers;

use App\Events\ControlValueUpdated;
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
use App\Support\BunnyFonts;
use App\Support\ChatDesigner;
use App\Support\ChatPresets;
use App\Support\OverlayMarkdown;
use App\Support\ProductSetup;
use App\Support\ServiceConnections;
use App\Support\ServiceTestGuides;
use App\Support\WiringFacts;
use App\Support\WiringReport;
use Illuminate\Http\JsonResponse;
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

    /**
     * The sidebar filter for what this account has installed. Not a manifest
     * category - it is per account and hidden from a visitor - so it lives
     * beside CATEGORIES rather than in it.
     */
    private const INSTALLED_FILTER = 'installed';

    public function index(Request $request): Response
    {
        $user = $request->user();
        $installed = $user ? $this->installedSlugs($user) : [];

        $all = collect($this->catalog->listed())
            ->map(fn (array $manifest) => [
                'slug' => $manifest['slug'],
                'name' => $manifest['name'],
                'description' => $manifest['description'],
                'category' => $manifest['category'] ?? null,
                'requires_bot' => (bool) ($manifest['requires_bot'] ?? false),
                'hero' => $manifest['hero'] ?? null,
                // The one service a product connects, for the card's icon
                // and its "Connects Ko-fi" line. A product connecting two
                // would need a second visual; none does.
                'service' => $manifest['installs']['integrations'][0] ?? null,
                'installs' => $this->installChips($manifest),
                'installed' => in_array($manifest['slug'], $installed, true),
            ])
            // Show all is one shelf per category in CATEGORIES order, then
            // anything uncategorised; slug order within a shelf, as before.
            ->sortBy(fn (array $product) => $this->categoryRank($product['category']), SORT_NUMERIC, false)
            ->values();

        // A category the page does not know, or Installed for a visitor, is
        // Show all: a first-timer arriving on a stale link sees the page,
        // not a 404.
        $category = $request->query('category');
        $category = is_string($category) && $this->isFilter($category, $user !== null) ? $category : null;

        $products = $all
            ->filter(fn (array $product) => match ($category) {
                null => true,
                self::INSTALLED_FILTER => $product['installed'],
                default => $product['category'] === $category,
            })
            ->values()
            ->all();

        $shelf = $category === null ? null : $this->shelfFor($category);

        view()->share('og', [
            'title' => ($shelf ? $shelf['label'].' - ' : '').'Products - Overlabels',
            'description' => $shelf['lead'] ?? 'Everything here is free, made by Overlabels, and installed with one click. Pick something for your chat to play, or connect a service you already use so its support lands on your stream.',
            'url' => $category === null ? route('products.index') : route('products.index', ['category' => $category]),
        ]);

        // Installed is this account's shelf, not the catalogue's: it renders a
        // different set for every reader and nothing for a crawler, so it must
        // not compete with the listing for the same words. A real shelf is real
        // content and stays indexable, pointing at itself.
        if ($category === self::INSTALLED_FILTER) {
            view()->share('robots', 'noindex, follow');
            view()->share('canonical', route('products.index'));
        } else {
            view()->share('canonical', $category === null ? route('products.index') : route('products.index', ['category' => $category]));
        }

        return Inertia::render('products/index', [
            'products' => $products,
            'categories' => $this->categories(),
            'category' => $category,
            'shelf' => $shelf,
            'installed_count' => $user ? $this->installedCount($user) : null,
        ]);
    }

    /**
     * The sidebar's shelves with their counts, the same on every /products
     * page: the listing filters by them, a product page marks its own.
     *
     * @return list<array{key: string, label: string, lead: string, count: int}>
     */
    private function categories(): array
    {
        $listed = collect($this->catalog->listed());

        return collect(RecipeCatalog::CATEGORIES)
            ->map(fn (array $shelf, string $key) => [
                'key' => $key,
                'label' => $shelf['label'],
                'lead' => $shelf['lead'],
                'count' => $listed->where('category', $key)->count(),
            ])
            ->values()
            ->all();
    }

    private function isFilter(string $category, bool $authed): bool
    {
        if ($category === self::INSTALLED_FILTER) {
            return $authed;
        }

        return array_key_exists($category, RecipeCatalog::CATEGORIES);
    }

    /**
     * @return array{label: string, lead: string}
     */
    private function shelfFor(string $category): array
    {
        if ($category === self::INSTALLED_FILTER) {
            return ['label' => 'Installed', 'lead' => 'What this account has installed. Each one opens on its own page.'];
        }

        return RecipeCatalog::CATEGORIES[$category];
    }

    private function categoryRank(?string $category): int
    {
        $rank = array_search($category, array_keys(RecipeCatalog::CATEGORIES), true);

        return $rank === false ? count(RecipeCatalog::CATEGORIES) : $rank;
    }

    /**
     * What an install puts in the account, as card chips: one per kind, in
     * the order a streamer meets them. An alert-type overlay says "Alert",
     * since that is what the person sees on stream, and a chat command is
     * a list appender or a bot alias - both put a `!word` in chat.
     *
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private function installChips(array $manifest): array
    {
        $installs = $manifest['installs'] ?? [];
        $chips = [];

        foreach ($installs['overlays'] ?? [] as $overlay) {
            $doc = OverlayMarkdown::parse(
                (string) file_get_contents(RecipeInstaller::directoryFor($manifest['slug']).DIRECTORY_SEPARATOR.basename($overlay['file']))
            );
            $chips[] = $doc['type'] === 'alert' ? 'Alert' : 'Overlay';
        }

        if (($installs['integrations'] ?? []) !== []) {
            $chips[] = 'Integration';
        }

        if (($installs['lists'] ?? []) !== []) {
            $chips[] = 'List';
        }

        if (($installs['list_appenders'] ?? []) !== [] || ($installs['bot_aliases'] ?? []) !== []) {
            $chips[] = 'Chat command';
        }

        return array_values(array_unique($chips));
    }

    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        if ($moved = $this->movedPermanently($slug, 'products.show')) {
            return $moved;
        }

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

        $canonical = route('products.show', $slug);

        view()->share('og', [
            'title' => $manifest['name'].' - Overlabels product',
            'description' => $manifest['description'],
            'url' => $canonical,
        ]);

        view()->share('canonical', $canonical);

        /*
         * A product IS free installable software, so SoftwareApplication is
         * the honest type: a free Offer is how schema.org says "costs
         * nothing", and it is the one thing on this page a search engine can
         * show without us claiming a rating nobody has given.
         *
         * A @graph rather than a bare node so the breadcrumb rides along in
         * the same block. The root template json_encodes whatever is shared,
         * and an assoc array is what its array_filter expects - a top-level
         * list would break the moment one entry were null.
         */
        view()->share('jsonLd', [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'SoftwareApplication',
                    '@id' => $canonical.'#product',
                    'name' => $manifest['name'],
                    'description' => $manifest['description'],
                    'url' => $canonical,
                    'applicationCategory' => 'MultimediaApplication',
                    'operatingSystem' => 'Any',
                    'softwareVersion' => (string) $manifest['version'],
                    'isAccessibleForFree' => true,
                    'image' => url($manifest['hero'] ?? '/ogimage.jpg'),
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => '0',
                        'priceCurrency' => 'EUR',
                        'availability' => 'https://schema.org/InStock',
                    ],
                    'author' => $this->publisher(),
                    'publisher' => $this->publisher(),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Products', 'item' => route('products.index')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $manifest['name'], 'item' => $canonical],
                    ],
                ],
            ],
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
                'category' => $manifest['category'] ?? null,
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
                // The product's fixed looks, with the one its overlay currently
                // holds marked. Empty for every product but Twitch Chat.
                'presets' => ChatPresets::forProduct($slug, $instance),
            ],
            'installed' => $installed,
            'categories' => $this->categories(),
            'installed_count' => $user ? $this->installedCount($user) : null,
        ]);
    }

    public function install(Request $request, string $slug): RedirectResponse
    {
        $slug = $this->canonical($slug);
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
            $this->installer->install($recipe, $user, RecipeInstance::instanceSlugFrom($slug), null, $ingredients);
        } catch (RuntimeException $e) {
            return redirect()->route('products.show', $slug)->withErrors(['install' => $e->getMessage()]);
        }

        // From here every app page carries the setup banner until the
        // product page sees nothing left, or the person says not now.
        ProductSetup::start($user, $slug);

        return redirect()->route('products.show', $slug)->with('success', $manifest['name'].' is installed.');
    }

    /**
     * One click, thirteen controls. Writes the preset onto the product's
     * overlay and broadcasts every control the way the controls tab does,
     * so OBS changes as the page reloads. The result is the card turning
     * to Applied and the overlay itself; no toast.
     */
    public function applyPreset(Request $request, string $slug, string $preset): RedirectResponse|JsonResponse
    {
        $slug = $this->canonical($slug);

        abort_unless(ChatPresets::has($slug) && isset(ChatPresets::PRESETS[$preset]), 404);

        $user = $request->user();
        $instance = $this->instanceFor($user, $slug);
        $template = $instance ? ChatPresets::overlayFor($instance) : null;
        abort_if($template === null || $template->owner_id !== $user->id, 404);

        foreach (ChatPresets::apply($template, $preset) as $control) {
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

        // The designer asks for JSON: it applies a preset without leaving the
        // page, because navigating would reload the preview frame and throw
        // away the chat that is in it. The values come back so the knobs move
        // to match in the same beat the overlay does.
        if ($request->wantsJson()) {
            return response()->json(['values' => ChatPresets::PRESETS[$preset]['values']]);
        }

        return redirect()->route('products.show', $slug);
    }

    /**
     * The chat designer: every knob on the left, the real overlay on the right.
     *
     * The preview frame is the overlay itself, on this origin, with generated
     * chat instead of the channel's own. That is deliberate on both counts. It
     * is the real renderer, so nothing here can drift from what OBS shows; and
     * a knob writes its control through the ordinary value endpoint, whose
     * broadcast the frame is already listening on - the same broadcast that
     * reaches OBS. There is no preview pipeline to keep in step, because there
     * is no preview: it is the overlay.
     *
     * 404 for any product but Twitch Chat, and for an account with no install:
     * there is nothing to design until there is an overlay to design.
     */
    public function design(Request $request, string $slug): Response|RedirectResponse
    {
        if ($moved = $this->movedPermanently($slug, 'products.design')) {
            return $moved;
        }

        abort_unless(ChatPresets::has($slug), 404);

        $user = $request->user();
        $instance = $this->instanceFor($user, $slug);
        $template = $instance ? ChatPresets::overlayFor($instance) : null;
        abort_if($template === null || $template->owner_id !== $user->id, 404);

        $manifest = $this->listedManifest($slug);

        return Inertia::render('products/design', [
            'product' => ['slug' => $manifest['slug'], 'name' => $manifest['name']],
            'overlay' => ['id' => $template->id, 'name' => $template->name, 'slug' => $template->slug],
            'preview_url' => ChatDesigner::previewUrl($template, ChatDesigner::previewToken($user)),
            'presets' => ChatDesigner::presets(),
            'skins' => ChatDesigner::skins(),
            'choices' => ChatDesigner::CHOICES,
            // The font row is a search, not a select: its vocabulary is the
            // whole Bunny Fonts catalogue. The catalogue is 118 KB, so it is
            // fetched by the picker when it opens rather than serialised into
            // this page - these six are what the row shows until then, and
            // what it falls back to if the fetch fails.
            'suggested_fonts' => ChatDesigner::SUGGESTED_FONTS,
            'fonts_url' => asset(BunnyFonts::CATALOGUE_PATH),
            'groups' => ChatDesigner::GROUPS,
            'controls' => ChatDesigner::controls($template),
            // The chat window is a foreach cap, not a control: it is "how many
            // items does this loop expand to", the same question the other four
            // caps answer, and it is written on /settings/account. The designer
            // writes the same preference through the same endpoint.
            'chat_window' => $user->foreachCaps()['chat'],
            'chat_window_max' => User::FOREACH_CAP_MAX,
            // Asked for explicitly, because chatFilters() is deliberately not
            // appended to a serialised User. Here for the same reason the
            // window cap is: a streamer deciding what their chat looks like
            // should not have to find a settings page to say "not the bot".
            'chat_filters' => $user->chatFilters(),
            'max_hidden_logins' => User::MAX_HIDDEN_LOGINS,
        ]);
    }

    public function dismissSetup(Request $request): RedirectResponse
    {
        ProductSetup::end($request->user());

        return back();
    }

    public function uninstall(Request $request, string $slug): RedirectResponse
    {
        $slug = $this->canonical($slug);
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
     * The Organization behind every product, for the structured data above.
     * Same shape as UpdateController's, which is the only other page that
     * publishes any; a third caller is when it becomes worth extracting.
     *
     * @return array<string, mixed>
     */
    private function publisher(): array
    {
        return [
            '@type' => 'Organization',
            'name' => 'Overlabels',
            'url' => url('/'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => url('/favicon.png'),
            ],
        ];
    }

    /**
     * A 301 to where the product lives now, when $slug is one it used to live
     * at. Null when the slug is current or simply unknown, which 404s below
     * exactly as it did before.
     *
     * Only the two GET routes redirect. A search engine must be told the page
     * moved, and it must be told once, so the old URL never competes with the
     * new one for the same words.
     */
    private function movedPermanently(string $slug, string $route): ?RedirectResponse
    {
        $canonical = $this->catalog->canonicalSlugFor($slug);

        return $canonical === null ? null : redirect()->route($route, $canonical, 301);
    }

    /**
     * The current slug for one taken off a URL, which may be a slug the
     * product used to live at.
     *
     * The write actions run every lookup through this. They are form posts,
     * and a page left open across a rename would otherwise submit the old
     * slug, match no `recipes` row, read as "not installed" and build the
     * account a second copy of something it already has.
     */
    private function canonical(string $slug): string
    {
        return $this->catalog->canonicalSlugFor($slug) ?? $slug;
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
    /**
     * How many LISTED products the account has installed: the number the
     * sidebar's Installed link shows, and the number of cards that filter
     * lists. An instance of an unlisted recipe (the picker recipes a seeder
     * or tinker installs) is not a product and must not count.
     */
    private function installedCount(User $user): int
    {
        return count(array_intersect($this->installedSlugs($user), array_keys($this->catalog->listed())));
    }

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
