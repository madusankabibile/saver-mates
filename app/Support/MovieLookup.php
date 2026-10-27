<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds a movie's details (proper title, year, description, category and a
 * poster) online from the uploaded file's name, so the upload form arrives
 * filled in: first TMDB when an API key is configured, then Wikipedia.
 *
 * "Inception.2010.1080p.BluRay.x264.mkv" is read as "Inception" from 2010;
 * a result's title has to match closely and, when the file names a year,
 * the result's year has to agree, so remakes and sequels stay out.
 */
class MovieLookup
{
    protected const MIN_TITLE = 0.8;

    /** TMDB's fixed genre ids. */
    protected const TMDB_GENRES = [
        28 => 'Action', 12 => 'Adventure', 16 => 'Animation', 35 => 'Comedy', 80 => 'Crime',
        99 => 'Documentary', 18 => 'Drama', 10751 => 'Family', 14 => 'Fantasy', 36 => 'History',
        27 => 'Horror', 10402 => 'Music', 9648 => 'Mystery', 10749 => 'Romance',
        878 => 'Science Fiction', 10770 => 'TV Movie', 53 => 'Thriller', 10752 => 'War', 37 => 'Western',
    ];

    /** Genre words => our category. Animation and documentaries win over anything else named. */
    protected const CATEGORY_WORDS = [
        'Animation' => '/\b(animat(ed|ion)|anime|cartoon|stop[- ]motion)\b/i',
        'Documentary' => '/\b(documentar(y|ies)|docudrama)\b/i',
        'Horror' => '/\b(horror|slasher|zombie)\b/i',
        'Sci-Fi' => '/\b(science[- ]fiction|sci[- ]?fi|fantasy)\b/i',
        'Action' => '/\b(action|adventure|superhero|martial[- ]arts|war|western|heist|spy|disaster)\b/i',
        'Thriller' => '/\b(thriller|crime|mystery|suspense|noir|gangster)\b/i',
        'Comedy' => '/\b(comedy|comedies|comic|satir(e|ical)|parody)\b/i',
        'Romance' => '/\b(romance|romantic|love story)\b/i',
        'Music' => '/\b(music(al)?|concert|dance)\b/i',
        'Family' => '/\b(family|children\'?s|kids)\b/i',
        'Drama' => '/\b(drama|biographical|biopic|historical|period)\b/i',
    ];

    /** Release tags that end the title part of a file name. */
    protected const RELEASE_TAGS = '/\b(480p|720p|1080p|2160p|4k|uhd|hdr|web-?rip|web-?dl|blu-?ray|brrip|bdrip|dvdrip|hdrip|hdtv|hdcam|camrip|x264|x265|h ?264|h ?265|hevc|10bit|aac|dts|ddp?5 1|yify|yts|rarbg|extended|unrated|remastered|proper|repack)\b.*$/i';

    public static function enabled(): bool
    {
        return (bool) (config('services.movie_lookup.tmdb_key') || config('services.movie_lookup.wiki_url'));
    }

    /**
     * Title and year from a file name: "Luck.2022.720p.WEBRip.x264.mp4" -> ["Luck", 2022].
     * Mirrors titleFromFile() on the upload page.
     *
     * @return array{0: string, 1: int|null}
     */
    public static function parseName(string $filename): array
    {
        $s = preg_replace('/\.[a-z0-9]{2,4}$/i', '', trim($filename));
        $s = preg_replace(['/\[[^\]]*\]/', '/www\.\S+/i'], ' ', $s);
        $s = preg_replace('/(\p{L})((?:19|20)\d{2})/u', '$1 $2', $s);
        $s = trim(preg_replace('/\s+/', ' ', preg_replace('/[._]+/', ' ', $s)));

        if (preg_match('/^(.*?)[\s(\-]*\b((?:19|20)\d{2})\b/', $s, $m) && trim($m[1]) !== '') {
            return [trim(preg_replace('/[\s(\-]+$/', '', $m[1])), (int) $m[2]];
        }

        return [trim(preg_replace(self::RELEASE_TAGS, '', $s)) ?: $s, null];
    }

    /**
     * The best confident match for an uploaded file name, or null.
     *
     * @return array{key: string, title: string, year: int|null, description: string|null, category: string|null, image: string|null}|null
     */
    public static function find(string $filename): ?array
    {
        [$title, $year] = self::parseName($filename);

        if (! self::enabled() || $title === '') {
            return null;
        }

        return self::fromTmdb($title, $year) ?? self::fromWikipedia($title, $year);
    }

    /** "Inception (2010)": how titles read in the library. */
    public static function displayTitle(array $match): string
    {
        return Str::limit($match['title'], 140, '').($match['year'] ? ' ('.$match['year'].')' : '');
    }

