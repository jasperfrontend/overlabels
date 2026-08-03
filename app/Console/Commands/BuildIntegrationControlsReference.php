<?php

namespace App\Console\Commands;

use App\Services\External\ExternalServiceRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Emits the `integration-controls` reference category from the drivers.
 *
 * The reference vault is hand-authored markdown, which is fine for Twitch tags
 * (they change when Twitch changes) but wrong for integration controls: those
 * are defined in PHP, in `getAutoProvisionedControls()`, and the hand-written
 * entries had already drifted into covering 3 of 7 services with Ko-fi - the
 * flagship integration - missing entirely.
 *
 * Generating them means adding a service to ExternalServiceRegistry adds its
 * reference pages, its sitemap URLs and its rows in help-reference-index.json
 * with no separate documentation step. The output is committed rather than
 * gitignored so the diff shows up in review when a driver changes, and so every
 * existing consumer - the Blade pages, the sitemap, the JSON index, the Alt+R
 * palette's Vite glob - keeps working with no changes at all. `--check` fails
 * when the committed files no longer match the drivers.
 */
class BuildIntegrationControlsReference extends Command
{
    protected $signature = 'help:build-integration-controls {--check : Fail if the committed files are out of date, without writing}';

    protected $description = 'Generate the integration-controls reference category from the external service drivers.';

    public const CATEGORY = 'integration-controls';

    /**
     * Keys every donation-family driver provisions. Used to split a service's
     * table into "the shared schema" and "what this service adds", which is the
     * thing a template author actually needs to know before swapping a prefix.
     */
    private const SHARED_KEYS = [
        'donations_received',
        'latest_donor_name',
        'latest_donation_amount',
        'latest_donation_message',
        'latest_donation_currency',
        'total_received',
    ];

