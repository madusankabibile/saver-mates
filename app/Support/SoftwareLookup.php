<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds software metadata and a relevant icon from the uploaded file's name.
 *
 * Source priority:
 *  1. iTunes Search API  — real app icons, mobile/desktop apps, free, no key
 *  2. GitHub Search API  — OSS metadata, free, 60 req/hour unauthenticated
 *  3. Wikipedia API      — software logos from article infoboxes
 *
 * Images from GitHub owner avatars are intentionally skipped; they are
 * profile photos, not software icons.
 */
class SoftwareLookup
{
    public static function find(string $filename): ?array
    {
        $name = self::cleanName($filename);
        if ($name === '') {
            return null;
        }

        // iTunes has real app icons — try it first.
        $itunes = self::fromItunes($name);
        if ($itunes) {
            return $itunes;
        }

        // GitHub for OSS metadata, Wikipedia for logo; merge the best of both.
        $gh = self::fromGitHub($name);
        $wp = self::fromWikipedia($name);

        if ($gh && $wp) {
            // Wikipedia article images are actual software logos; prefer them.
            if (empty($gh['image']) && ! empty($wp['image'])) {
                $gh['image'] = $wp['image'];
            }
            if (empty($gh['description']) && ! empty($wp['description'])) {
                $gh['description'] = $wp['description'];
            }
            return $gh;
        }

        return $gh ?? $wp;
    }

