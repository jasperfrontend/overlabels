<?php

namespace App\Services\Kits;

use App\Models\EventTemplateMapping;
use App\Models\ExternalEventTemplateMapping;
use App\Models\ExternalIntegration;
use App\Models\Kit;
use App\Models\OverlayTemplate;
use App\Models\User;
use App\Services\Alerts\AlertEventSamples;
use App\Services\TemplateDataMapperService;
use Illuminate\Support\Facades\Route;

/**
 * Everything the kit page needs to play a kit's alerts before anyone installs
 * it: each alert's source, a static overlay to play them over, and one button
 * per event the alert is for, carrying that event's sample tags.
 *
 * The page renders all of it in a sandboxed frame through the same two-pass
 * pipeline as the overlay (`renderTemplateSource`), with sample data only: the
 * static tag samples from TAG_CATALOG, the kit's own control values, and
 * AlertEventSamples for the event. Never a live value - the author's channel
 * must not leak into a stranger's preview, and the viewer's cannot exist yet.
 *
 * A template being private means its source is not SHARED (no public page, no
 * .md, no source in the kit's .md). It still renders here, because a preview
 * is using a kit, not reading it. Copying the kit hands the copier the same
 * templates anyway.
 */
final class KitPreview
{
    public function __construct(
        private readonly AlertEventSamples $samples,
        private readonly TemplateDataMapperService $mapper,
    ) {}

    /**
     * @return array<string,mixed>|null null when the kit has no alerts to play
     */
    public function build(Kit $kit, ?User $viewer): ?array
    {
        $kit->loadMissing('templates.controls');
        $alerts = $kit->templates->where('type', 'alert')->values();
        if ($alerts->isEmpty()) {
            return null;
        }

        $locale = $viewer?->locale ?: 'en-US';
        [$triggers, $declared] = $this->triggers($kit);

        $out = [];
        foreach ($triggers as $trigger) {
            $out[] = [
                ...$trigger,
                'label' => AlertEventSamples::label($trigger['service'], $trigger['event_type']),
                'variants' => $this->samples->for($trigger['service'], $trigger['event_type'], $locale),
            ];
        }

        // An alert nothing says how to trigger still plays, with a Ko-fi tip's
        // tags: the most common thing a kit alert is for, and every donation
        // field an alert usually reads.
        $covered = array_column($triggers, 'template_id');
        foreach ($alerts as $alert) {
            if (! in_array($alert->id, $covered, true)) {
                $out[] = [
                    'template_id' => $alert->id,
                    'service' => null,
                    'event_type' => null,
                    'duration_ms' => Kit::DEFAULT_TRIGGER_DURATION_MS,
                    'label' => $alert->name,
                    'variants' => array_map(
                        fn (array $v) => [...$v, 'label' => $alert->name],
                        array_slice($this->samples->for('kofi', 'donation', $locale), 0, 1),
                    ),
                ];
            }
        }

        if ($viewer !== null && $viewer->id === $kit->owner_id) {
            $out = $this->withWiring($out, $viewer);
        }

        $backdrop = $kit->templates->firstWhere('type', 'static');

        return [
            'alerts' => $alerts->map(fn (OverlayTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                ...$t->alertSource(),
                'compiled_css' => $t->compiled_css ?? '',
                'sound_url' => $t->alert_sound_url,
                'controls' => $this->controls($t),
            ])->all(),
            'backdrop' => $backdrop ? [
                'name' => $backdrop->name,
                ...$backdrop->alertSource(),
                'compiled_css' => $backdrop->compiled_css ?? '',
                'controls' => $this->controls($backdrop),
            ] : null,
            'triggers' => $out,
            'declared' => $declared,
            'sample' => $this->mapper->getSampleTemplateData(),
            'locale' => $locale,
        ];
    }

    /**
     * The kit's declared triggers; for a kit that declares none (every kit made
     * before triggers existed), what its author's own Triggers point at its
     * alerts, so an older kit previews too.
     *
     * @return array{0:list<array{template_id:int,service:string,event_type:string,duration_ms:int}>,1:bool}
     */
    private function triggers(Kit $kit): array
    {
        $declared = $kit->alertTriggers();
        if ($declared !== []) {
            return [$declared, true];
        }

        $alertIds = $kit->templates->where('type', 'alert')->pluck('id')->all();
        $seen = [];
        $out = [];

        $rows = EventTemplateMapping::where('user_id', $kit->owner_id)->whereIn('template_id', $alertIds)->orderBy('id')->get()
            ->map(fn ($m) => ['template_id' => (int) $m->template_id, 'service' => AlertEventSamples::TWITCH, 'event_type' => $m->event_type, 'duration_ms' => (int) $m->duration_ms])
            ->concat(ExternalEventTemplateMapping::where('user_id', $kit->owner_id)->whereIn('overlay_template_id', $alertIds)->orderBy('id')->get()
                ->map(fn ($m) => ['template_id' => (int) $m->overlay_template_id, 'service' => $m->service, 'event_type' => $m->event_type, 'duration_ms' => (int) $m->duration_ms]));

        foreach ($rows as $row) {
            $key = "{$row['service']}:{$row['event_type']}";
            if (isset($seen[$key]) || ! AlertEventSamples::isKnown($row['service'], $row['event_type'])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $row;
        }

        return [$out, false];
    }

    /**
     * For the kit's owner: is each trigger actually on their Triggers page,
     * and is the service it comes from connected. This is how a copier sees
     * what the copy wired and what is left to do.
     *
     * @param  list<array<string,mixed>>  $triggers
     * @return list<array<string,mixed>>
     */
    private function withWiring(array $triggers, User $viewer): array
    {
        $connected = ExternalIntegration::where('user_id', $viewer->id)->where('enabled', true)->pluck('service')->all();

        foreach ($triggers as &$trigger) {
            if ($trigger['service'] === null) {
                continue;
            }

            $existing = $trigger['service'] === AlertEventSamples::TWITCH
                ? EventTemplateMapping::with('template')->where('user_id', $viewer->id)->where('event_type', $trigger['event_type'])->get()
                    ->first(fn ($m) => $m->template !== null)
                : ExternalEventTemplateMapping::with('template')->where('user_id', $viewer->id)
                    ->where('service', $trigger['service'])->where('event_type', $trigger['event_type'])->get()
                    ->first(fn ($m) => $m->template !== null);

            $templateId = $existing === null ? null : (int) ($existing->template_id ?? $existing->overlay_template_id);
            $route = "settings.integrations.{$trigger['service']}.show";

            $trigger['wiring'] = [
                'state' => $existing === null ? 'free' : ($templateId === $trigger['template_id'] ? ($existing->enabled ? 'wired' : 'off') : 'other'),
                'other_alert' => $existing !== null && $templateId !== $trigger['template_id'] ? $existing->template->name : null,
                'connected' => $trigger['service'] === AlertEventSamples::TWITCH ? true : in_array($trigger['service'], $connected, true),
                'connect_url' => Route::has($route) ? route($route) : null,
            ];
        }

        return $triggers;
    }

    /** @return list<array<string,mixed>> */
    private function controls(OverlayTemplate $template): array
    {
        return $template->controls
            ->whereNull('source')
            ->map(fn ($c) => ['key' => $c->key, 'type' => $c->type, 'value' => $c->value, 'config' => $c->config])
            ->values()
            ->all();
    }
}
