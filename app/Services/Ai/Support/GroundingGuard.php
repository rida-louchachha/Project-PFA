<?php

namespace App\Services\Ai\Support;

/**
 * Hallucination guard for numbers (prices, dates, amounts).
 *
 * - Outbound emails: AI text must contain no digit at all (facts are rendered by templates).
 * - Chat replies: every number must appear in the data the model received this turn
 *   (tool results, server context, customer messages); others are flagged in the audit log.
 */
final class GroundingGuard
{
    public static function containsDigits(string $text): bool
    {
        return preg_match('/\d/', $text) === 1;
    }

    /**
     * @param  list<mixed>  $sources
     * @return list<string> Numbers in $text that no source contains.
     */
    public static function ungroundedNumbers(string $text, array $sources): array
    {
        $allowed = [];
        foreach ($sources as $source) {
            $string = is_string($source) ? $source : (json_encode($source, JSON_UNESCAPED_UNICODE) ?: '');
            foreach (self::numbers($string) as $number) {
                $allowed[$number] = true;
            }
        }

        $ungrounded = [];
        foreach (self::numbers($text) as $number) {
            if (!isset($allowed[$number])) {
                $ungrounded[$number] = $number;
            }
        }

        return array_values($ungrounded);
    }

    /**
     * Normalised numbers: "1 250,00" => "1250", "625.50" => "625.5", "09" => "9".
     *
     * @return list<string>
     */
    private static function numbers(string $text): array
    {
        // Drop thousands separators written as (narrow) spaces
        $text = preg_replace('/(?<=\d)[\s\x{00A0}\x{202F}](?=\d{3}(?!\d))/u', '', $text) ?? $text;
        preg_match_all('/\d+(?:[.,]\d+)?/', $text, $matches);

        $numbers = [];
        foreach ($matches[0] as $raw) {
            $value = str_replace(',', '.', $raw);
            if (str_contains($value, '.')) {
                $value = rtrim(rtrim($value, '0'), '.');
            }
            $value = ltrim($value, '0');
            $numbers[] = $value === '' || str_starts_with($value, '.') ? '0' . $value : $value;
        }

        return $numbers;
    }
}
