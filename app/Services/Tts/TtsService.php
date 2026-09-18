<?php

namespace App\Services\Tts;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Thin wrapper around ElevenLabs Flash 2.5 TTS.
 *
 * synthesize() is the only call site for alert TTS. Result is a public URL to
 * an mp3 cached on the `public` disk under `tts/{hmac}.mp3`. The key covers
 * text + voice + model + the broadcaster, under the app key, so repeat
 * sentences ("New follower: Frank!") only hit the API once while the URL stays
 * unguessable. Cleanup is handled by a scheduled command.
 *
 * Failure is silent: returns null and logs. Alerts must still fire even when
 * ElevenLabs is down/rate-limited; TTS is best-effort.
 *
 * Text is put through SpeakableText first, which rewrites currency amounts
 * into words ("€84" -> "84 euros"). That happens before the cache key is
 * taken, so two sentences that are spoken identically share one mp3.
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
    public function synthesize(string $text, string $scope = ''): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $text = SpeakableText::prepare($text);

        $apiKey = (string) config('services.elevenlabs.api_key');
        $voiceId = (string) config('services.elevenlabs.voice_id');
        $modelId = (string) config('services.elevenlabs.model_id');

        if ($apiKey === '' || $voiceId === '') {
            Log::warning('TtsService: ElevenLabs not configured (api_key or voice_id missing)');

            return null;
        }

        $disk = Storage::disk('public');
        $path = self::TTS_DIR.'/'.$this->cacheKey($text, $voiceId, $modelId, $scope).'.mp3';

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

    /**
     * Keyed with an HMAC over the app key, and scoped to the broadcaster.
     *
     * This used to be a plain sha256 of text + voice + model, with voice and
     * model being app-wide constants. That made the URL a pure function of the
     * sentence: anyone who could guess what an alert says - "New follower:
     * Frank!" is not a hard guess - could construct the URL and confirm the
     * file existed, turning a public bucket path into an oracle over somebody's
     * follower list. It also meant two streamers with the same alert text
     * shared one mp3.
     *
     * Still deterministic, so the dedup that stops us paying ElevenLabs twice
     * for the same sentence is unaffected. Just not reproducible by anyone who
     * does not hold the app key.
     */
    private function cacheKey(string $text, string $voiceId, string $modelId, string $scope = ''): string
    {
        return hash_hmac(
            'sha256',
            $text.'|'.$voiceId.'|'.$modelId.'|'.$scope,
            (string) config('app.key'),
        );
    }
}
