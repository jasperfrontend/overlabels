<?php

namespace App\Services\Recipes;

use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Models\TowerBlock;
use App\Models\User;
use App\Services\External\ExternalServiceRegistry;
use App\Services\HtmlSanitizationService;
use App\Services\TemplateDataMapperService;
use App\Support\OverlayMarkdown;
use App\Support\ProductDesigner;

/**
 * The public demo of a chat game on the homepage and its product page: the
 * product's REAL overlay, built from its recipe exactly as an install would
 * build it, with sample values instead of anyone's account.
 *
 * Nothing is written. The payload has the shape OverlayTemplateController's
 * render returns, so OverlayRenderer runs it unchanged (its `demo` mode), and
 * the page that frames it plays the designer's scenes into it. That is why
 * the demo is the product and not a drawing of it: the same template, the
 * same expressions, the same handlers.
 *
 * Only a product whose designer declares scenes has a demo: without them
 * the overlay would stand still.
 */
class ProductDemo
{
    /** Sample followers for an overlay that reads them (Follower Bowling's pins). */
    private const array SAMPLE_FOLLOWERS = ['PixelMoth', 'tea_and_raids', 'noodlebyte', 'quietfox', 'kettle_', 'moss_ttv', 'dana_plays', 'sunroof', 'bytesized', 'lurkerbee'];

    /** The avatar a pin shows without a real follower; the lane uses it itself. */
    private const string SAMPLE_AVATAR = 'https://images.overlabels.com/overlays/twitch-avatar.png';

    public function __construct(
        private readonly RecipeCatalog $catalog,
        private readonly TemplateDataMapperService $mapper,
    ) {}

    /**
     * What a page needs to frame the demo, or null when the product has none.
     *
     * @return array{product: string, name: string, scenes: list<array<string, mixed>>, stage: array{w: int, h: int}}|null
     */
    public function frame(string $slug): ?array
    {
        $manifest = $this->catalog->find($slug);
        $designer = $manifest !== null ? ProductDesigner::declared($manifest) : null;
        $scenes = $designer !== null ? ProductDesigner::scenes($designer) : [];

        if ($scenes === [] || $this->overlayFile($manifest, $designer) === null) {
            return null;
        }

        $stage = ProductDesigner::stage($designer)[0];

        return [
            'product' => $slug,
            'name' => (string) ($manifest['name'] ?? $slug),
            'scenes' => $scenes,
            'stage' => ['w' => (int) $stage['w'], 'h' => (int) $stage['h']],
        ];
    }

    /**
     * The render payload, or null when the product has no demo.
     *
     * @return array<string, mixed>|null
     */
    public function payload(string $slug): ?array
    {
        if ($this->frame($slug) === null) {
            return null;
        }

        $manifest = $this->catalog->find($slug);
        $designer = ProductDesigner::declared($manifest);
        $file = $this->overlayFile($manifest, $designer);

        // The ingredients' defaults: the overlay a one-click install makes.
        $answers = RecipeIngredients::answers($manifest, []);
        $parsed = OverlayMarkdown::parse(RecipeIngredients::fill((string) file_get_contents($file), $answers));
        $fields = HtmlSanitizationService::sanitizeTemplateFields([
            'html' => $parsed['html'] ?? '',
            'css' => $parsed['css'] ?? '',
            'head' => $parsed['head'] ?? '',
        ]);

        $caps = User::PREFERENCE_DEFAULTS['foreach_caps'];
        $template = new OverlayTemplate(['name' => $parsed['name'] ?? $manifest['name'], 'html' => $fields['html'], 'css' => $fields['css'], 'head' => $fields['head']]);
        $tags = $template->extractTemplateTags($caps);

        $now = (string) now()->timestamp;
        $data = [];
        $expressions = [];
        $webfonts = [];

        foreach ($parsed['controls'] ?? [] as $control) {
            $key = $control['key'];
            if (($control['type'] ?? '') === 'expression') {
                $expression = (string) ($control['config']['expression'] ?? '');
                $expressions[$key] = ['expression' => $expression, 'dependencies' => OverlayControl::extractExpressionDependencies($expression)];
                $data["c:{$key}"] = '';
            } else {
                $data["c:{$key}"] = (string) ($control['value'] ?? '');
                if (! empty($control['config']['webfont'])) {
                    $webfonts[] = $key;
                }
            }
            $data["c:{$key}_at"] = $now;
        }

        // The service controls an install provisions, at their starting values.
        foreach ((array) ($manifest['installs']['integrations'] ?? []) as $service) {
            if (! is_string($service) || ! ExternalServiceRegistry::has($service)) {
                continue;
            }
            foreach (ExternalServiceRegistry::driver($service)->getAutoProvisionedControls() as $control) {
                $data["c:{$service}:{$control['key']}"] = (string) ($control['value'] ?? '');
                $data["c:{$service}:{$control['key']}_at"] = $now;
            }
        }

        // The Lists an install creates, empty.
        foreach ((array) ($manifest['installs']['lists'] ?? []) as $list) {
            $data += self::emptyList((string) ($list['slug'] ?? ''));
        }

        if (in_array('tower.count', $tags, true)) {
            $data['tower.count'] = '0';
        }

        $twitch = $this->mapper->mapForTemplate(['channel_followers' => self::sampleFollowers()], $template->name, $tags, null, $caps);

        return [
            'template' => ['head' => $template->head, 'html' => $template->html, 'css' => $template->css, 'compiled_css' => null, 'tags' => $tags],
            'alert_sources' => (object) [],
            'alert_css_preload' => [],
            'alert_sound_preload' => [],
            'alerts_need_emotes' => false,
            'meta' => ['name' => $template->name, 'slug' => $slug],
            'data' => array_merge($twitch, $data),
            'timer_states' => (object) [],
            'expression_controls' => self::referencedExpressions($expressions, $tags),
            'random_controls' => [],
            'webfont_controls' => $webfonts,
            'stream_live' => true,
            'locale' => 'en-US',
            'chat_filters' => User::PREFERENCE_DEFAULTS['chat_filters'],
            'chat_window' => $caps['chat'],
            'plinko_command' => User::PREFERENCE_DEFAULTS['plinko']['command'],
            'chat_redact_command' => null,
            'checkins_window' => $caps['checkins'],
            'tower_window' => TowerBlock::WINDOW,
        ];
    }

