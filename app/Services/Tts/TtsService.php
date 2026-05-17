<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Thin wrapper around ElevenLabs Flash 2.5 TTS.
 *
 * synthesize() is the only call site for alert TTS. Result is a public URL to
 * an mp3 cached on the `public` disk under `tts/{sha256}.mp3`. The hash keys
 * on text + voice + model, so repeat sentences ("New follower: Frank!") only
 * hit the API once. Cleanup is handled by a scheduled command.
 *
 * Failure is silent: returns null and logs. Alerts must still fire even when
 * ElevenLabs is down/rate-limited; TTS is best-effort.
 */
class TtsService
{
    private const string TTS_DIR = 'tts';

    private const int HTTP_TIMEOUT_SECONDS = 10;

    /**
     * Synthesize text via ElevenLabs and return a public URL to the mp3.
     * Returns null when credentials are missing, the input is empty, or the
     * upstream call fails.
     */
    public function synthesize(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $apiKey = (string) config('services.elevenlabs.api_key');
        $voiceId = (string) config('services.elevenlabs.voice_id');
        $modelId = (string) config('services.elevenlabs.model_id');

        if ($apiKey === '' || $voiceId === '') {
            Log::warning('TtsService: ElevenLabs not configured (api_key or voice_id missing)');

            return null;
        }

        $disk = Storage::disk('public');
        $path = self::TTS_DIR.'/'.$this->cacheKey($text, $voiceId, $modelId).'.mp3';

        if ($disk->exists($path)) {
            return $disk->url($path);
        }

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept' => 'audio/mpeg',
            ])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->withBody(
                    json_encode([
                        'text' => $text,
                        'model_id' => $modelId,
                        'output_format' => 'mp3_44100_128',
                    ], JSON_THROW_ON_ERROR),
                    'application/json',
                )
                ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}");
        } catch (\Throwable $e) {
            Log::warning('TtsService: synthesize HTTP threw', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('TtsService: synthesize failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            return null;
        }

        $disk->put($path, $response->body());

        return $disk->url($path);
    }

    /**
     * List the user's voice library. Used by the tts:list-voices command to
     * discover voice IDs at setup time. Returns the raw `voices` array from
     * the API or null on failure.
     *
     * @return array<int,array<string,mixed>>|null
     */
    public function listVoices(): ?array
    {
        $apiKey = (string) config('services.elevenlabs.api_key');
        if ($apiKey === '') {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept' => 'application/json',
            ])
                ->timeout(self::HTTP_TIMEOUT_SECONDS)
                ->get('https://api.elevenlabs.io/v1/voices');
        } catch (\Throwable $e) {
            Log::warning('TtsService: listVoices HTTP threw', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $voices = $response->json('voices');

        return is_array($voices) ? $voices : null;
    }

    private function cacheKey(string $text, string $voiceId, string $modelId): string
    {
        return hash('sha256', $text.'|'.$voiceId.'|'.$modelId);
    }
}
