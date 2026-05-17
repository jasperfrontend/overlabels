<?php

namespace App\Console\Commands;

use App\Services\Tts\TtsService;
use Illuminate\Console\Command;

/**
 * Lists every voice in the configured ElevenLabs account. Used at setup time
 * to discover the Kaylin voice_id; drop that into .env as ELEVENLABS_VOICE_ID.
 *
 * Run: php artisan tts:list-voices
 */
class TtsListVoices extends Command
{
    protected $signature = 'tts:list-voices {--filter= : Substring match on voice name (case-insensitive)}';

    protected $description = 'List voices available in the configured ElevenLabs account';

    public function handle(TtsService $tts): int
    {
        $voices = $tts->listVoices();

        if ($voices === null) {
            $this->error('Failed to fetch voices. Check ELEVENLABS_API_KEY and network access.');

            return self::FAILURE;
        }

        $filter = $this->option('filter');
        if (is_string($filter) && $filter !== '') {
            $needle = mb_strtolower($filter);
            $voices = array_values(array_filter(
                $voices,
                static fn (array $v) => str_contains(mb_strtolower((string) ($v['name'] ?? '')), $needle),
            ));
        }

        if (empty($voices)) {
            $this->warn('No voices found'.($filter ? " matching '{$filter}'" : '').'.');

            return self::SUCCESS;
        }

        $rows = array_map(static function (array $v): array {
            return [
                $v['voice_id'] ?? '',
                $v['name'] ?? '',
                $v['category'] ?? '',
                $v['labels']['gender'] ?? '',
                $v['labels']['accent'] ?? '',
            ];
        }, $voices);

        $this->table(['voice_id', 'name', 'category', 'gender', 'accent'], $rows);

        return self::SUCCESS;
    }
}
