<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Browsers only understand WebVTT in <track>, so SRT files are converted on
 * upload. Subtitle files from the wild come in assorted encodings, so text is
 * normalised to UTF-8 first.
 */
class Subtitles
{
    public const LANGUAGES = [
        'en' => 'English', 'si' => 'Sinhala', 'ta' => 'Tamil', 'hi' => 'Hindi',
        'es' => 'Spanish', 'fr' => 'French', 'de' => 'German', 'ko' => 'Korean',
        'ja' => 'Japanese', 'zh' => 'Chinese', 'ar' => 'Arabic', 'ml' => 'Malayalam',
        'te' => 'Telugu',
    ];

    public static function toVtt(string $raw): string
    {
        $text = self::toUtf8($raw);
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/^\x{FEFF}/u', '', $text);

        if (str_starts_with(ltrim($text), 'WEBVTT')) {
            return $text;
        }

        // 00:01:02,345 --> 00:01:04,000  becomes  00:01:02.345 --> 00:01:04.000
        $text = preg_replace(
            '/(\d{1,2}:\d{2}:\d{2})[,.](\d{1,3})\s*-->\s*(\d{1,2}:\d{2}:\d{2})[,.](\d{1,3})/',
            '$1.$2 --> $3.$4',
            $text
        );

        // Basic SRT styling tags like {\an8} are not valid in VTT cues.
        $text = preg_replace('/\{\\\\[^}]*\}/', '', $text);

        return "WEBVTT\n\n".trim($text)."\n";
    }

    protected static function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            return mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        }

        if (mb_check_encoding($raw, 'UTF-8')) {
            return $raw;
        }

        return mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    /**
     * Guess [lang, label] from a file name like "Movie.2021.sinhala.srt",
     * "English [SDH].eng.srt" or "Eiffel-2021.si.srt".
     */
    public static function guessLanguage(string $filename): array
    {
        $name = mb_strtolower(pathinfo($filename, PATHINFO_FILENAME));

        $patterns = [
            'si' => '/(^|[^a-z])(si|sin|sinhala|sinhalese)([^a-z]|$)/',
            'ta' => '/(^|[^a-z])(ta|tam|tamil)([^a-z]|$)/',
            'hi' => '/(^|[^a-z])(hi|hin|hindi)([^a-z]|$)/',
            'es' => '/(^|[^a-z])(es|spa|spanish)([^a-z]|$)/',
            'fr' => '/(^|[^a-z])(fr|fre|french)([^a-z]|$)/',
            'ko' => '/(^|[^a-z])(ko|kor|korsub|korean)([^a-z]|$)/',
            'en' => '/(^|[^a-z])(en|eng|english)([^a-z]|$)/',
        ];

        foreach ($patterns as $lang => $pattern) {
            if (preg_match($pattern, $name)) {
                $label = self::LANGUAGES[$lang];

                if (preg_match('/sdh/', $name)) {
                    $label .= ' (SDH)';
                } elseif (preg_match('/forced/', $name)) {
                    $label .= ' (Forced)';
                }

                return [$lang, $label];
            }
        }

        // Unknown: assume English, as most release subtitles are.
        return ['en', 'English'];
    }

    public static function labelFor(string $lang): string
    {
        return self::LANGUAGES[$lang] ?? Str::upper($lang);
    }
}
