<?php

namespace App\Services;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\OverlayControl;
use App\Models\OverlayTemplate;
use App\Services\External\ExternalServiceRegistry;
use App\Support\Dsl;

/**
 * Builds the complete, shareable description of one public overlay.
 *
 * The public preview page used to ship `head`, `html` and `css` and nothing
 * else, which is why neither a human nor an LLM could understand a shared
 * overlay: the render payload is assembled from control rows, and the share
 * surface carried none of them. This service is the missing half.
 *
 * It is deliberately the same shape of answer that OverlayTemplate::fork()
 * already stashes in `_sourceControls` / `_requiredServices` for the fork
 * wizard - "everything this overlay is" - except published up front, before
 * you commit to copying, and rendered for a reader rather than a wizard.
 *
 * ## What is and is not in the document
 *
 * Control DEFINITIONS are shared; control VALUES are shared only for controls
 * the overlay itself defines. A `source_managed` control holds live data from
 * someone's connected account - `latest_donor_name` is a real person's name,
 * `total_received` is revenue - so those rows are never read here at all.
 * Service controls are described from the driver's getAutoProvisionedControls(),
 * which is a canonical, user-independent definition. Nothing about the owner's
 * account reaches this document.
 *
 * The split is not arbitrary: it is exactly what Kit::fork() persists
 * (`whereNull('source')`, values included). Anything the document shows under
 * "controls" is something a copy actually gives you, so the document cannot
 * promise more than the Copy button delivers.
 */
class OverlayShareService
{
    /**
     * Tag namespaces that are not external services. `c:list:<slug>` reads a
     * List; it is a dependency, but not an integration to connect.
     */
    private const string LIST_NAMESPACE = 'list';

    /**
     * Build the structured share document.
     *
     * Consumed by both surfaces: the Vue public preview renders it as panels,
     * markdown() renders it as prose. One source of truth, so the page and the
     * .md can never disagree about what an overlay needs.
     *
     * @return array<string,mixed>
     */
    public function document(OverlayTemplate $template): array
    {
        $referenced = $this->referencedKeys($template);

        return [
            'controls' => $this->controls($template, $referenced['controls']),
            'services' => $this->services($template, $referenced['controls']),
            'lists' => $this->lists($referenced['lists']),
            'dataTags' => $referenced['data'],
            'alert' => $this->alert($template),
            'blocks' => $this->blocks($template),
        ];
    }

    /**
     * Every tag key written into head/html/css, split by what it addresses.
     *
     * Scans all three fields rather than reusing extractTemplateTags(), which
     * covers html+css only (head is normally fonts) and expands foreach bodies
     * into indexed data keys. Neither suits us: a `[[[c:...]]]` inside a
     * `<style>` in head is legitimate, and an expanded loop would list
     * `subscribers.0.name` ten times where the author wrote one tag.
     *
     * @return array{controls: string[], lists: string[], data: string[]}
     */
    private function referencedKeys(OverlayTemplate $template): array
    {
        $pattern = Dsl::tagKeyPattern();
        $keys = [];

        foreach (['head', 'html', 'css'] as $field) {
            preg_match_all($pattern, $template->{$field} ?? '', $matches);
            $keys = array_merge($keys, $matches[1] ?? []);
        }

        $controls = [];
        $lists = [];
        $data = [];

        foreach (array_unique($keys) as $key) {
            $segments = Dsl::segments($key);

            if (($segments[0] ?? null) !== 'c' || count($segments) < 2) {
                $data[] = $key;

                continue;
            }

            if (count($segments) >= 3 && $segments[1] === self::LIST_NAMESPACE) {
                $lists[] = $segments[2];

                continue;
            }

            // Everything after `c:` is the control address: `foo` for a plain
            // control, `kofi:donations_received` for a service one.
            $controls[] = implode(':', array_slice($segments, 1));
        }

        sort($data);
        sort($lists);

        return [
            'controls' => array_values(array_unique($controls)),
            'lists' => array_values(array_unique($lists)),
            'data' => array_values(array_unique($data)),
        ];
    }

