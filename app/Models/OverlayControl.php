<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OverlayControl extends Model
{
    use HasFactory;

    protected $fillable = [
        'overlay_template_id',
        'user_id',
        'key',
        'label',
        'type',
        'value',
        'config',
        'sort_order',
    ];

    protected $casts = [
        'config' => 'array',
        'sort_order' => 'integer',
    ];

    const TYPES = ['text', 'number', 'counter', 'timer', 'datetime', 'boolean'];

    const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,49}$/';

    /**
     * Sanitize a raw value for a given control type.
     */
    public static function sanitizeValue(string $type, mixed $raw): string
    {
        return match ($type) {
            'text' => strip_tags((string) $raw),
            'number', 'counter' => is_numeric($raw) ? (string) $raw : '0',
            'boolean' => in_array($raw, ['1', 'true', true, 1], true) ? '1' : '0',
            default => '', // timer, datetime: value derived from config
        };
    }

    /**
     * Resolve the display value for this control.
     * For timer: compute elapsed seconds from config state.
     * For all others: return stored value.
     */
    public function resolveDisplayValue(): string
    {
        if ($this->type === 'timer') {
            return $this->resolveTimerDisplayValue();
        }

        return $this->value ?? '';
    }

    private function resolveTimerDisplayValue(): string
    {
        $config = $this->config ?? [];
        $mode = $config['mode'] ?? 'countup';
        $baseSeconds = (int) ($config['base_seconds'] ?? 0);
        $offsetSeconds = (int) ($config['offset_seconds'] ?? 0);
        $running = (bool) ($config['running'] ?? false);
        $startedAt = $config['started_at'] ?? null;

        $elapsed = $offsetSeconds;

        if ($running && $startedAt) {
            $startTime = \Carbon\Carbon::parse($startedAt);
            $elapsed = $offsetSeconds + (int) $startTime->diffInSeconds(now());
        }

        if ($mode === 'countdown') {
            return (string) max(0, $baseSeconds - $elapsed);
        }

        return (string) $elapsed;
    }

    /**
     * Single entry point to create a control for a template.
     * Validates key uniqueness within the template.
     */
    public static function createForTemplate(OverlayTemplate $template, User $user, array $data): self
    {
        return static::create([
            'overlay_template_id' => $template->id,
            'user_id' => $user->id,
            'key' => $data['key'],
            'label' => $data['label'] ?? null,
            'type' => $data['type'],
            'value' => isset($data['value']) ? static::sanitizeValue($data['type'], $data['value']) : null,
            'config' => $data['config'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * Relationships
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(OverlayTemplate::class, 'overlay_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
