<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Fork" is never used in frontend-facing copy - the UI says Copy everywhere
     * else, including the button that produces these kits. Bring existing titles
     * in line with the prefix Kit::fork() now writes.
     */
    public function up(): void
    {
        DB::table('kits')
            ->where('title', 'like', 'Fork of %')
            ->update([
                'title' => DB::raw("'Copy of ' || substring(title from 9)"),
            ]);
    }

    public function down(): void
    {
        DB::table('kits')
            ->where('title', 'like', 'Copy of %')
            ->update([
                'title' => DB::raw("'Fork of ' || substring(title from 9)"),
            ]);
    }
};
