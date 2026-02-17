<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique;');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_email_unique UNIQUE (email);');
    }
};