    /** Our category for a list of genre names or a phrase like "science fiction heist". */
    public static function category(string ...$genres): ?string
    {
        $text = implode(', ', $genres);

        foreach (['Animation', 'Documentary'] as $strong) {
            if (preg_match(self::CATEGORY_WORDS[$strong], $text)) {
                return $strong;
            }
        }

        // Otherwise the genre named first decides.
        $best = null;
        foreach (self::CATEGORY_WORDS as $category => $re) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE) && (! $best || $m[0][1] < $best[1])) {
                $best = [$category, $m[0][1]];
            }
        }

        return $best[0] ?? null;
    }

    /* ------------------------------------------------------------------ */
    /* TMDB                                                                */
    /* ------------------------------------------------------------------ */

    protected static function fromTmdb(string $title, ?int $year): ?array
    {
        $key = config('services.movie_lookup.tmdb_key');
        $base = config('services.movie_lookup.tmdb_url');
        if (! $key || ! $base) {
            return null;
        }

        try {
            $results = self::tmdb('/search/movie', array_filter([
                'query' => Str::limit($title, 200, ''),
                'year' => $year,
                'include_adult' => 'false',
            ]))['results'] ?? [];

            // A wrong year in the file name should not hide the movie.
            if (! $results && $year) {
                $results = self::tmdb('/search/movie', ['query' => Str::limit($title, 200, ''), 'include_adult' => 'false'])['results'] ?? [];
            }
        } catch (Throwable $e) {
            Log::warning('Movie lookup (TMDB) failed: '.$e->getMessage());

            return null;
        }

        $best = null;
        foreach (array_slice(is_array($results) ? $results : [], 0, 10) as $i => $r) {
            if (! is_array($r) || ! isset($r['id'], $r['title'])) {
                continue;
            }

            $found = preg_match('/^(\d{4})/', (string) ($r['release_date'] ?? ''), $m) ? (int) $m[1] : null;
            $score = self::score($title, $year, [$r['title'], $r['original_title'] ?? ''], $found);

            if ($score !== null && (! $best || $score - $i * 0.01 > $best[0])) {
                $best = [$score - $i * 0.01, $r, $found];
            }
        }

        if (! $best) {
            return null;
        }

        [, $r, $found] = $best;
        $genres = array_values(array_filter(array_map(fn ($id) => self::TMDB_GENRES[$id] ?? null, $r['genre_ids'] ?? [])));
        $images = rtrim((string) config('services.movie_lookup.tmdb_images'), '/');

        return [
            'key' => 'tm:'.$r['id'],
            'title' => (string) $r['title'],
            'year' => $found,
            'description' => self::trimDescription($r['overview'] ?? null),
            'category' => self::category(...$genres),
            // The backdrop is already 16:9; the poster gets framed to fit.
            'image' => ! empty($r['backdrop_path']) ? $images.'/w1280'.$r['backdrop_path']
                : (! empty($r['poster_path']) ? $images.'/w780'.$r['poster_path'] : null),
        ];
    }

    protected static function tmdb(string $path, array $query): mixed
    {
        $key = (string) config('services.movie_lookup.tmdb_key');
        $client = OutboundHttp::client(6)->acceptJson();

        // v4 read tokens are JWTs sent as a bearer token; v3 keys go in the query.
        if (str_starts_with($key, 'eyJ')) {
            $client->withToken($key);
        } else {
            $query['api_key'] = $key;
        }

        return $client->get(rtrim(config('services.movie_lookup.tmdb_url'), '/').$path, $query)->throw()->json();
    }

    /* ------------------------------------------------------------------ */
    /* Wikipedia                                                           */
    /* ------------------------------------------------------------------ */

    protected static function fromWikipedia(string $title, ?int $year): ?array
    {
        $base = config('services.movie_lookup.wiki_url');
        if (! $base) {
            return null;
        }

        try {
            $pages = OutboundHttp::client(8)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0 (intranet media library)'])
                ->get(rtrim($base, '/').'/w/api.php', [
                    'action' => 'query',
                    'format' => 'json',
                    'redirects' => 1,
                    'generator' => 'search',
                    'gsrsearch' => Str::limit($title, 200, '').($year ? ' '.$year : '').' film',
                    'gsrlimit' => 8,
                    'prop' => 'extracts|pageimages',
                    'exintro' => 1,
                    'explaintext' => 1,
                    'exlimit' => 'max',
                    'piprop' => 'thumbnail',
                    'pithumbsize' => 1280,
                    'pilicense' => 'any', // film posters are non-free images
                ])->throw()->json('query.pages') ?? [];
        } catch (Throwable $e) {
            Log::warning('Movie lookup (Wikipedia) failed: '.$e->getMessage());

            return null;
        }

        $best = null;
        foreach (is_array($pages) ? $pages : [] as $page) {
            $intro = trim(preg_replace('/\s+/', ' ', (string) ($page['extract'] ?? '')));

            // Only articles about a film: "Inception is a 2010 science fiction heist film ..."
            if (! isset($page['title']) || ! preg_match('/\b(?:is|was) an? ((?:[^.]{0,160}?\s)?)(?:feature |short )?(?:film|movie)\b/i', $intro, $m)) {
                continue;
            }

            $phrase = $m[1];
            $found = preg_match('/\b((?:19|20)\d{2})\b/', $phrase, $y) ? (int) $y[1] : null;
            $name = trim(preg_replace('/\s*\([^)]*\bfilm\)$/i', '', $page['title']));
            $score = self::score($title, $year, [$name], $found);

            $rank = (int) ($page['index'] ?? 9);
            if ($score !== null && (! $best || $score - $rank * 0.01 > $best[0])) {
                $best = [$score - $rank * 0.01, $page, $name, $found, $phrase, $intro];
            }
        }

        if (! $best) {
            return null;
        }

        [, $page, $name, $found, $phrase, $intro] = $best;

        return [
            'key' => 'wp:'.$page['pageid'],
            'title' => $name,
            'year' => $found,
            // "Pathaan (pronounced [pəʈʰaːn]) is ..." loses the pronunciation guide.
            'description' => self::trimDescription(preg_replace('/\s*\((?:[^()]*pronounced|[^()]*\[)[^()]*\)/u', '', $intro)),
            'category' => self::category($phrase),
            'image' => $page['thumbnail']['source'] ?? null,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Matching and images                                                 */
    /* ------------------------------------------------------------------ */

    /** How well a result fits; null when the title differs or the year disagrees. */
    protected static function score(string $title, ?int $year, array $names, ?int $found): ?float
    {
        $s = max(array_map(fn ($n) => $n === '' ? 0.0 : SongLookup::similarity($title, $n), $names));

        if ($s < self::MIN_TITLE) {
            return null;
        }

        if ($year && $found) {
            if (abs($year - $found) > 1) {
                return null; // a remake or another film with the same name
            }
            $s += $year === $found ? 0.3 : 0.1;
        }

        return $s;
    }

    /** Whole sentences, up to about 1,200 characters. */
    protected static function trimDescription(?string $text): ?string
    {
        $text = trim(preg_replace('/\s+/', ' ', (string) $text));
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= 1200) {
            return $text;
        }

        $cut = mb_substr($text, 0, 1200);
        $end = max(mb_strrpos($cut, '. ') ?: 0, mb_strrpos($cut, '! ') ?: 0, mb_strrpos($cut, '? ') ?: 0);

        return $end > 200 ? mb_substr($cut, 0, $end + 1) : Str::limit($text, 1200);
    }

    /**
     * The match's picture as a 1280x720 JPEG thumbnail: wide images are
     * cropped to fit, posters sit on a blurred, darkened copy of themselves.
     */
    public static function thumbnail(array $match): ?string
    {
        if (empty($match['image']) || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        try {
            $bytes = OutboundHttp::client(10)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0 (intranet media library)'])
                ->get($match['image'])->throw()->body();
        } catch (Throwable $e) {
            Log::warning('Movie poster download failed: '.$e->getMessage());

            return null;
        }

        $info = @getimagesizefromstring($bytes);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return null;
        }

        [$w, $h] = [imagesx($src), imagesy($src)];
        [$W, $H] = [1280, 720];
        $canvas = imagecreatetruecolor($W, $H);

        if ($w / $h >= 1.5) {
            $ch = $w * $H / $W; // crop to 16:9
            $cw = $ch > $h ? $h * $W / $H : $w;
            $ch = min($ch, $h);
            imagecopyresampled($canvas, $src, 0, 0, (int) (($w - $cw) / 2), (int) (($h - $ch) / 2), $W, $H, (int) $cw, (int) $ch);
        } else {
            // Blur the middle of the poster small (cheap), then stretch it to fill.
            $tiny = imagecreatetruecolor(160, 90);
            imagecopyresampled($tiny, $src, 0, 0, 0, (int) (($h - $w * 9 / 16) / 2), 160, 90, $w, (int) ($w * 9 / 16));
            for ($i = 0; $i < 12; $i++) {
                imagefilter($tiny, IMG_FILTER_GAUSSIAN_BLUR);
            }
            imagecopyresampled($canvas, $tiny, 0, 0, 0, 0, $W, $H, 160, 90);
            imagedestroy($tiny);
            imagefilledrectangle($canvas, 0, 0, $W, $H, imagecolorallocatealpha($canvas, 0, 0, 0, 60));

            $ph = $H - 40;
            $pw = (int) round($w * $ph / $h);
            imagecopyresampled($canvas, $src, (int) (($W - $pw) / 2), 20, 0, 0, $pw, $ph, $w, $h);
        }

        imagedestroy($src);
        ob_start();
        imagejpeg($canvas, null, 86);
        imagedestroy($canvas);

        return ob_get_clean() ?: null;
    }
}
