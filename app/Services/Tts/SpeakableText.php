<?php

namespace App\Services\Tts;

/**
 * Rewrites currency amounts into words on their way to ElevenLabs.
 *
 * The problem this exists for: a donation alert's TTS line is usually written
 * as `[[[event.from_name]]] donated [[[event.formatted_amount]]] through
 * [[[event.source]]]`, and StreamLabs' `formatted_amount` was a symbol glued to
 * digits - "€84", "$13.37". The other four donation drivers exposed no formatted
 * amount at all, so the common template there was
 * `[[[event.currency]]][[[event.amount]]]`, which renders an ISO code glued to
 * digits - "EUR84". Either way ElevenLabs is handed one token it has no reading
 * for, and the voice slurs BOTH the symbol and the number:
 *
 *   "Kevin just donated €84 through StreamLabs"
 *   -> "Kevin just donated hurrurel eigddyfour through streamlabs"
 *
 * Since OL-2609-073 `formatted_amount` is written by NumberFormatter::CURRENCY
 * in the streamer's locale for every service, and ICU has shapes of its own:
 * a foreign currency's symbol is qualified ("US$ 3,00" on Dutch settings,
 * "CA$13.37", "JP¥ 13"), French puts the qualifier after it ("13,37 $US"),
 * and Japanese writes its own yen full-width. A qualified symbol the pass did
 * not know went through untouched, and an English voice reads "US$ 3,00" as
 * three thousand dollars. Those shapes are in SYMBOLS now, and a test walks
 * every locale the app offers against every currency listed here.
 *
 * ElevenLabs' own normalization guidance is to expand money into its spoken
 * form before synthesis rather than rely on the model:
 * https://elevenlabs.io/docs/best-practices/prompting/normalization
 *
 * So "€84" becomes "84 euros" and "$13.37" becomes "13 dollars and 37 cents".
 * The DIGITS are deliberately kept rather than spelled out - the same page
 * records that Multilingual v2 (the model Kaylin runs on) already reads
 * "$1,000,000" correctly as "one million dollars"; the number was never the
 * part it could not handle, the glued symbol was.
 *
 * Only the sentence sent to the TTS API is touched. What the overlay draws and
 * what the bot posts to chat are rendered separately and are unaffected.
 *
 * Adding a currency is one entry in CURRENCIES (plus one in SYMBOLS if it has
 * a symbol). Anything not listed is left exactly as written.
 */
class SpeakableText
{
    /**
     * ISO code => [unit singular, unit plural, subunit singular, subunit plural].
     * A null subunit means the currency is spoken without one.
     *
     * @var array<string, array{0: string, 1: string, 2: ?string, 3: ?string}>
     */
    private const array CURRENCIES = [
        'USD' => ['dollar', 'dollars', 'cent', 'cents'],
        'EUR' => ['euro', 'euros', 'cent', 'cents'],
        'GBP' => ['pound', 'pounds', 'penny', 'pence'],
        'JPY' => ['yen', 'yen', null, null],
        'CAD' => ['Canadian dollar', 'Canadian dollars', 'cent', 'cents'],
        'AUD' => ['Australian dollar', 'Australian dollars', 'cent', 'cents'],
    ];

    /**
     * Symbol => ISO code. The bare four are what StreamLabs and hand-written
     * templates glue to digits. The rest are what ICU writes for a currency
     * that is foreign to the locale: a qualifier before the symbol in most
     * locales, after it in French, and a full-width yen in Japanese.
     *
     * @var array<string, string>
     */
    private const array SYMBOLS = [
        '€' => 'EUR',
        '$' => 'USD',
        '£' => 'GBP',
        '¥' => 'JPY',
        '￥' => 'JPY',
        'US$' => 'USD',
        '$US' => 'USD',
        'CA$' => 'CAD',
        'C$' => 'CAD',
        '$CA' => 'CAD',
        'AU$' => 'AUD',
        'A$' => 'AUD',
        '$AU' => 'AUD',
        'JP¥' => 'JPY',
        '£GB' => 'GBP',
    ];

    /**
     * Separators an amount may carry between symbol and digits. `\s` under /u
     * is still ASCII-only in PCRE, and NumberFormatter::CURRENCY emits a
     * non-breaking or narrow no-break space for several locales, so both are
     * named explicitly.
     */
    private const string GAP = '[\s\x{00A0}\x{202F}]*';

