<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds software metadata and a relevant icon from the uploaded file's name.
 *
 * Source priority:
 *  1. FileCR             — screenshots + metadata for Windows/macOS/Android apps
 *  2. iTunes Search API  — real app icons for iOS/macOS apps, free, no key
 *  3. GitHub Search API  — OSS metadata, free, 60 req/hour unauthenticated
 *  4. Wikipedia API      — software logos from article infoboxes
 *
 * GitHub owner avatars are intentionally skipped (profile photos ≠ app icons).
 */
class SoftwareLookup
{
    public static function find(string $filename): ?array
    {
        $name = self::cleanName($filename);
        if ($name === '') {
            return null;
        }

        // FileCR has screenshots and metadata for most popular desktop/mobile apps.
        $fcr = self::fromFileCr($name);
        if ($fcr) {
            return $fcr;
        }

        // iTunes has real app icons — good for iOS/macOS apps not on FileCR.
        $itunes = self::fromItunes($name);
        if ($itunes) {
            return $itunes;
        }

        // GitHub for OSS metadata, Wikipedia for logo; merge the best of both.
        $gh = self::fromGitHub($name);
        $wp = self::fromWikipedia($name);

        if ($gh && $wp) {
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
     * FileCR search — screenshots and metadata for popular Windows/macOS/Android apps.
     * Scrapes the HTML search results page then the detail page.
     */
    private static function fromFileCr(string $name): ?array
    {
        try {
            $searchHtml = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SaverMate/1.0)'])
                ->get('https://filecr.com/search/', ['q' => $name])
                ->throw()
                ->body();
        } catch (\Throwable) {
            return null;
        }

        // Parse the search results to find the best-matching software link.
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($searchHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // Cards are <a> tags with href like /windows/slug/ or /android/slug/
        $links = $xpath->query('//a[contains(@href, "/windows/") or contains(@href, "/macos/") or contains(@href, "/android/") or contains(@href, "/ms-windows/")]/@href');

        $best      = null;
        $bestScore = 0.0;

        if ($links) {
            $seen = [];
            foreach ($links as $attr) {
                $href = trim($attr->value);
                // Must be a detail page: /{platform}/{slug}/ — exactly 3 segments
                if (! preg_match('#^/([a-z\-]+)/([a-z0-9\-]+)/$#', $href, $m)) {
                    continue;
                }
                if (isset($seen[$href])) continue;
                $seen[$href] = true;

                $slug     = str_replace('-', ' ', $m[2]);
                $nameLow  = mb_strtolower($name);
                $slugLow  = mb_strtolower($slug);
                similar_text($nameLow, $slugLow, $pct);
                // Boost score when the slug starts with the search term (e.g. "vlc" → "vlc media player").
                if (str_starts_with($slugLow, $nameLow)) {
                    $pct = max($pct, 60.0);
                }
                if ($pct > $bestScore) {
                    $bestScore = $pct;
                    $best = ['href' => $href, 'platform' => $m[1]];
                }
            }
        }

        if (! $best || $bestScore < 35) {
            return null;
        }

        // Fetch the detail page.
        try {
            $detailHtml = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SaverMate/1.0)'])
                ->get('https://filecr.com' . $best['href'])
                ->throw()
                ->body();
        } catch (\Throwable) {
            return null;
        }

        $dom2 = new \DOMDocument();
        $dom2->loadHTML($detailHtml, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xp2 = new \DOMXPath($dom2);

        // Title from <h1>
        $h1s   = $xp2->query('//h1');
        $rawTitle = $h1s && $h1s->length > 0 ? trim($h1s->item(0)->textContent) : '';
        if ($rawTitle === '') return null;

        // Split title into name + version: "VLC Media Player 3.0.24" → name="VLC Media Player", version="3.0.24"
        $version = null;
        $title   = $rawTitle;
        if (preg_match('/^(.*?)\s+(v?\d[\d\.]+[a-z0-9\-]*)$/i', $rawTitle, $vm)) {
            $title   = trim($vm[1]);
            $version = ltrim($vm[2], 'vV');
        }

        // Description: first non-empty paragraph that is long enough.
        $desc = null;
        $paras = $xp2->query('//p');
        if ($paras) {
            foreach ($paras as $p) {
                $text = trim($p->textContent);
                if (mb_strlen($text) > 60 && ! str_starts_with(strtolower($text), 'free download')) {
                    $desc = Str::limit($text, 800);
                    break;
                }
                if (mb_strlen($text) > 60) {
                    $desc = Str::limit($text, 800);
                    // keep looking for a non-"Free Download" paragraph
                }
            }
        }

        // First screenshot image (wsrv.nl CDN).
        $image = null;
        $imgs  = $xp2->query('//img[contains(@src, "imgcdn.org") or contains(@src, "wsrv.nl")]');
        if ($imgs && $imgs->length > 0) {
            $src = trim($imgs->item(0)->getAttribute('src'));
            if ($src !== '') $image = $src;
        }

        // OS type from URL platform segment.
        $osMap = [
            'windows'    => 'Windows',
            'ms-windows' => 'Windows',
            'macos'      => 'macOS',
            'mac'        => 'macOS',
            'android'    => 'Android',
            'ios'        => 'iOS',
            'linux'      => 'Linux',
        ];
        $os = $osMap[$best['platform']] ?? null;

        // Category from the first category link on the detail page.
        $catLinks = $xp2->query('//a[contains(@href, "/' . $best['platform'] . '/") and string-length(@href) > 10]');
        $category = null;
        if ($catLinks) {
            foreach ($catLinks as $cl) {
                $href2 = $cl->getAttribute('href');
                // Category links are /{platform}/{category}/ — skip the software itself
                if ($href2 === $best['href']) continue;
                $catText = trim($cl->textContent);
                if ($catText !== '' && mb_strlen($catText) < 40) {
                    $category = self::mapFileCrCategory($catText);
                    break;
                }
            }
        }

        return [
            'key'         => 'fcr:' . $best['href'],
            'title'       => $title,
            'version'     => $version,
            'description' => $desc,
            'os_type'     => $os,
            'category'    => $category,
            'image'       => $image,
        ];
    }

    private static function mapFileCrCategory(string $cat): ?string
    {
        $cat = strtolower($cat);
        $map = [
            'multimedia'          => 'Media & Entertainment',
            'audio'               => 'Media & Entertainment',
            'video'               => 'Media & Entertainment',
            'graphic'             => 'Graphics & Design',
            'design'              => 'Graphics & Design',
            'photo'               => 'Graphics & Design',
            'office'              => 'Productivity',
            'productivity'        => 'Productivity',
            'developer'           => 'Developer Tools',
            'programming'         => 'Developer Tools',
            'security'            => 'Security',
            'antivirus'           => 'Security',
            'network'             => 'Networking',
            'internet'            => 'Networking',
            'education'           => 'Education',
            'game'                => 'Games',
            'tools'               => 'Utilities',
            'utility'             => 'Utilities',
            'system'              => 'Utilities',
        ];
        foreach ($map as $keyword => $category) {
            if (str_contains($cat, $keyword)) return $category;
        }
        return 'Other';
    }

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
