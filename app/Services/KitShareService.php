<?php

namespace App\Services;

use App\Models\Kit;
use App\Models\OverlayTemplate;
use Illuminate\Support\Collection;

/**
 * Renders a public kit as one markdown document: the kit, then every overlay
 * in it, each described exactly as its own `.md` describes it.
 *
 * Composes OverlayShareService rather than reimplementing it - a kit document
 * is N overlay documents plus a header, and the moment the two render controls
 * differently one of them is wrong.
 *
 * ## The privacy rule, which is not the same as the kit's own flag
 *
 * A kit has `is_public`, and so does every template in it, and **nothing
 * enforces any relationship between them**. `KitController::store()` validates
 * only that the templates belong to the user, so a public kit may legitimately
 * contain private templates. None do today; that is luck, not a constraint.
 *
 * So the kit flag gates the document, and each template's own flag gates
 * whether its source appears in it. A private template is listed by name and
 * type - it is part of what you get when you copy the kit, and hiding its
 * existence would misdescribe the kit - but its source, controls and triggers
 * are withheld.
 *
 * That is what lets this endpoint sit outside the auth wall that `kits.show`
 * lives behind: every byte of source in the document is already world-readable
 * at that template's own `/overlay/{slug}/public.md`. It opens no new surface.
 */
class KitShareService
{
    public function __construct(private readonly OverlayShareService $overlays) {}

    /**
     * Render the kit as one self-contained markdown document.
     */
    public function markdown(Kit $kit, string $url): string
    {
        $kit->loadMissing(['owner', 'templates.owner']);

        // Statics first: they are the thing you install, alerts fire into them,
        // and blocks are pieces of them. Reading order follows that, then name.
        $templates = $kit->templates
            ->sortBy(fn (OverlayTemplate $t) => sprintf('%d %s', $this->typeRank($t), $t->name))
            ->values();

        $shared = $templates->filter(fn (OverlayTemplate $t) => $t->is_public);
        $documents = $shared->mapWithKeys(
            fn (OverlayTemplate $t) => [$t->id => $this->overlays->document($t)]
        );

        $owner = $kit->owner?->name ?? 'an Overlabels user';

        $out = $this->frontMatter($kit, $url, $owner, $templates->count());
        $out .= $this->intro($kit, $owner, $templates, $shared->count());
        $out .= $this->contentsSection($templates);
        $out .= $this->requirementsSection($documents->all());
        $out .= $this->overlaySections($templates, $documents->all());
        $out .= $this->copySection($kit, $url);

        return $out;
    }

    private function typeRank(OverlayTemplate $template): int
    {
        return match ($template->type) {
            'static' => 0,
            'alert' => 1,
            default => 2,
        };
    }

    private function frontMatter(Kit $kit, string $url, string $owner, int $count): string
    {
        $fields = [
            'kit' => $kit->title,
            'id' => (string) $kit->id,
            'author' => $owner,
            'url' => $url,
            'overlays' => (string) $count,
            'copies' => (string) $kit->fork_count,
        ];

        $out = "---\n";
        foreach ($fields as $key => $value) {
            $out .= $key.': '.$this->overlays->yamlScalar($value)."\n";
        }

        return $out."---\n\n";
    }

    /**
     * @param  Collection<int,OverlayTemplate>  $templates
     */
    private function intro(Kit $kit, string $owner, $templates, int $sharedCount): string
    {
        $out = '# '.$kit->title."\n\n";

        if ($kit->description) {
            $out .= trim($kit->description)."\n\n";
        }

        $count = $templates->count();

        $out .= "An Overlabels **kit** by {$owner}: {$count} overlay(s) bundled so they copy into an "
            ."account together, as a set that is designed to work as one.\n\n";

        $out .= 'Overlabels overlays are plain HTML and CSS containing `[[[triple-bracket tags]]]` that '
            .'resolve against live stream data and update over WebSockets. There is no JavaScript in an '
            .'overlay - the template language does the work. The complete language specification is at '
            .'<'.config('app.url')."/llms.txt>; read it first if you are not already familiar with the syntax.\n\n";

        $out .= 'Each overlay below is described in full: its source, the controls it defines, the '
            ."integrations it needs connected, and for alerts how it is wired.\n\n";

        $withheld = $count - $sharedCount;

        if ($withheld > 0) {
            // Stated up front rather than only at the point of omission: a
            // reader planning work off this document needs to know it is
            // partial before they have read to the bottom of it.
            $out .= "**{$withheld} of the {$count} overlays in this kit are private.** They are listed, because "
                .'copying the kit copies them too, but their source is not published. Only the owner can '
                ."see it.\n\n";
        }

        return $out;
    }

