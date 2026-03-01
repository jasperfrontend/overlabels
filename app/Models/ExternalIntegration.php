<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class ExternalIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'service',
        'webhook_token',
        'credentials',
        'settings',
        'enabled',
        'test_mode',
        'last_received_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'enabled' => 'boolean',
        'test_mode' => 'boolean',
        'last_received_at' => 'datetime',
    ];

    protected static function booting(): void
    {
        static::creating(function (self $model) {
            if (empty($model->webhook_token)) {
                $model->webhook_token = (string) Str::uuid();
            }
        });
    }

    /**
     * Get decrypted credentials as an array.
     */
    public function getCredentialsDecrypted(): array
    {
        if (empty($this->credentials)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials), true) ?? [];
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Set credentials by encrypting a JSON-encoded array.
     */
    public function setCredentialsEncrypted(array $data): void
    {
        $this->credentials = Crypt::encryptString(json_encode($data));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExternalEvent::class, 'service', 'service')
            ->where('user_id', $this->user_id);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ExternalEventTemplateMapping::class, 'service', 'service')
            ->where('user_id', $this->user_id);
    }
}
