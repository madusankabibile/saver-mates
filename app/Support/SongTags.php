<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns raw ID3 values and file names into presentable title / artist / album.
 * Downloaded tracks often carry the download site in every tag
 * ("Sarigama.lk", "[www.sinhalasongs.lk]"), so those values are discarded and
 * the file name (and, for imports, the artist folder) is used instead.
 */
class SongTags
{
    protected const JUNK = '/(www\.|https?:|\.lk\b|\.com\b|\.net\b|sarigama|sinhalasongs|mp3\s*download|^unknown|^default$|^other$|^track\s*\d*$)/i';

    /**
     * @param  array  $tags  output of MediaMeta::mp3Info (title, artist, album, genre)
     * @param  string  $filename  original file name, with or without extension
     * @param  string|null  $folderArtist  artist implied by the containing folder (imports only)
     */
    public static function clean(array $tags, string $filename, ?string $folderArtist = null): array
    {
        $title = self::titleValue($tags['title'] ?? null);
        $artist = self::value($tags['artist'] ?? null);
        $album = self::value($tags['album'] ?? null);
        $genre = self::value($tags['genre'] ?? null);

        $folderArtist = $folderArtist && ! preg_match('/^(others?|new folder.*|misc|various|songs?)$/i', trim($folderArtist))
            ? mb_convert_case(self::tidy($folderArtist), MB_CASE_TITLE)
            : null;

        // A tag that merely repeats the title is not an artist.
        if ($artist && $title && mb_strtolower($artist) === mb_strtolower($title)) {
            $artist = null;
        }

        if (! $title) {
            $title = self::titleFromFilename($filename, $artist ?? $folderArtist);
        }

        return [
            'title' => Str::limit($title ?: 'Untitled track', 250, ''),
            'artist' => $artist ?? $folderArtist,
            'album' => $album,
            'genre' => $genre ? Str::limit($genre, 60, '') : null,
        ];
    }

    protected static function value(?string $v): ?string
    {
        $v = trim((string) $v);

        if ($v === '' || preg_match(self::JUNK, $v)) {
            return null;
        }

        return self::tidy($v);
    }

    /** Titles lose file extensions and leading track numbers; a bare number is no title. */
    protected static function titleValue(?string $v): ?string
    {
        $v = preg_replace('/\.(mp3|wav|m4a|aac|ogg|flac)$/i', '', trim((string) $v));
        $v = preg_replace('/^\d{1,3}(\s*[-._)]\s*|\s+|(?=\p{Lu}))/u', '', $v);

        return preg_match('/^\d*$/', $v) ? null : self::value($v);
    }

    /** Underscores to spaces, collapse whitespace, and soften ALL CAPS. */
    protected static function tidy(string $v): string
    {
        $v = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $v)));

        if ($v === mb_strtoupper($v) || $v === mb_strtolower($v)) {
            $v = mb_convert_case(mb_strtolower($v), MB_CASE_TITLE);
        }

        return $v;
    }

    protected static function titleFromFilename(string $filename, ?string $artist): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/^\d{1,3}(\s*[-._)]\s*|\s+)/', '', $name);

        // Strip download-site suffixes and bracketed noise.
        $name = preg_replace('/[\s_\-]*(sarigama[\s_.]*lk|sinhalasongs[\s_.]*lk|www\.\S+|\(?\d{2,3}\s*kbps\)?)\s*$/i', '', $name);
        $name = preg_replace('/\[[^\]]*\]|\{[^}]*\}/', '', $name);

        $tokens = array_values(array_filter(preg_split('/[\s_\-]+/', $name), fn ($t) => $t !== ''));

        // "Song_Name_Artist_Name": cut where the artist's first name starts.
        if ($artist && count($tokens) > 2) {
            $first = mb_strtolower(preg_split('/\s+/', $artist)[0]);

            foreach ($tokens as $i => $token) {
                if ($i >= 2 && self::similar(mb_strtolower($token), $first)) {
                    $tokens = array_slice($tokens, 0, $i);
                    break;
                }
            }
        }

        return self::tidy(implode(' ', $tokens));
    }

    protected static function similar(string $a, string $b): bool
    {
        if (mb_strlen($b) < 3) {
            return $a === $b;
        }

        return $a === $b || levenshtein($a, $b) <= (mb_strlen($b) > 6 ? 2 : 1);
    }
}
