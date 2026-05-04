<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the Overlabels mobile integration's internal service slug from
 * `overlabels-mobile` to `gps`, and strip the redundant `gps_` prefix from
 * its provisioned control keys so expressions read `c.gps.speed` instead
 * of `c.gps.gps_speed`.
 *
 * The webhook URL `/api/webhooks/overlabels-mobile/{token}` and the settings
 * page URL `/settings/integrations/overlabels-mobile` are NOT touched - those
 * are translated to the canonical service key inside the controllers via an
 * alias map. The mobile app on the streamer's phone keeps working unchanged.
 *
 * Migration scope:
 *   1. external_integrations.service: 'overlabels-mobile' → 'gps'
 *   2. external_events.service:       'overlabels-mobile' → 'gps'
 *   3. overlay_controls.source:       'overlabels-mobile' → 'gps'
 *   4. overlay_controls.key:          'gps_speed' → 'speed' etc. (only where source='gps')
 *   5. overlay_controls.config:       rewrite expression strings + dependencies arrays
 *   6. overlay_templates.html/css/js/head: rewrite [[[c:overlabels-mobile:gps_*]]] tags
 *
 * Down: full reverse.
 */
return new class extends Migration
{
    private const KEY_MAP = [
        'gps_speed' => 'speed',
        'gps_lat' => 'lat',
        'gps_lng' => 'lng',
        'gps_distance' => 'distance',
        'gps_bearing' => 'bearing',
        'gps_accuracy' => 'accuracy',
        'gps_battery' => 'battery',
        'gps_charging' => 'charging',
        'gps_tracking' => 'tracking',
        'gps_session_distance' => 'session_distance',
        'gps_session_max_speed' => 'session_max_speed',
        'gps_session_avg_speed' => 'session_avg_speed',
        'gps_session_duration' => 'session_duration',
    ];

    public function up(): void
    {
        // Safety check: a user-defined control with key='gps' (no source) would collide
        // with the new service namespace and silently shadow it inside expressions.
        $conflicts = DB::table('overlay_controls')
            ->whereNull('source')
            ->where('key', 'gps')
            ->count();

        if ($conflicts > 0) {
            throw new \RuntimeException(
                "Cannot proceed: $conflicts user-scoped control(s) have key='gps'. ".
                "The 'gps' slug is being claimed for the renamed Overlabels mobile service. ".
                "Rename those custom controls before running this migration."
            );
        }

        DB::transaction(function () {
            // 1-3. Service slug rename across event, integration, and control rows.
            DB::table('external_integrations')
                ->where('service', 'overlabels-mobile')
                ->update(['service' => 'gps']);

            DB::table('external_events')
                ->where('service', 'overlabels-mobile')
                ->update(['service' => 'gps']);

            DB::table('overlay_controls')
                ->where('source', 'overlabels-mobile')
                ->update(['source' => 'gps']);

            // 4. Strip the `gps_` prefix from provisioned control keys.
            foreach (self::KEY_MAP as $oldKey => $newKey) {
                DB::table('overlay_controls')
                    ->where('source', 'gps')
                    ->where('key', $oldKey)
                    ->update(['key' => $newKey]);
            }

            // 5. Rewrite expression controls' config (expression text + dependencies).
            $expressionRows = DB::table('overlay_controls')
                ->where('type', 'expression')
                ->whereRaw("config::text LIKE '%overlabels-mobile%' OR config::text LIKE '%gps_%'")
                ->get(['id', 'config']);

            foreach ($expressionRows as $row) {
                $config = json_decode($row->config ?? '', true) ?: [];
                $changed = false;

                if (isset($config['expression']) && is_string($config['expression'])) {
                    $rewritten = $this->rewriteExpression($config['expression']);
                    if ($rewritten !== $config['expression']) {
                        $config['expression'] = $rewritten;
                        $changed = true;
                    }
                }

                if (isset($config['dependencies']) && is_array($config['dependencies'])) {
                    $newDeps = $this->rewriteDependencies($config['dependencies']);
                    if ($newDeps !== $config['dependencies']) {
                        $config['dependencies'] = $newDeps;
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('overlay_controls')
                        ->where('id', $row->id)
                        ->update(['config' => json_encode($config)]);
                }
            }

            // 6. Rewrite template tag references in template source columns.
            $templateRows = DB::table('overlay_templates')
                ->whereRaw("(html ILIKE '%overlabels-mobile%' OR css ILIKE '%overlabels-mobile%' OR js ILIKE '%overlabels-mobile%' OR COALESCE(head, '') ILIKE '%overlabels-mobile%')")
                ->get(['id', 'html', 'css', 'js', 'head']);

            foreach ($templateRows as $tpl) {
                $updates = [];
                foreach (['html', 'css', 'js', 'head'] as $col) {
                    if ($tpl->$col === null) {
                        continue;
                    }
                    $rewritten = $this->rewriteTemplateContent($tpl->$col);
                    if ($rewritten !== $tpl->$col) {
                        $updates[$col] = $rewritten;
                    }
                }
                if (! empty($updates)) {
                    DB::table('overlay_templates')
                        ->where('id', $tpl->id)
                        ->update($updates);
                }
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            $reverseMap = array_flip(self::KEY_MAP);

            // 4. Reverse key prefix strip.
            foreach ($reverseMap as $newKey => $oldKey) {
                DB::table('overlay_controls')
                    ->where('source', 'gps')
                    ->where('key', $newKey)
                    ->update(['key' => $oldKey]);
            }

            // 1-3. Reverse service slug.
            DB::table('overlay_controls')
                ->where('source', 'gps')
                ->update(['source' => 'overlabels-mobile']);

            DB::table('external_events')
                ->where('service', 'gps')
                ->update(['service' => 'overlabels-mobile']);

            DB::table('external_integrations')
                ->where('service', 'gps')
                ->update(['service' => 'overlabels-mobile']);

            // 5. Reverse expression rewrites by re-running with reversed maps.
            $expressionRows = DB::table('overlay_controls')
                ->where('type', 'expression')
                ->whereRaw("config::text LIKE '%c.gps.%' OR config::text LIKE '%\"gps:%'")
                ->get(['id', 'config']);

            foreach ($expressionRows as $row) {
                $config = json_decode($row->config ?? '', true) ?: [];
                $changed = false;

                if (isset($config['expression']) && is_string($config['expression'])) {
                    $rewritten = $this->reverseExpression($config['expression'], $reverseMap);
                    if ($rewritten !== $config['expression']) {
                        $config['expression'] = $rewritten;
                        $changed = true;
                    }
                }

                if (isset($config['dependencies']) && is_array($config['dependencies'])) {
                    $newDeps = array_values(array_unique(array_map(function ($dep) use ($reverseMap) {
                        if (! str_starts_with($dep, 'gps:')) {
                            return $dep;
                        }
                        $key = substr($dep, 4);
                        $isAt = str_ends_with($key, '_at');
                        $base = $isAt ? substr($key, 0, -3) : $key;
                        $oldBase = $reverseMap[$base] ?? $base;
                        return 'overlabels-mobile:'.$oldBase.($isAt ? '_at' : '');
                    }, $config['dependencies'])));
                    if ($newDeps !== $config['dependencies']) {
                        $config['dependencies'] = $newDeps;
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('overlay_controls')
                        ->where('id', $row->id)
                        ->update(['config' => json_encode($config)]);
                }
            }

            // 6. Reverse template tag rewrites.
            $templateRows = DB::table('overlay_templates')
                ->whereRaw("(html ILIKE '%[[[c:gps:%' OR css ILIKE '%[[[c:gps:%' OR js ILIKE '%[[[c:gps:%' OR COALESCE(head, '') ILIKE '%[[[c:gps:%')")
                ->get(['id', 'html', 'css', 'js', 'head']);

            foreach ($templateRows as $tpl) {
                $updates = [];
                foreach (['html', 'css', 'js', 'head'] as $col) {
                    if ($tpl->$col === null) {
                        continue;
                    }
                    $rewritten = $this->reverseTemplateContent($tpl->$col, $reverseMap);
                    if ($rewritten !== $tpl->$col) {
                        $updates[$col] = $rewritten;
                    }
                }
                if (! empty($updates)) {
                    DB::table('overlay_templates')
                        ->where('id', $tpl->id)
                        ->update($updates);
                }
            }
        });
    }

    /**
     * Apply key map (handling _at suffix) to an unprefixed key.
     */
    private function mapKey(string $key): string
    {
        $isAt = str_ends_with($key, '_at');
        $base = $isAt ? substr($key, 0, -3) : $key;
        $newBase = self::KEY_MAP[$base] ?? $base;
        return $newBase.($isAt ? '_at' : '');
    }

    /**
     * Rewrite an expression: bracket notation for the old hyphenated source
     * collapses to dot notation under the new `gps` namespace.
     */
    private function rewriteExpression(string $expr): string
    {
        // c["overlabels-mobile"].keyname / c['overlabels-mobile'].keyname
        $expr = preg_replace_callback(
            '/\bc\[([\'"])overlabels-mobile\1\]\.([a-z][a-z0-9_]*)/',
            fn ($m) => 'c.gps.'.$this->mapKey($m[2]),
            $expr
        );

        // c["overlabels-mobile"]["keyname"] (single or double quoted, mix allowed)
        $expr = preg_replace_callback(
            '/\bc\[([\'"])overlabels-mobile\1\]\[([\'"])([a-z][a-z0-9_]*)\2\]/',
            fn ($m) => 'c.gps.'.$this->mapKey($m[3]),
            $expr
        );

        return $expr;
    }

    /**
     * Rewrite the dependencies array stored on expression controls. Existing
     * format is `source:key`, e.g. `overlabels-mobile:gps_speed`.
     */
    private function rewriteDependencies(array $deps): array
    {
        $newDeps = array_map(function ($dep) {
            if (! str_starts_with($dep, 'overlabels-mobile:')) {
                return $dep;
            }
            $key = substr($dep, strlen('overlabels-mobile:'));
            return 'gps:'.$this->mapKey($key);
        }, $deps);

        return array_values(array_unique($newDeps));
    }

    /**
     * Rewrite tag references in template source content. Handles plain tags
     * and tags with pipe formatters (e.g. |speed:kmh, |distance:mi).
     */
    private function rewriteTemplateContent(string $content): string
    {
        return preg_replace_callback(
            '/\[\[\[c:overlabels-mobile:([a-z][a-z0-9_]*)((?:\|[^\]]+)?)]]]/',
            fn ($m) => '[[[c:gps:'.$this->mapKey($m[1]).$m[2].']]]',
            $content
        );
    }

    private function reverseExpression(string $expr, array $reverseMap): string
    {
        $reverseKey = function (string $key) use ($reverseMap): string {
            $isAt = str_ends_with($key, '_at');
            $base = $isAt ? substr($key, 0, -3) : $key;
            $oldBase = $reverseMap[$base] ?? $base;
            return $oldBase.($isAt ? '_at' : '');
        };

        return preg_replace_callback(
            '/\bc\.gps\.([a-z][a-z0-9_]*)/',
            fn ($m) => 'c["overlabels-mobile"].'.$reverseKey($m[1]),
            $expr
        );
    }

    private function reverseTemplateContent(string $content, array $reverseMap): string
    {
        return preg_replace_callback(
            '/\[\[\[c:gps:([a-z][a-z0-9_]*)((?:\|[^\]]+)?)]]]/',
            function ($m) use ($reverseMap) {
                $key = $m[1];
                $isAt = str_ends_with($key, '_at');
                $base = $isAt ? substr($key, 0, -3) : $key;
                $oldBase = $reverseMap[$base] ?? $base;
                return '[[[c:overlabels-mobile:'.$oldBase.($isAt ? '_at' : '').$m[2].']]]';
            },
            $content
        );
    }
};