    public static function prepare(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        // Longest marker first, so "US$" is read as one marker rather than a
        // "$" the lookbehind then refuses for having a letter in front of it.
        $markers = array_merge(array_keys(self::SYMBOLS), array_keys(self::CURRENCIES));
        usort($markers, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $money = implode('|', array_map(
            static fn (string $s): string => preg_quote($s, '/'),
            $markers,
        ));

        // "€84", "EUR 84.50"
        $text = (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}])('.$money.')(?![\p{L}])'.self::GAP.'(\d(?:[\d.,]*\d)?)/u',
            static fn (array $m): string => self::rewrite($m[1], $m[2]) ?? $m[0],
            $text,
        );

        // "84€", "84.50 EUR"
        $text = (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}.,])(\d(?:[\d.,]*\d)?)'.self::GAP.'('.$money.')(?![\p{L}\p{N}])/u',
            static fn (array $m): string => self::rewrite($m[2], $m[1]) ?? $m[0],
            $text,
        );

        // Any other three-letter code glued to its digits: we have no words for
        // it, but splitting the token still hands the model a plain number to
        // read - "SEK84" -> "SEK 84".
        return (string) preg_replace('/(?<![\p{L}\p{N}])([A-Z]{3})(?=\d)/u', '$1 ', $text);
    }

    /**
     * Returns null when the digits are not a shape we are confident about, so
     * the caller can leave the original text untouched.
     */
    private static function rewrite(string $marker, string $digits): ?string
    {
        $code = self::SYMBOLS[$marker] ?? $marker;
        if (! isset(self::CURRENCIES[$code])) {
            return null;
        }

        $parts = self::splitAmount($digits);
        if ($parts === null) {
            return null;
        }

        [$whole, $fraction] = $parts;
        [$one, $many, $subOne, $subMany] = self::CURRENCIES[$code];

        $units = ltrim($whole, '0');
        $units = $units === '' ? '0' : $units;

        $subUnits = 0;
        if ($subOne !== null && $fraction !== '') {
            // "84.5" is eighty-four and fifty cents, not five.
            $subUnits = (int) str_pad(substr($fraction, 0, 2), 2, '0');
        }

        // "€0.50" is fifty cents, never "zero euros and fifty cents".
        if ($units === '0' && $subUnits > 0) {
            return $subUnits.' '.($subUnits === 1 ? $subOne : $subMany);
        }

        $spoken = $units.' '.($units === '1' ? $one : $many);

        if ($subUnits > 0) {
            $spoken .= ' and '.$subUnits.' '.($subUnits === 1 ? $subOne : $subMany);
        }

        return $spoken;
    }

    /**
     * Split "1,234.56" into its whole and fractional digits without knowing
     * which convention wrote it. The caller's pattern guarantees the run
     * begins and ends on a digit, so a trailing "." is the sentence's full
     * stop and never reaches here.
     *
     * With both separators present the LAST one is the decimal point and the
     * other is grouping, which is true of every convention that uses two. With
     * one separator it is a decimal point only if it appears once and one or
     * two digits follow - so "84,50" and "13.37" are amounts, while "1,234"
     * and "1.234" are both one thousand two hundred and thirty-four. Anything
     * else is a shape we cannot read with confidence and is refused.
     *
     * @return array{0: string, 1: string}|null
     */
    private static function splitAmount(string $raw): ?array
    {
        $lastDot = strrpos($raw, '.');
        $lastComma = strrpos($raw, ',');
        $decimalAt = null;

        if ($lastDot !== false && $lastComma !== false) {
            $decimalAt = max($lastDot, $lastComma);
        } elseif ($lastDot !== false || $lastComma !== false) {
            $at = $lastDot !== false ? $lastDot : $lastComma;
            $following = strlen($raw) - $at - 1;

            if (substr_count($raw, $raw[$at]) === 1 && ($following === 1 || $following === 2)) {
                $decimalAt = $at;
            } elseif ($following !== 3) {
                return null;
            }
        }

        $whole = $decimalAt === null ? $raw : substr($raw, 0, $decimalAt);
        $fraction = $decimalAt === null ? '' : substr($raw, $decimalAt + 1);

        $whole = (string) preg_replace('/\D/', '', $whole);

        return $whole === '' ? null : [$whole, $fraction];
    }
}