    /**
     * The designer's overlay file, or null.
     *
     * @param  array<string, mixed>|null  $manifest
     * @param  array<string, mixed>|null  $designer
     */
    private function overlayFile(?array $manifest, ?array $designer): ?string
    {
        $ref = $designer['overlay'] ?? null;
        foreach ((array) ($manifest['installs']['overlays'] ?? []) as $overlay) {
            if (($overlay['ref'] ?? null) === $ref && is_string($overlay['file'] ?? null)) {
                $path = RecipeInstaller::directoryFor((string) $manifest['slug']).DIRECTORY_SEPARATOR.$overlay['file'];

                return is_file($path) ? $path : null;
            }
        }

        return null;
    }

    /**
     * Only the expressions the template reads, and what they read in turn:
     * the same rule as the real render, which is why it is repeated here.
     *
     * @param  array<string, array{expression: string, dependencies: list<string>}>  $expressions
     * @param  list<string>  $tags
     * @return list<array{key: string, expression: string}>
     */
    private static function referencedExpressions(array $expressions, array $tags): array
    {
        $queue = [];
        foreach ($tags as $tag) {
            if (str_starts_with($tag, 'c:')) {
                $queue[] = substr($tag, 2);
            }
        }

        $registered = [];
        while ($queue !== []) {
            $key = array_shift($queue);
            if (isset($registered[$key]) || ! isset($expressions[$key])) {
                continue;
            }
            $registered[$key] = true;
            foreach ($expressions[$key]['dependencies'] as $dependency) {
                $queue[] = $dependency;
            }
        }

        return array_map(fn (string $key) => ['key' => $key, 'expression' => $expressions[$key]['expression']], array_keys($registered));
    }

    /** @return array<string, string> */
    private static function emptyList(string $slug): array
    {
        if ($slug === '') {
            return [];
        }

        $base = "c:list:{$slug}";

        return [
            $base => '[]', "{$base}:json" => '[]', "{$base}.count" => '0', "{$base}:count" => '0',
            "{$base}:first" => '', "{$base}:last" => '', "{$base}:empty" => '1', "{$base}:random" => '',
            "{$base}:sum" => '0', "{$base}:last_removed" => '', "{$base}:last_removed_at" => '', "{$base}:expires_at" => '',
        ];
    }

    /** @return array{total: int, data: list<array<string, string>>} */
    private static function sampleFollowers(): array
    {
        $data = [];
        foreach (self::SAMPLE_FOLLOWERS as $i => $name) {
            $data[] = [
                'user_id' => (string) (900000 + $i),
                'user_login' => strtolower($name),
                'user_name' => $name,
                'followed_at' => now()->subMinutes(5 * ($i + 1))->toIso8601String(),
                'user_profile_image_url' => self::SAMPLE_AVATAR,
            ];
        }

        return ['total' => 1234, 'data' => $data];
    }
}