    /**
     * The controls this overlay defines, which a copy recreates.
     *
     * Scoped to `source === null` to match Kit::fork exactly. Service-managed
     * rows are reported under services() from their driver definition instead,
     * so no row carrying live account data is ever read.
     *
     * @param  string[]  $referenced  Control addresses found in the source.
     * @return array<int,array<string,mixed>>
     */
    private function controls(OverlayTemplate $template, array $referenced): array
    {
        return $template->controls()
            ->whereNull('source')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (OverlayControl $control) => [
                'key' => $control->key,
                'tag' => 'c:'.$control->key,
                'label' => $control->label,
                'description' => $control->description,
                'type' => $control->type,
                // Safe to ship: an overlay-defined control's value is the
                // author's design default, and it is what Kit::fork copies.
                'value' => $control->value,
                'config' => $this->publicConfig($control),
                'used' => $this->isReferenced($control->key, $referenced),
            ])
            ->values()
            ->all();
    }

    /**
     * Strip runtime state out of a control's config, keeping the parts that
     * describe how it behaves.
     *
     * A timer's `started_at` is when the owner last started their timer, not a
     * property of the overlay. Same reasoning as omitting service values.
     *
     * @return array<string,mixed>|null
     */
    private function publicConfig(OverlayControl $control): ?array
    {
        $config = $control->config;

        if (! is_array($config) || $config === []) {
            return null;
        }

        unset($config['started_at'], $config['paused_at'], $config['elapsed']);

        return $config === [] ? null : $config;
    }

    /**
     * Whether a control key is actually written into the template source.
     *
     * Tolerates the `_at` companion: every control gets an automatic
     * `<key>_at` timestamp key at render time, so a template referencing only
     * `[[[c:foo_at]]]` is still using control `foo`.
     *
     * @param  string[]  $referenced
     */
    private function isReferenced(string $key, array $referenced): bool
    {
        return in_array($key, $referenced, true)
            || in_array($key.'_at', $referenced, true);
    }

    /**
     * Integrations this overlay needs connected before it renders anything.
     *
     * Definitions come from the driver, never from the owner's provisioned
     * rows, so this describes the service rather than the account.
     *
     * @param  string[]  $referenced
     * @return array<int,array<string,mixed>>
     */
    private function services(OverlayTemplate $template, array $referenced): array
    {
        $used = [];

        foreach ($referenced as $address) {
            $segments = Dsl::segments($address);

            if (count($segments) < 2 || ! ExternalServiceRegistry::has($segments[0])) {
                continue;
            }

            $used[$segments[0]][] = implode(':', array_slice($segments, 1));
        }

        // Template-scoped service controls (a service control provisioned
        // against this template rather than user-wide) count as a requirement
        // too, even when the key is not written into the source yet.
        $template->controls()
            ->whereNotNull('source')
            ->get()
            ->each(function (OverlayControl $control) use (&$used) {
                if (ExternalServiceRegistry::has($control->source)) {
                    $used[$control->source][] = $control->key;
                }
            });

        $services = [];

        foreach ($used as $service => $keys) {
            $definitions = collect(ExternalServiceRegistry::driver($service)->getAutoProvisionedControls())
                ->keyBy('key');

            $services[] = [
                'service' => $service,
                'label' => ExternalServiceRegistry::displayName($service),
                'controls' => collect($keys)
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(function (string $key) use ($service, $definitions) {
                        // `<key>_at` is synthesised per control at render time,
                        // so resolve it back to the control it timestamps.
                        $base = $definitions->has($key)
                            ? $key
                            : (str_ends_with($key, '_at') ? substr($key, 0, -3) : $key);
                        $definition = $definitions->get($base);

                        return [
                            'key' => $key,
                            'tag' => "c:$service:$key",
                            'label' => $definition['label'] ?? null,
                            'type' => $definition['type'] ?? null,
                            // A key the driver does not provision: either a
                            // typo, or written against a newer version of the
                            // driver than this one.
                            'known' => $definition !== null,
                        ];
                    })
                    ->all(),
            ];
        }

        usort($services, fn ($a, $b) => strcmp($a['service'], $b['service']));

        return $services;
    }

    /**
     * Lists the overlay reads. Only the slug is knowable from the source - the
     * list itself belongs to whoever copies the overlay, and is not shared.
     *
     * @param  string[]  $slugs
     * @return array<int,array<string,string>>
     */
    private function lists(array $slugs): array
    {
        return collect($slugs)
            ->map(fn (string $slug) => [
                'slug' => $slug,
                'tag' => 'c:list:'.$slug,
            ])
            ->all();
    }

    /**
     * Alert-only configuration.
     *
     * Splits cleanly in two, and the document must keep them apart:
     *   - the fields on the template row (sound, TTS, bot message) ARE
     *     replicated by fork(), so a copy behaves the same;
     *   - the triggers are EventTemplateMapping rows owned by the author and
     *     are NOT copied. They are included because they are the single best
     *     explanation of an alert's markup - they are why `[[[event.bits]]]`
     *     is in there - but they must be labelled as the author's wiring.
     *
     * @return array<string,mixed>|null
     */
    private function alert(OverlayTemplate $template): ?array
    {
        if ($template->type !== 'alert') {
            return null;
        }

        return [
            'sound_url' => $template->alert_sound_url,
            'tts_message' => $template->tts_message,
            'tts_delay_ms' => $template->tts_delay_ms,
            'chat_message' => $template->chat_message,
            'triggers' => $this->triggers($template),
        ];
    }

    /**
     * How the author wired this alert. Not copied - descriptive only.
     *
     * @return array<int,array<string,mixed>>
     */
    private function triggers(OverlayTemplate $template): array
    {
        $twitch = EventTemplateMapping::where('user_id', $template->owner_id)
            ->where('template_id', $template->id)
            ->where('enabled', true)
            ->get(['event_type', 'condition_type', 'condition_value', 'duration_ms'])
            ->map(fn (EventTemplateMapping $m) => [
                'source' => 'twitch',
                'label' => EventTemplateMapping::EVENT_TYPES[$m->event_type] ?? $m->event_type,
                'event_type' => $m->event_type,
                'condition_type' => $m->condition_type,
                'condition_value' => $m->condition_value,
                'duration_ms' => $m->duration_ms,
            ]);

        $external = ExternalEventTemplateMapping::where('user_id', $template->owner_id)
            ->where('overlay_template_id', $template->id)
            ->where('enabled', true)
            ->get(['service', 'event_type', 'condition_type', 'condition_value', 'duration_ms'])
            ->map(fn (ExternalEventTemplateMapping $m) => [
                'source' => $m->service,
                'label' => ExternalServiceRegistry::displayName($m->service).' '.$m->event_type,
                'event_type' => $m->event_type,
                'condition_type' => $m->condition_type,
                'condition_value' => $m->condition_value,
                'duration_ms' => $m->duration_ms,
            ]);

        return $twitch->concat($external)->values()->all();
    }

    /**
     * Names of the Builder blocks a composed overlay was assembled from.
     *
     * Context only. The compiled output already lives in head/html/css - the
     * render pipeline reads nothing else - so the shared source is complete
     * whether or not the Builder made it.
     *
     * @return string[]
     */
    private function blocks(OverlayTemplate $template): array
    {
        if (! $template->isBuilderComposed()) {
            return [];
        }

        return collect($template->metadata['builder']['placements'] ?? [])
            ->pluck('block_name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Markdown rendering
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Render the whole overlay as one self-contained markdown document.
     *
     * The point of this endpoint is that one fetch is enough for a language
     * model to understand an overlay completely, so it leads with what the
     * thing is, links the tag-language spec, and never assumes the reader has
     * seen any other page.
     */
    public function markdown(OverlayTemplate $template, string $url): string
    {
        $doc = $this->document($template);
        $owner = $template->owner?->name ?? 'an Overlabels user';

        $out = $this->frontMatter($template, $url, $owner);
        $out .= $this->intro($template, $doc, $owner);
        $out .= $this->body($template, $doc, '##');
        $out .= $this->copySection($template, $url);

        return $out;
    }

    /**
     * The sections describing one overlay, at a caller-chosen heading depth.
     *
     * Split out so a kit document can nest whole overlays underneath its own
     * headings. The depth is threaded through as a parameter rather than
     * post-processed with a regex: template CSS is full of lines that start
     * with `#`, and shifting headings by pattern would rewrite an id selector
     * inside a fenced code block into a heading.
     *
     * @param  array<string,mixed>  $doc  Result of document().
     */
    public function body(OverlayTemplate $template, array $doc, string $h): string
    {
        return $this->sourceSection($template, $h)
            .$this->controlsSection($doc['controls'], $h)
            .$this->requirementsSection($doc['services'], $doc['lists'], $h)
            .$this->alertSection($doc['alert'], $h)
            .$this->dataTagsSection($doc['dataTags'], $h);
    }

    private function frontMatter(OverlayTemplate $template, string $url, string $owner): string
    {
        $fields = [
            'name' => $template->name,
            'slug' => $template->slug,
            'type' => $template->type,
            'author' => $owner,
            'url' => $url,
            'copies' => (string) $template->fork_count,
        ];

        $out = "---\n";
        foreach ($fields as $key => $value) {
            $out .= $key.': '.$this->yamlScalar((string) $value)."\n";
        }

        return $out."---\n\n";
    }

    /**
     * Quote anything YAML would misread. Overlay names are free text, so a
     * name containing a colon would otherwise produce an invalid mapping.
     *
     * Public because KitShareService writes front matter of its own and a
     * duplicated escaping helper is how the two end up disagreeing.
     */
    public function yamlScalar(string $value): string
    {
        if ($value === '' || preg_match('/[:#\'"\[\]{}&*?|<>=!%@`,\n]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"';
        }

        return $value;
    }

    private function intro(OverlayTemplate $template, array $doc, string $owner): string
    {
        $typeLabel = match ($template->type) {
            'alert' => 'alert template',
            'block' => 'Builder block',
            default => 'static overlay',
        };

        $out = '# '.$template->name."\n\n";

        if ($template->description) {
            $out .= trim($template->description)."\n\n";
        }

        $out .= "An Overlabels **{$typeLabel}** by {$owner}.\n\n";
        $out .= 'Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that '
            .'resolve against live stream data and update over WebSockets. There is no JavaScript in an '
            .'overlay - the template language does the work. The complete language specification is at '
            .'<'.config('app.url')."/llms.txt>; read it first if you are not already familiar with the syntax.\n\n";

        $out .= match ($template->type) {
            'alert' => 'This is an alert: it renders once when a stream event fires, for a set duration, then '
                ."disappears. On top of everything a static overlay sees, its tags can read `event.*`.\n\n",
            'block' => 'This is a Builder block: a reusable fragment that is composed into a static overlay on a '
                ."CSS grid rather than added to OBS on its own. Its CSS is scoped when placed.\n\n",
            default => "This is a static overlay: it stays on screen and updates continuously.\n\n",
        };

        if ($doc['blocks'] !== []) {
            $out .= 'Composed in the Builder from '.count($doc['blocks']).' block(s): '
                .implode(', ', array_map(fn ($b) => "*$b*", $doc['blocks']))
                .". The compiled result is the source below, which is complete on its own.\n\n";
        }

        return $out;
    }

    private function sourceSection(OverlayTemplate $template, string $h): string
    {
        $out = $h." Source\n\n";
        $out .= "The three fields below are the entire overlay. Nothing else is rendered.\n\n";

        foreach ([
            'head' => ['html', 'Fonts and `<style>` blocks. No scripts - they are stripped on save.'],
            'html' => ['html', 'The markup.'],
            'css' => ['css', 'The stylesheet. Tags work in here too.'],
        ] as $field => [$lang, $note]) {
            $content = trim((string) $template->{$field});

            $out .= $h."# `$field`\n\n";

            if ($content === '') {
                $out .= "Empty.\n\n";

                continue;
            }

            $out .= $note."\n\n".$this->fence($content, $lang)."\n";
        }

        return $out;
    }

    /**
     * Wrap content in a fence long enough to survive backticks inside it.
     * Template HTML can legitimately contain a markdown code fence in a
     * comment, which with a fixed ``` would truncate the document.
     */
    private function fence(string $content, string $language): string
    {
        preg_match_all('/`+/', $content, $runs);
        $longest = max(array_map('strlen', $runs[0] ?: ['']));
        $fence = str_repeat('`', max(3, $longest + 1));

        return $fence.$language."\n".$content."\n".$fence."\n";
    }

    private function controlsSection(array $controls, string $h): string
    {
        $out = $h." Controls\n\n";

        if ($controls === []) {
            $out .= "This overlay defines no controls of its own.\n\n";

            return $out;
        }

        $out .= 'Controls are named, live-updatable values the overlay reads with `[[[c:<key>]]]`. These '
            .count($controls).' are defined by the overlay itself and are recreated, with the default values '
            ."shown, for anyone who copies it.\n\n";

        $out .= "| Tag | Type | Label | Default | Referenced in source |\n";
        $out .= "|---|---|---|---|---|\n";

        foreach ($controls as $control) {
            $out .= '| `[[['.$control['tag'].']]]` '
                .'| '.$control['type'].' '
                .'| '.$this->cell($control['label']).' '
                .'| '.$this->cell($control['value'], code: true).' '
                .'| '.($control['used'] ? 'yes' : 'no')." |\n";
        }

        $out .= "\n";

        // An expression control's formula lives in config, not value, so the
        // table above shows it blank. On a maths-driven overlay the formulas
        // ARE the overlay - omitting them would leave a document that looks
        // complete while missing the only part worth reading.
        $detailed = array_filter(
            $controls,
            fn ($c) => ! empty($c['description'])
                || ! empty($c['config']['expression'])
                || $this->behaviourConfig($c) !== [],
        );

        if ($detailed !== []) {
            $out .= $h."# Control detail\n\n";

            foreach ($detailed as $control) {
                $out .= '- `'.$control['tag'].'`';

                // One list item per control, so a description stays on one
                // line - a newline here would end the item and orphan the
                // expression below it.
                if (! empty($control['description'])) {
                    $out .= ' - '.preg_replace('/\s*\n\s*/', ' ', trim($control['description']));
                }

                $out .= "\n";

                if (! empty($control['config']['expression'])) {
                    $out .= '  - expression: '.$this->inlineCode($control['config']['expression'])."\n";
                }

                $behaviour = $this->behaviourConfig($control);

                if ($behaviour !== []) {
                    $pairs = [];
                    foreach ($behaviour as $key => $value) {
                        $pairs[] = $key.'='.(is_scalar($value) ? var_export($value, true) : json_encode($value));
                    }
                    $out .= '  - '.implode(', ', $pairs)."\n";
                }
            }

            $out .= "\n";
        }

        $out .= 'Every control also exposes a companion `[[[c:<key>_at]]]` holding the Unix timestamp in '
            ."seconds of when it last changed.\n\n";

        return $out;
    }

    /**
     * Config keys worth printing as behaviour: everything except the ones that
     * already get their own line, and `dependencies`, which only restates what
     * the expression itself shows.
     *
     * @param  array<string,mixed>  $control
     * @return array<string,mixed>
     */
    private function behaviourConfig(array $control): array
    {
        $config = $control['config'] ?? [];

        if (! is_array($config)) {
            return [];
        }

        unset($config['expression'], $config['dependencies']);

        return $config;
    }

    private function requirementsSection(array $services, array $lists, string $h): string
    {
        if ($services === [] && $lists === []) {
            return '';
        }

        $out = $h." Requirements\n\n";

        if ($services !== []) {
            $names = implode(', ', array_column($services, 'label'));
            $out .= "This overlay reads live data from: **$names**. Those integrations must be connected under "
                .'Settings -> Integrations before the tags below resolve to anything. Connecting a service '
                .'provisions its controls automatically - they are not part of the copy, and they are '
                ."account-wide rather than per-overlay.\n\n";

            foreach ($services as $service) {
                $out .= $h.'# '.$service['label']."\n\n";
                $out .= "| Tag | Type | Control |\n|---|---|---|\n";

                foreach ($service['controls'] as $control) {
                    $label = $control['known']
                        ? $this->cell($control['label'])
                        : 'not provisioned by this service - likely a typo';

                    $out .= '| `[[['.$control['tag'].']]]` '
                        .'| '.($control['type'] ?? '?').' '
                        .'| '.$label." |\n";
                }

                $out .= "\n";
            }
        }

        if ($lists !== []) {
            $out .= $h."# Lists\n\n";
            $out .= 'This overlay reads '.count($lists).' List(s). Lists hold their own data and are not '
                ."copied with an overlay - create one with a matching slug under /dashboard/lists.\n\n";

            foreach ($lists as $list) {
                $out .= '- `[[['.$list['tag'].']]]` - list slug `'.$list['slug']."`\n";
            }

            $out .= "\n";
        }

        return $out;
    }

    private function alertSection(?array $alert, string $h): string
    {
        if ($alert === null) {
            return '';
        }

        $out = $h." Alert behaviour\n\n";

        $configured = false;

        if ($alert['sound_url']) {
            $out .= '- Plays a sound on fire: <'.$alert['sound_url'].'>. Sound is a template field, not markup: '
                ."`<audio>` in an overlay is stripped on save, because one element per alert stacks overlapping playback.\n";
            $configured = true;
        }

        if ($alert['tts_message']) {
            $delay = $alert['tts_delay_ms'] ? " after a {$alert['tts_delay_ms']}ms delay" : '';
            $out .= "- Speaks via text to speech{$delay}: ".$this->inlineCode($alert['tts_message'])."\n";
            $configured = true;
        }

        if ($alert['chat_message']) {
            $out .= '- Posts to Twitch chat via the @overlabels bot: '.$this->inlineCode($alert['chat_message'])."\n";
            $configured = true;
        }

        if (! $configured) {
            $out .= "No sound, text to speech or chat message configured. This alert is purely visual.\n";
        }

        $out .= "\n";

        if ($alert['triggers'] !== []) {
            $out .= $h."# How the author wired it\n\n";
            $out .= "Triggers belong to the author's account and are **not** copied - you bind your own after "
                ."copying. They are listed because they are the clearest explanation of what the markup expects.\n\n";
            $out .= "| Fires on | Condition | Duration |\n|---|---|---|\n";

            foreach ($alert['triggers'] as $trigger) {
                $condition = $trigger['condition_type'] && $trigger['condition_type'] !== 'any'
                    ? str_replace('_', ' ', $trigger['condition_type']).' '.$trigger['condition_value']
                    : 'any';

                $out .= '| '.$this->cell($trigger['label'])
                    .' | '.$condition
                    .' | '.$trigger['duration_ms']."ms |\n";
            }

            $out .= "\n";
        }

        return $out;
    }

    private function dataTagsSection(array $tags, string $h): string
    {
        if ($tags === []) {
            return '';
        }

        $out = $h." Live data tags used\n\n";
        $out .= 'Beyond its controls, this overlay reads '.count($tags).' data tag(s). These resolve against '
            .'Twitch channel data and, for alerts, the firing event. A tag with no data renders as nothing '
            ."unless it carries a `?? default`.\n\n";

        foreach ($tags as $tag) {
            $out .= '- `[[['.$tag.']]]`'."\n";
        }

        return $out."\n";
    }

    private function copySection(OverlayTemplate $template, string $url): string
    {
        $page = preg_replace('/\.md$/', '', $url);

        $out = "## Copying this overlay\n\n";
        $out .= "Open <$page> while logged in to Overlabels and press **Copy**. That creates your own editable "
            .'copy of the source above, along with the controls listed under Controls. It does not copy the '
            ."author's integrations, Lists";

        $out .= $template->type === 'alert' ? ", or event triggers.\n\n" : ".\n\n";

        $out .= 'You can also paste the source into a new overlay by hand at '
            .'<'.config('app.url')."/templates/create>.\n";

        return $out;
    }

    /**
     * Escape a value for a markdown table cell. Pipes would otherwise split
     * the row, and a control default is arbitrary user text.
     */
    private function cell(?string $value, bool $code = false): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $value = str_replace(['|', "\n"], ['\|', ' '], trim($value));

        return $code ? $this->inlineCode($value) : $value;
    }

    /**
     * An inline code span that survives backticks in the content: the
     * delimiter is one backtick longer than any run inside, padded with a
     * space when the content starts or ends with one (the CommonMark rule).
     * The same shape the fence() helper uses for blocks, so the importer can
     * unwrap both the same way.
     */
    private function inlineCode(string $value): string
    {
        preg_match_all('/`+/', $value, $runs);
        $longest = max(array_map('strlen', $runs[0] ?: ['']));
        $delimiter = str_repeat('`', $longest + 1);
        $pad = str_starts_with($value, '`') || str_ends_with($value, '`') ? ' ' : '';

        return $delimiter.$pad.$value.$pad.$delimiter;
    }
}
