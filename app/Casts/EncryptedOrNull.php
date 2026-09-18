<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts a string column at rest, and reads an undecryptable value as null
 * rather than throwing.
 *
 * Laravel's built-in `encrypted` cast raises a DecryptException on any value it
 * cannot read. That is the right default for a column that has only ever held
 * ciphertext, which is why `BotToken` uses it. It is the wrong default for a
 * column being converted in place: for the length of a rolling deploy, an old
 * container is still writing plaintext, and a single leftover row would then
 * throw on every read for that user, on every request, forever.
 *
 * Returning null degrades to the state the app already knows how to handle -
 * "this user has no usable token" - which routes them through a normal
 * re-authorization instead of a 500. The same applies if APP_KEY is ever
 * rotated: users re-authorize, rather than the site going down.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class EncryptedOrNull implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString((string) $value);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [$key => null];
        }

        return [$key => Crypt::encryptString((string) $value)];
    }
}
