<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Encrypts users.access_token and users.refresh_token at rest.
 *
 * These were the last credentials on the platform stored in the clear.
 * bot_tokens has been encrypted since April and external_integrations
 * credentials since March; a streamer's own Twitch tokens were not, which meant
 * every nightly database dump carried a live, usable grant for every account.
 *
 * TWO STEPS, AND THE ORDER MATTERS.
 *
 * 1. Widen the columns. On production both are `character varying(255)`,
 *    because 2025_07_25_170248 declared them as string() and the later
 *    2025_08_04_162922 guarded its text() definition behind hasColumn() and so
 *    never ran. An encrypted Twitch token is roughly 200-260 characters, and
 *    Postgres REJECTS an over-length varchar rather than truncating it, so
 *    encrypting first would have failed every login the moment it landed.
 *
 * 2. Encrypt what is already there, through the query builder rather than the
 *    model: no casts, no observers, no updated_at churn, and no chance of
 *    double-encrypting a row the cast has already handled.
 *
 * Re-runnable. Each value is probed with a decrypt first, and one that already
 * decrypts is left alone, so a partial run resumes cleanly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Postgres has no "change to text" through Laravel's column modifier
        // without doctrine/dbal, and a raw ALTER is clearer about intent.
        DB::statement('ALTER TABLE users ALTER COLUMN access_token TYPE text');
        DB::statement('ALTER TABLE users ALTER COLUMN refresh_token TYPE text');

        if (! Schema::hasTable('users')) {
            return;
        }

        DB::table('users')
            ->select('id', 'access_token', 'refresh_token')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach (['access_token', 'refresh_token'] as $column) {
                        $encrypted = $this->encryptOnce($row->{$column});

                        if ($encrypted !== null) {
                            $updates[$column] = $encrypted;
                        }
                    }

                    if ($updates !== []) {
                        DB::table('users')->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    /**
     * Not reversible on purpose. Decrypting these back into plaintext columns
     * would be undoing the point of the change, and the application reads them
     * through the cast either way.
     */
    public function down(): void {}

    /**
     * Ciphertext for a plaintext value, or null when there is nothing to do -
     * the value is empty, or it already decrypts and is therefore already
     * encrypted.
     */
    private function encryptOnce(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            Crypt::decryptString($value);

            return null;
        } catch (DecryptException) {
            return Crypt::encryptString($value);
        }
    }
};