    public function handle(): int
    {
        $dir = resource_path('help/reference/'.self::CATEGORY);
        $files = $this->render();

        if ($this->option('check')) {
            return $this->check($dir, $files);
        }

        File::ensureDirectoryExists($dir);

        // Remove stale files first, so deleting a driver deletes its pages.
        foreach (File::glob($dir.'/*.md') as $existing) {
            if (! array_key_exists(basename($existing, '.md'), $files)) {
                File::delete($existing);
                $this->line('  deleted '.basename($existing));
            }
        }

        foreach ($files as $slug => $body) {
            File::put($dir."/{$slug}.md", $body);
        }

        $this->info(sprintf('Wrote %d entries to %s', count($files), $dir));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $files
     */
    private function check(string $dir, array $files): int
    {
        $stale = [];

        foreach ($files as $slug => $body) {
            $path = $dir."/{$slug}.md";
            if (! File::exists($path) || File::get($path) !== $body) {
                $stale[] = $slug.'.md';
            }
        }

        foreach (File::glob($dir.'/*.md') as $existing) {
            if (! array_key_exists(basename($existing, '.md'), $files)) {
                $stale[] = basename($existing).' (orphaned)';
            }
        }

        if ($stale !== []) {
            $this->error('Out of date: '.implode(', ', $stale));
            $this->line('Run `php artisan help:build-integration-controls` and commit the result.');

            return self::FAILURE;
        }

        $this->info(sprintf('All %d integration-controls entries are current.', count($files)));

        return self::SUCCESS;
    }

    /**
     * @return array<string, string> slug => markdown body
     */
    private function render(): array
    {
        $files = [];

        foreach (ExternalServiceRegistry::services() as $service) {
            $files[$service] = $this->renderService($service);
        }

        $files['all-integration-controls'] = $this->renderIndex();

        return $files;
    }

    private function renderService(string $service): string
    {
        $driver = ExternalServiceRegistry::driver($service);
        $controls = $driver->getAutoProvisionedControls();
        $name = ExternalServiceRegistry::displayName($service);
        $events = $driver->getSupportedEventTypes();

        $shared = array_values(array_filter($controls, fn ($c) => in_array($c['key'], self::SHARED_KEYS, true)));
        $extra = array_values(array_filter($controls, fn ($c) => ! in_array($c['key'], self::SHARED_KEYS, true)));

        $out = "# {$name} controls\n\n";
        $out .= sprintf(
            "%s provisions %d control%s when you connect it. They are filled in automatically and stay up to date; you read them, Overlabels writes them.\n\n",
            $name,
            count($controls),
            count($controls) === 1 ? '' : 's',
        );

        $out .= sprintf(
            "Reference them anywhere a tag works, using the `c:%s:` prefix.\n\n",
            $service,
        );

        if ($shared !== []) {
            $out .= $extra === []
                ? "## Controls\n\n"
                : "## The shared donation schema\n\n";
            $out .= $this->controlTable($service, $shared);
            $out .= "\n";
        }

        if ($extra !== []) {
            $out .= sprintf("## Specific to %s\n\n", $name);
            $out .= $this->controlTable($service, $extra);
            $out .= "\n";
        }

        $out .= "## Events that update them\n\n";
        $out .= $events === []
            ? "This service does not declare event types.\n\n"
            : '`'.implode('`, `', $events)."`\n\n";

        $out .= "## Notes\n\n";
        $out .= "- These are **service-managed** controls. Setting one by hand through the dashboard or the API returns a 403 - the integration owns the value.\n";
        $out .= "- Referencing one in a template declares that dependency. Someone who copies the template without {$name} connected is warned, not blocked, and it starts working the moment they connect.\n";

        if ($shared !== []) {
            $out .= "- The shared keys above are identical across every donation integration, so swapping the prefix ports a template between services. See [[all-integration-controls]].\n";
        }

        $out .= "\n".$this->footer($service);

        return $out;
    }

    private function renderIndex(): string
    {
        $services = ExternalServiceRegistry::services();

        $out = "# All integration controls\n\n";
        $out .= 'Every control that an external integration provisions and keeps up to date, across all '.count($services)." services.\n\n";

        $out .= "## The shared donation schema\n\n";
        $out .= "Every donation integration provisions the same six keys, so a template written against one service ports to another by changing the prefix alone.\n\n";
        $out .= "| Key | Type |\n|---|---|\n";
        foreach (self::SHARED_KEYS as $key) {
            $type = $this->typeOf($key) ?? 'text';
            $out .= "| `{$key}` | {$type} |\n";
        }
        $out .= "\n";

        $out .= "## Per service\n\n";
        $out .= "| Service | Prefix | Controls | Beyond the shared six |\n|---|---|---|---|\n";

        foreach ($services as $service) {
            $driver = ExternalServiceRegistry::driver($service);
            $controls = $driver->getAutoProvisionedControls();
            $extra = array_values(array_filter($controls, fn ($c) => ! in_array($c['key'], self::SHARED_KEYS, true)));
            $shared = array_values(array_filter($controls, fn ($c) => in_array($c['key'], self::SHARED_KEYS, true)));

            // A service that shares none of the six is not "extending" the
            // schema, it is a different shape entirely - say so rather than
            // dumping its whole key list into one table cell.
            if ($shared === []) {
                $extraLabel = 'does not use the shared schema';
            } elseif ($extra === []) {
                $extraLabel = 'nothing';
            } else {
                $extraLabel = implode(', ', array_map(fn ($c) => '`'.$c['key'].'`', $extra));
            }

            $out .= sprintf(
                "| [[%s]] | `c:%s:` | %d | %s |\n",
                $service,
                $service,
                count($controls),
                $extraLabel,
            );
        }

        $out .= "\n## Notes\n\n";
        $out .= "- Overlabels GPS is the odd one out: it is an integration, but it carries location and device telemetry rather than donations, so it shares none of the six keys.\n";
        $out .= "- All of these are service-managed. They are read-only to the user and to the API.\n";
        $out .= "- Every value is a string on the wire. Use a formatter pipe to present it: `[[[c:kofi:total_received|currency:EUR]]]`.\n";

        $out .= "\n".$this->footer(null);

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $controls
     */
    private function controlTable(string $service, array $controls): string
    {
        $out = "| Tag | Type | Default | Holds |\n|---|---|---|---|\n";

        foreach ($controls as $c) {
            $default = ($c['value'] ?? '') === '' ? 'empty' : '`'.$c['value'].'`';
            $out .= sprintf(
                "| `[[[c:%s:%s]]]` | %s | %s | %s |\n",
                $service,
                $c['key'],
                $c['type'] ?? 'text',
                $default,
                $c['label'] ?? $c['key'],
            );
        }

        return $out;
    }

    /**
     * Type for a shared key, taken from whichever driver defines it first, so
     * the index table does not hardcode types that live in the drivers.
     */
    private function typeOf(string $key): ?string
    {
        foreach (ExternalServiceRegistry::services() as $service) {
            foreach (ExternalServiceRegistry::driver($service)->getAutoProvisionedControls() as $c) {
                if ($c['key'] === $key) {
                    return $c['type'] ?? null;
                }
            }
        }

        return null;
    }

    private function footer(?string $service): string
    {
        $source = $service === null
            ? 'App\Services\External\ExternalServiceRegistry'
            : 'the '.ExternalServiceRegistry::displayName($service).' driver';

        return "---\n\n*Generated from {$source} by `php artisan help:build-integration-controls`. Do not edit by hand - your changes will be overwritten.*\n";
    }
}
