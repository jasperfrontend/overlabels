<?php
/**
 * Starter seed generator.
 *
 * Produces database/sql/starter-seed.sql from the current live database.
 * Sensitive user fields (tokens, twitch_data, webhook_secret) are stripped
 * so the file is safe to commit.
 *
 * Usage:
 *   php database/sql/generate-seed.php
 *
 * To restore:
 *   psql -U postgres -d overlabels -f database/sql/starter-seed.sql
 * Or from artisan:
 *   php artisan db:seed-import   (if you add that command later)
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pdo = DB::getPdo();

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function quote($pdo, $val): string
{
    if ($val === null) return 'NULL';
    if (is_bool($val)) return $val ? 'true' : 'false';
    if (is_int($val) || is_float($val)) return (string) $val;
    return $pdo->quote($val);
}

function rows_to_inserts($pdo, string $table, array $rows): string
{
    if (empty($rows)) return "-- (no rows in {$table})\n";
    $out = "";
    foreach ($rows as $row) {
        $row = (array) $row;
        $cols = implode(', ', array_keys($row));
        $vals = implode(', ', array_map(fn($v) => quote($pdo, $v), array_values($row)));
        $out .= "INSERT INTO public.{$table} ({$cols}) VALUES ({$vals});\n";
    }
    return $out;
}

function seq_reset(string $table, string $col = 'id'): string
{
    return "SELECT setval(pg_get_serial_sequence('public.{$table}', '{$col}'), " .
           "COALESCE((SELECT MAX({$col}) FROM public.{$table}), 1));\n";
}

// ---------------------------------------------------------------------------
// Collect data
// ---------------------------------------------------------------------------

// Users — strip all credentials/tokens; they're re-populated on Twitch login
$users = DB::table('users')->get()->map(function ($u) {
    $u = (array) $u;
    $u['access_token']      = null;
    $u['refresh_token']     = null;
    $u['token_expires_at']  = null;
    $u['twitch_data']       = null;
    $u['webhook_secret']    = null;
    $u['remember_token']    = null;
    $u['eventsub_connected_at'] = null;
    return $u;
})->all();

$tag_categories = DB::table('template_tag_categories')->orderBy('id')->get()->all();
$tags           = DB::table('template_tags')->orderBy('id')->get()->all();
$kits           = DB::table('kits')->orderBy('id')->get()->all();
$kit_templates  = DB::table('kit_templates')->orderBy('id')->get()->all();
$templates      = DB::table('overlay_templates')->orderBy('id')->get()->all();
$controls       = DB::table('overlay_controls')->orderBy('id')->get()->all();
$event_maps     = DB::table('event_template_mappings')->orderBy('id')->get()->all();

// ---------------------------------------------------------------------------
// Build SQL
// ---------------------------------------------------------------------------

$now = date('Y-m-d H:i:s');
$tables_seeded = ['users', 'template_tag_categories', 'template_tags', 'kits', 'kit_templates', 'overlay_templates', 'overlay_controls', 'event_template_mappings'];

$sql  = "-- =============================================================================\n";
$sql .= "-- Overlabels starter seed\n";
$sql .= "-- Generated: {$now}\n";
$sql .= "--\n";
$sql .= "-- Tables: " . implode(', ', $tables_seeded) . "\n";
$sql .= "-- Sensitive user fields (tokens, twitch_data, webhook_secret) are stripped.\n";
$sql .= "-- Run AFTER php artisan migrate on a fresh database.\n";
$sql .= "--\n";
$sql .= "-- Import: psql -U postgres -d <dbname> -f database/sql/starter-seed.sql\n";
$sql .= "-- =============================================================================\n\n";

$sql .= "BEGIN;\n\n";

// Bypass FK constraints so we can truncate/insert in any order
$sql .= "SET session_replication_role = 'replica';\n\n";

// Truncate in reverse-dependency order (safe with replica role)
$sql .= "-- Clear existing starter data\n";
foreach (array_reverse($tables_seeded) as $t) {
    $sql .= "TRUNCATE public.{$t} CASCADE;\n";
}
$sql .= "\n";

// Inserts
$sql .= "-- users\n";
$sql .= rows_to_inserts($pdo, 'users', $users);
$sql .= "\n";

$sql .= "-- template_tag_categories\n";
$sql .= rows_to_inserts($pdo, 'template_tag_categories', $tag_categories);
$sql .= "\n";

$sql .= "-- template_tags\n";
$sql .= rows_to_inserts($pdo, 'template_tags', $tags);
$sql .= "\n";

$sql .= "-- kits\n";
$sql .= rows_to_inserts($pdo, 'kits', $kits);
$sql .= "\n";

$sql .= "-- kit_templates\n";
$sql .= rows_to_inserts($pdo, 'kit_templates', $kit_templates);
$sql .= "\n";

$sql .= "-- overlay_templates\n";
$sql .= rows_to_inserts($pdo, 'overlay_templates', $templates);
$sql .= "\n";

$sql .= "-- overlay_controls\n";
$sql .= rows_to_inserts($pdo, 'overlay_controls', $controls);
$sql .= "\n";

$sql .= "-- event_template_mappings\n";
$sql .= rows_to_inserts($pdo, 'event_template_mappings', $event_maps);
$sql .= "\n";

// Restore FK enforcement
$sql .= "RESET session_replication_role;\n\n";

// Reset sequences so new rows get correct IDs
$sql .= "-- Sequence resets\n";
foreach ($tables_seeded as $t) {
    $sql .= seq_reset($t);
}
$sql .= "\n";

$sql .= "COMMIT;\n";

// ---------------------------------------------------------------------------
// Write file
// ---------------------------------------------------------------------------

$output = __DIR__ . '/starter-seed.sql';
file_put_contents($output, $sql);
echo "Seed written to {$output}\n";
echo "Rows: users=" . count($users) . ", tag_categories=" . count($tag_categories) .
     ", tags=" . count($tags) . ", kits=" . count($kits) .
     ", kit_templates=" . count($kit_templates) . ", overlay_templates=" . count($templates) .
     ", overlay_controls=" . count($controls) . ", event_mappings=" . count($event_maps) . "\n";