    /**
     * Fetch and process the thumbnail image for a match.
     * Returns raw JPEG bytes, or null on failure.
     */
    public static function thumbnail(array $match): ?string
    {
        if (empty($match['image'])) {
            return null;
        }

        try {
            $bytes = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0'])
                ->get($match['image'])
                ->throw()
                ->body();

            if (empty($bytes)) {
                return null;
            }

            if (! function_exists('imagecreatefromstring')) {
                return $bytes;
            }

            $info = @getimagesizefromstring($bytes);
            if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                return null;
            }

            $src = @imagecreatefromstring($bytes);
            if (! $src) {
                return null;
            }

            [$w, $h] = [imagesx($src), imagesy($src)];
            $size   = 256;
            $canvas = imagecreatetruecolor($size, $size);
            $bg     = imagecolorallocate($canvas, 20, 20, 20);
            imagefill($canvas, 0, 0, $bg);

            $scale = min($size / $w, $size / $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $ox = (int) (($size - $nw) / 2);
            $oy = (int) (($size - $nh) / 2);

            imagecopyresampled($canvas, $src, $ox, $oy, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);

            ob_start();
            imagejpeg($canvas, null, 88);
            imagedestroy($canvas);

            return ob_get_clean() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Strip extension, version numbers and release tags from a filename.
     */
    public static function cleanName(string $name): string
    {
        $name = preg_replace('/\.(exe|msi|dmg|pkg|deb|rpm|apk|zip|tar\.gz|tar\.bz2|tar\.xz|AppImage|snap|flatpak|7z|rar|run|sh|jar|bin|iso)$/i', '', $name);
        $name = preg_replace('/[\s_\-]+v?\d+[\.\d]*[a-z0-9\-]*/i', ' ', $name);
        $name = preg_replace('/[\s_\-]+(setup|installer|install|portable|x64|x86|amd64|arm64|win32|win64|windows|win|macos|mac|linux|ubuntu|debian|fedora|alpha|beta|rc\d*|stable|release|final|full|lite|pro|free|trial|community|enterprise|classic)\b/i', ' ', $name);
        $name = preg_replace('/[_\-\.]+/', ' ', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    // -------------------------------------------------------------------------
    // Sources
    // -------------------------------------------------------------------------

    /**
     * iTunes / App Store search — the best source for mobile and desktop app icons.
     * Returns artworkUrl512 which is the actual app icon.
     */
    private static function fromItunes(string $name): ?array
    {
        try {
            $json = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0'])
                ->get('https://itunes.apple.com/search', [
                    'term'    => $name,
                    'entity'  => 'software',
                    'limit'   => 5,
                    'country' => 'us',
                ])
                ->throw()
                ->json();
        } catch (\Throwable) {
            return null;
        }

        $results = $json['results'] ?? [];
        if (empty($results)) {
            return null;
        }

        $best      = null;
        $bestScore = 0.0;

        foreach ($results as $app) {
            $appName = $app['trackName'] ?? ($app['artistName'] ?? '');
            similar_text(mb_strtolower($name), mb_strtolower($appName), $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = $app;
            }
        }

        if (! $best || $bestScore < 35) {
            return null;
        }

        // artworkUrl512 is the high-res app icon.
        $icon = $best['artworkUrl512'] ?? ($best['artworkUrl100'] ?? null);

        $devices  = implode(' ', $best['supportedDevices'] ?? []);
        $os       = self::detectOsFromItunes($best, $devices);
        $category = $best['primaryGenreName'] ?? null;
        $category = self::mapItunesCategory($category);

        return [
            'key'         => 'it:' . ($best['trackId'] ?? $best['artistId'] ?? ''),
            'title'       => $best['trackName'] ?? $name,
            'version'     => $best['version'] ?? null,
            'description' => isset($best['description'])
                ? Str::limit(strip_tags($best['description']), 800)
                : null,
            'os_type'  => $os,
            'category' => $category,
            'image'    => $icon,
        ];
    }

    /**
     * GitHub repository search — good for OSS apps; we never use the owner avatar.
     */
    private static function fromGitHub(string $name): ?array
    {
        try {
            $json = Http::timeout(8)
                ->withHeaders([
                    'User-Agent'           => 'SaverMate/1.0',
                    'Accept'               => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ])
                ->get('https://api.github.com/search/repositories', [
                    'q'        => $name,
                    'sort'     => 'stars',
                    'per_page' => 5,
                ])
                ->throw()
                ->json();
        } catch (\Throwable) {
            return null;
        }

        $items = $json['items'] ?? [];
        if (empty($items)) {
            return null;
        }

        $best      = null;
        $bestScore = 0.0;

        foreach ($items as $repo) {
            $repoName = str_replace(['-', '_', '.'], ' ', $repo['name'] ?? '');
            similar_text(mb_strtolower($name), mb_strtolower($repoName), $pct);
            if ($pct >= 40 && $pct > $bestScore) {
                $bestScore = $pct;
                $best = $repo;
            }
        }

        if (! $best) {
            return null;
        }

        $topics    = $best['topics'] ?? [];
        $topicsStr = implode(' ', array_map('strtolower', $topics));
        $desc      = $best['description'] ?? null;

        return [
            'key'         => 'gh:' . $best['full_name'],
            'title'       => $best['name'],
            'version'     => null,
            'description' => $desc ? Str::limit($desc, 800) : null,
            'os_type'     => self::detectOsFromTopics($topics, $topicsStr),
            'category'    => self::detectCategoryFromTopics($topics, (string) $desc),
            'image'       => null, // Owner avatar is a profile photo, not a software icon.
        ];
    }

    /**
     * Wikipedia — article images are actual software logos from the infobox.
     */
    private static function fromWikipedia(string $name): ?array
    {
        try {
            $pages = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0'])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action'      => 'query',
                    'format'      => 'json',
                    'redirects'   => 1,
                    'generator'   => 'search',
                    'gsrsearch'   => Str::limit($name, 200, '') . ' software',
                    'gsrlimit'    => 3,
                    'prop'        => 'extracts|pageimages',
                    'exintro'     => 1,
                    'explaintext' => 1,
                    'exlimit'     => 'max',
                    'piprop'      => 'thumbnail',
                    'pithumbsize' => 512,
                    'pilicense'   => 'any',
                ])
                ->throw()
                ->json('query.pages') ?? [];
        } catch (\Throwable) {
            return null;
        }

        if (empty($pages)) {
            return null;
        }

        $page = array_values($pages)[0];
        if (! isset($page['title'])) {
            return null;
        }

        $extract     = trim(preg_replace('/\s+/', ' ', (string) ($page['extract'] ?? '')));
        $sentences   = preg_split('/(?<=[.!?])\s+/', $extract, 4);
        $description = Str::limit(implode(' ', array_slice($sentences ?? [], 0, 3)), 800) ?: null;
        $text        = mb_strtolower($extract);

        return [
            'key'         => 'wp:' . ($page['pageid'] ?? 0),
            'title'       => $page['title'],
            'version'     => null,
            'description' => $description,
            'os_type'     => self::detectOsFromTopics([], $text),
            'category'    => self::detectCategoryFromTopics([], $extract),
            'image'       => $page['thumbnail']['source'] ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function detectOsFromItunes(array $app, string $devices): ?string
    {
        $kind = strtolower($app['kind'] ?? '');
        if ($kind === 'software') {
            $platform = strtolower($app['features'][0] ?? '');
            if (str_contains($devices, 'iPhone') || str_contains($devices, 'iPad')) {
                return 'iOS';
            }
            if (str_contains($devices, 'WatchOS') || str_contains($devices, 'AppleTV')) {
                return 'iOS';
            }
            return 'macOS';
        }
        if ($kind === 'mac-software') {
            return 'macOS';
        }
        return null;
    }

    private static function mapItunesCategory(?string $genre): ?string
    {
        if (! $genre) {
            return null;
        }
        $map = [
            'Productivity'           => 'Productivity',
            'Utilities'              => 'Utilities',
            'Developer Tools'        => 'Developer Tools',
            'Graphics & Design'      => 'Graphics & Design',
            'Photo & Video'          => 'Graphics & Design',
            'Music'                  => 'Media & Entertainment',
            'Entertainment'          => 'Media & Entertainment',
            'Games'                  => 'Games',
            'Education'              => 'Education',
            'Business'               => 'Productivity',
            'Finance'                => 'Productivity',
            'Social Networking'      => 'Productivity',
            'News'                   => 'Productivity',
            'Reference'              => 'Education',
            'Medical'                => 'Utilities',
            'Health & Fitness'       => 'Utilities',
            'Travel'                 => 'Utilities',
            'Navigation'             => 'Utilities',
            'Lifestyle'              => 'Utilities',
            'Food & Drink'           => 'Utilities',
            'Shopping'               => 'Utilities',
            'Sports'                 => 'Utilities',
            'Weather'                => 'Utilities',
            'Book'                   => 'Education',
            'Networking'             => 'Networking',
        ];
        foreach ($map as $key => $category) {
            if (stripos($genre, $key) !== false) {
                return $category;
            }
        }
        return 'Other';
    }

    public static function detectOsFromTopics(array $topics, string $text = ''): ?string
    {
        $h = mb_strtolower(implode(' ', $topics) . ' ' . $text);

        if (preg_match('/\bcross[- ]?platform\b|\bmulti[- ]?platform\b/i', $h)) return 'Cross-platform';
        if (preg_match('/\bwindows\b|\bwin32\b|\bwin64\b/i', $h))             return 'Windows';
        if (preg_match('/\bmacos\b|\bmac-?os\b|\bosx\b/i', $h))               return 'macOS';
        if (preg_match('/\blinux\b|\bubuntu\b|\bdebian\b|\bfedora\b/i', $h))  return 'Linux';
        if (preg_match('/\bandroid\b/i', $h))                                  return 'Android';
        if (preg_match('/\bios\b|\biphone\b|\bipad\b/i', $h))                 return 'iOS';
        if (preg_match('/\bbrowser\b|\bchrome[- ]extension\b/i', $h))         return 'Web';

        return null;
    }

    public static function detectCategoryFromTopics(array $topics, string $desc): ?string
    {
        $text = mb_strtolower(implode(' ', $topics) . ' ' . $desc);

        $map = [
            'Developer Tools'       => '/\b(developer|devtool|ide|debugger|compiler|linter|code|editor|cli|sdk|api|framework|library|git|testing)\b/',
            'Security'              => '/\b(security|antivirus|firewall|vpn|encryption|password|pentest|malware)\b/',
            'Graphics & Design'     => '/\b(graphic|design|image|photo|drawing|vector|illustration|3d|rendering|art|ui|ux)\b/',
            'Media & Entertainment' => '/\b(media|player|audio|video|music|stream|podcast|movie|entertainment)\b/',
            'Games'                 => '/\b(game|gaming|emulator|steam|unity|unreal|rpg|fps)\b/',
            'Networking'            => '/\b(network|networking|server|proxy|dns|http|tcp|ssh|ftp|router)\b/',
            'Education'             => '/\b(education|learning|tutorial|course|school|study|academic)\b/',
            'Utilities'             => '/\b(util|utility|tool|system|cleaner|optimizer|backup|sync|file|manager|archiver|compress|converter)\b/',
            'Productivity'          => '/\b(productivity|office|word|spreadsheet|calendar|task|project|note|todo|email|pdf)\b/',
        ];

        foreach ($map as $category => $pattern) {
            if (preg_match($pattern, $text)) {
                return $category;
            }
        }

        return 'Other';
    }
}
