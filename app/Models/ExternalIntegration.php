<?php

namespace App\Models;

use Database\Factories\ExternalIntegrationFactory;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $service
 * @property string $webhook_token
 * @property string|null $credentials
 * @property array<array-key, mixed>|null $settings
 * @property bool $enabled
 * @property Carbon|null $last_received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property bool $test_mode
 * @property-read Collection<int, ExternalEvent> $events
 * @property-read int|null $events_count
 * @property-read Collection<int, ExternalEventTemplateMapping> $mappings
 * @property-read int|null $mappings_count
 * @property-read User|null $user
 *
 * @method static ExternalIntegrationFactory factory($count = null, $state = [])
 * @method static Builder<static>|ExternalIntegration newModelQuery()
 * @method static Builder<static>|ExternalIntegration newQuery()
 * @method static Builder<static>|ExternalIntegration query()
 * @method static Builder<static>|ExternalIntegration whereCreatedAt($value)
 * @method static Builder<static>|ExternalIntegration whereCredentials($value)
 * @method static Builder<static>|ExternalIntegration whereEnabled($value)
 * @method static Builder<static>|ExternalIntegration whereId($value)
 * @method static Builder<static>|ExternalIntegration whereLastReceivedAt($value)
 * @method static Builder<static>|ExternalIntegration whereService($value)
 * @method static Builder<static>|ExternalIntegration whereSettings($value)
 * @method static Builder<static>|ExternalIntegration whereTestMode($value)
 * @method static Builder<static>|ExternalIntegration whereUpdatedAt($value)
 * @method static Builder<static>|ExternalIntegration whereUserId($value)
 * @method static Builder<static>|ExternalIntegration whereWebhookToken($value)
 *
 * @mixin Eloquent
 */
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