    /**
     * @param  Collection<int,OverlayTemplate>  $templates
     */
    private function contentsSection($templates): string
    {
        if ($templates->isEmpty()) {
            return "## Contents\n\nThis kit is empty.\n\n";
        }

        $out = "## Contents\n\n";
        $out .= "| # | Overlay | Type | Source below |\n|---|---|---|---|\n";

        foreach ($templates as $i => $template) {
            $out .= '| '.($i + 1)
                .' | '.str_replace('|', '\|', $template->name)
                .' | '.$template->type
                .' | '.($template->is_public ? 'yes' : 'no - private')
                ." |\n";
        }

        return $out."\n";
    }

    /**
     * Integrations and Lists needed by the kit as a whole.
     *
     * Aggregated because "this kit needs Ko-fi connected" is a decision you
     * make once before installing anything, not nine times while reading.
     * Derived only from the overlays whose source is published - a private
     * overlay may well need more, which the note says plainly.
     *
     * @param  array<int,array<string,mixed>>  $documents
     */
    private function requirementsSection(array $documents): string
    {
        $services = [];
        $lists = [];

        foreach ($documents as $doc) {
            foreach ($doc['services'] as $service) {
                $services[$service['service']] = $service['label'];
            }
            foreach ($doc['lists'] as $list) {
                $lists[$list['slug']] = true;
            }
        }

        if ($services === [] && $lists === []) {
            return '';
        }

        ksort($services);
        ksort($lists);

        $out = "## What this kit needs\n\n";

        if ($services !== []) {
            $out .= 'Across its overlays, this kit reads live data from: **'.implode(', ', $services).'**. '
                .'Connect those under Settings -> Integrations before installing it; each one provisions its '
                ."own controls, which are account-wide and are not part of the copy.\n\n";
        }

        if ($lists !== []) {
            $out .= 'It also reads '.count($lists).' List(s): '
                .implode(', ', array_map(fn ($slug) => "`$slug`", array_keys($lists)))
                .". Lists hold their own data and are not copied - create one with a matching slug.\n\n";
        }

        return $out;
    }

    /**
     * @param  Collection<int,OverlayTemplate>  $templates
     * @param  array<int,array<string,mixed>>  $documents
     */
    private function overlaySections($templates, array $documents): string
    {
        if ($templates->isEmpty()) {
            return '';
        }

        $out = "## Overlays\n\n";

        foreach ($templates as $i => $template) {
            $out .= '### '.($i + 1).'. '.$template->name."\n\n";

            if ($template->description) {
                $out .= trim($template->description)."\n\n";
            }

            // THE per-template privacy gate. `$documents` is already keyed to
            // public templates only, but this early return is what actually
            // keeps a private overlay's body out of the document - do not
            // reduce it to an isset() on $documents, which reads like a
            // lookup miss rather than a deliberate refusal.
            if (! $template->is_public) {
                $out .= 'Private. This overlay is part of the kit and is copied with it, but its owner has not '
                    ."published the source, so it is not included here.\n\n";

                continue;
            }

            $doc = $documents[$template->id];

            $out .= 'Type: '.$template->type.'. Full page: <'
                .route('overlay.public', $template->slug).">\n\n";

            // Nested a level deeper than in a standalone overlay document, so
            // "Source" and "Controls" read as belonging to this overlay rather
            // than to the kit.
            $out .= $this->overlays->body($template, $doc, '####');
        }

        return $out;
    }

    private function copySection(Kit $kit, string $url): string
    {
        $page = preg_replace('/\.md$/', '', $url);

        $out = "## Copying this kit\n\n";
        $out .= "Open <$page> while logged in to Overlabels and press **Copy**. That copies every overlay in "
            .'the kit into your account in one go, including any listed above as private, along with the '
            ."controls each one defines.\n\n";
        $out .= "It does not copy the author's integrations, Lists, or event triggers - those belong to their "
            ."account. Connect the services listed above and bind your own triggers afterwards.\n";

        return $out;
    }
}
