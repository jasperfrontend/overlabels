<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE external_events ALTER COLUMN raw_payload TYPE jsonb USING raw_payload::jsonb');
        DB::statement('ALTER TABLE external_events ALTER COLUMN normalized_payload TYPE jsonb USING normalized_payload::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE external_events ALTER COLUMN raw_payload TYPE json USING raw_payload::json');
        DB::statement('ALTER TABLE external_events ALTER COLUMN normalized_payload TYPE json USING normalized_payload::json');
    }
};
