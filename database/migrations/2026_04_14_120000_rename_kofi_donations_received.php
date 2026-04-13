<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normalize Ko-fi's control key from `kofis_received` to `donations_received`,
     * matching the naming StreamLabs and StreamElements already use. Also rename
     * the corresponding settings JSON keys (`kofis_seed_set` / `kofis_seed_value`)
     * to `donations_seed_set` / `donations_seed_value`, and rewrite any stored
     * template bodies, expression values, and expression dependency lists that
     * reference the old key.
     */
    public function up(): void
    {
        DB::table('overlay_controls')
            ->where('source', 'kofi')
            ->where('key', 'kofis_received')
            ->update(['key' => 'donations_received']);

        $expressionControls = DB::table('overlay_controls')
            ->where('type', 'expression')
            ->get();

        foreach ($expressionControls as $row) {
            $dirty = false;
            $fields = [];

            if (is_string($row->value) && str_contains($row->value, 'c.kofi.kofis_received')) {
                $fields['value'] = str_replace('c.kofi.kofis_received', 'c.kofi.donations_received', $row->value);
                $dirty = true;
            }

            $config = is_string($row->config) ? json_decode($row->config, true) : $row->config;
            if (is_array($config) && isset($config['dependencies']) && is_array($config['dependencies'])) {
                $newDeps = array_values(array_map(
                    static fn ($d) => $d === 'kofi:kofis_received' ? 'kofi:donations_received' : $d,
                    $config['dependencies']
                ));
                if ($newDeps !== $config['dependencies']) {
                    $config['dependencies'] = $newDeps;
                    $fields['config'] = json_encode($config);
                    $dirty = true;
                }
            }

            if ($dirty) {
                DB::table('overlay_controls')->where('id', $row->id)->update($fields);
            }
        }

        $tagOld = '[[[c:kofi:kofis_received]]]';
        $tagNew = '[[[c:kofi:donations_received]]]';

        foreach (DB::table('overlay_templates')->get() as $row) {
            $dirty = false;
            $fields = [];

            foreach (['html', 'css', 'js'] as $column) {
                $value = $row->{$column};
                if (is_string($value) && str_contains($value, $tagOld)) {
                    $fields[$column] = str_replace($tagOld, $tagNew, $value);
                    $dirty = true;
                }
            }

            if (is_string($row->template_tags) && str_contains($row->template_tags, 'c:kofi:kofis_received')) {
                $fields['template_tags'] = str_replace(
                    'c:kofi:kofis_received',
                    'c:kofi:donations_received',
                    $row->template_tags
                );
                $dirty = true;
            }

            if ($dirty) {
                DB::table('overlay_templates')->where('id', $row->id)->update($fields);
            }
        }

        foreach (DB::table('external_integrations')->where('service', 'kofi')->get() as $row) {
            $settings = is_string($row->settings) ? json_decode($row->settings, true) : $row->settings;
            if (! is_array($settings)) {
                continue;
            }

            $dirty = false;
            if (array_key_exists('kofis_seed_set', $settings)) {
                $settings['donations_seed_set'] = $settings['kofis_seed_set'];
                unset($settings['kofis_seed_set']);
                $dirty = true;
            }
            if (array_key_exists('kofis_seed_value', $settings)) {
                $settings['donations_seed_value'] = $settings['kofis_seed_value'];
                unset($settings['kofis_seed_value']);
                $dirty = true;
            }

            if ($dirty) {
                DB::table('external_integrations')
                    ->where('id', $row->id)
                    ->update(['settings' => json_encode($settings)]);
            }
        }
    }

    public function down(): void
    {
        DB::table('overlay_controls')
            ->where('source', 'kofi')
            ->where('key', 'donations_received')
            ->update(['key' => 'kofis_received']);
    }
};
