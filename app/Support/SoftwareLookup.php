<?php

namespace App\Support;

use App\Models\Software;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds software details from the uploaded file's name.
 * Primary source: GitHub Search API (free, no key, 60 req/hour).
 * Fallback: Wikipedia API.
 */
class SoftwareLookup
{
    /**
     * Find details for a software file name.
     *
     * @return array{key: string, title: string, version: string|null, description: string|null, os_type: string|null, category: string|null, image: string|null}|null
     */
    public static function find(string $filename): ?array
    {
        $name = self::cleanName($filename);

        if ($name === '') {
            return null;
        }

        return self::fromGitHub($name) ?? self::fromWikipedia($name);
    }

    /**
     * Fetch the thumbnail image bytes for a match returned by find().
     */
    public static function thumbnail(array $match): ?string
    {
        if (empty($match['image'])) {
            return null;
        }

        try {
            $bytes = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0 (intranet software library)'])
                ->get($match['image'])
                ->throw()
                ->body();

            if (empty($bytes)) {
                return null;
            }

            // If GD is not available, return bytes as-is (caller will base64-encode).
            if (! function_exists('imagecreatefromstring')) {
                return $bytes;
            }

            $info = @getimagesizefromstring($bytes);
            if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
                return null;
            }

            // For square-ish images (avatars) keep them square; resize to max 256x256.
            $src = @imagecreatefromstring($bytes);
            if (! $src) {
                return null;
            }

            [$w, $h] = [imagesx($src), imagesy($src)];
            $size = 256;
            $canvas = imagecreatetruecolor($size, $size);

            // Fill with dark background.
            $bg = imagecolorallocate($canvas, 20, 20, 20);
            imagefill($canvas, 0, 0, $bg);

            $scale = min($size / $w, $size / $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $ox = (int) (($size - $nw) / 2);
            $oy = (int) (($size - $nh) / 2);

            imagecopyresampled($canvas, $src, $ox, $oy, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);

            ob_start();
            imagejpeg($canvas, null, 85);
            imagedestroy($canvas);

            return ob_get_clean() ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Strip the file extension and common version/release patterns from a filename,
     * returning a clean software name suitable for searching.
     */
    public static function cleanName(string $name): string
    {
        // Remove extension (.exe .msi .dmg .pkg .deb .rpm .apk .zip .tar.gz .AppImage .snap .flatpak)
        $name = preg_replace('/\.(exe|msi|dmg|pkg|deb|rpm|apk|zip|tar\.gz|AppImage|snap|flatpak|7z|rar|tar\.bz2|tar\.xz|run|sh)$/i', '', $name);

        // Remove version patterns: v1.2.3, 1.2.3, _v1.2.3, -1.2.3
        $name = preg_replace('/[\s_\-]+v?\d+[\.\d]+[a-z0-9\-]*/i', ' ', $name);

        // Remove common release descriptors
        $name = preg_replace('/[\s_\-]+(setup|installer|install|portable|x64|x86|amd64|arm64|win32|win64|windows|win|macos|mac|linux|ubuntu|debian|fedora|alpha|beta|rc\d*|stable|release|final|full|lite|pro|free|trial|community|enterprise|classic)\b/i', ' ', $name);

        // Replace separators with spaces
        $name = preg_replace('/[_\-\.]+/', ' ', $name);

        // Collapse whitespace
        $name = trim(preg_replace('/\s+/', ' ', $name));

        return $name;
    }

    /**
     * Search GitHub for a matching repository and extract software details.
     */
    public static function fromGitHub(string $name): ?array
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent'  => 'SaverMate/1.0 (intranet software library)',
                    'Accept'      => 'application/vnd.github+json',
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

        $items = $response['items'] ?? [];
        if (! is_array($items) || empty($items)) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($items as $repo) {
            if (! isset($repo['name'], $repo['full_name'])) {
                continue;
            }

            $repoName = str_replace(['-', '_', '.'], ' ', $repo['name']);
            similar_text(mb_strtolower($name), mb_strtolower($repoName), $pct);

            if ($pct < 40) {
                continue;
            }

            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = $repo;
            }
        }

        if (! $best) {
            return null;
        }

        $topics    = $best['topics'] ?? [];
        $desc      = $best['description'] ?? null;
        $topicsStr = implode(' ', array_map('strtolower', $topics));

        return [
            'key'         => 'gh:' . $best['full_name'],
            'title'       => $best['name'],
            'version'     => null,
            'description' => $desc ? Str::limit($desc, 1000) : null,
            'os_type'     => self::detectOsFromTopics($topics, $topicsStr),
            'category'    => self::detectCategoryFromTopics($topics, (string) $desc),
            'image'       => $best['owner']['avatar_url'] ?? null,
        ];
    }

    /**
     * Search Wikipedia for a matching software article.
     */
    public static function fromWikipedia(string $name): ?array
    {
        try {
            $pages = Http::timeout(8)
                ->withHeaders(['User-Agent' => 'SaverMate/1.0 (intranet software library)'])
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
                    'pithumbsize' => 256,
                    'pilicense'   => 'any',
                ])
                ->throw()
                ->json('query.pages') ?? [];
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($pages) || empty($pages)) {
            return null;
        }

        // Take the first page result.
        $page = array_values($pages)[0];
        if (! isset($page['title'])) {
            return null;
        }

        $extract = trim(preg_replace('/\s+/', ' ', (string) ($page['extract'] ?? '')));

        // Extract up to 3 sentences.
        $sentences = preg_split('/(?<=[.!?])\s+/', $extract, 4);
        $description = implode(' ', array_slice($sentences ?? [], 0, 3));
        $description = Str::limit($description, 1000) ?: null;

        $text = mb_strtolower($extract);

        return [
            'key'         => 'wp:' . $page['pageid'],
            'title'       => $page['title'],
            'version'     => null,
            'description' => $description ?: null,
            'os_type'     => self::detectOsFromTopics([], $text),
            'category'    => self::detectCategoryFromTopics([], $extract),
            'image'       => $page['thumbnail']['source'] ?? null,
        ];
    }

    /**
     * Map GitHub topics or a text string to one of Software::OS_TYPES.
     */
    public static function detectOsFromTopics(array $topics, string $text = ''): ?string
    {
        $haystack = mb_strtolower(implode(' ', $topics) . ' ' . $text);

        if (preg_match('/\bcross[- ]?platform\b|\bmulti[- ]?platform\b|\ball[- ]?platform\b/i', $haystack)) {
            return 'Cross-platform';
        }
        if (preg_match('/\bwindows\b|\bwin32\b|\bwin64\b|\bwinpe\b/i', $haystack)) {
            return 'Windows';
        }
        if (preg_match('/\bmacos\b|\bmac-?os\b|\bosx\b|\bos-?x\b|\bapple\b|\bswift\b|\bobjective-c\b/i', $haystack)) {
            return 'macOS';
        }
        if (preg_match('/\blinux\b|\bubuntu\b|\bdebian\b|\bfedora\b|\barch\b|\bgnu\b/i', $haystack)) {
            return 'Linux';
        }
        if (preg_match('/\bandroid\b/i', $haystack)) {
            return 'Android';
        }
        if (preg_match('/\bios\b|\biphone\b|\bipad\b|\bswift\b/i', $haystack)) {
            return 'iOS';
        }
        if (preg_match('/\bweb\b|\bbrowser\b|\bchrome\b|\bfirefox\b|\bsafari\b|\bextension\b/i', $haystack)) {
            return 'Web';
        }

        return null;
    }

    /**
     * Map GitHub topics and description to one of Software::CATEGORIES.
     */
    public static function detectCategoryFromTopics(array $topics, string $desc): ?string
    {
        $text = mb_strtolower(implode(' ', $topics) . ' ' . $desc);

        $map = [
            'Developer Tools'       => '/\b(developer|development|devtool|ide|debugger|compiler|linter|code|editor|cli|sdk|api|framework|library|git|testing|ci|cd)\b/',
            'Security'              => '/\b(security|antivirus|firewall|vpn|encryption|password|pentest|hacking|malware|vulnerability)\b/',
            'Graphics & Design'     => '/\b(graphic|design|image|photo|drawing|vector|illustration|3d|rendering|art|ui|ux|figma|sketch)\b/',
            'Media & Entertainment' => '/\b(media|player|audio|video|music|stream|podcast|movie|entertainment)\b/',
            'Games'                 => '/\b(game|gaming|emulator|steam|unity|unreal|engine|rpg|fps)\b/',
            'Networking'            => '/\b(network|networking|server|proxy|dns|http|tcp|ssh|ftp|router|monitor)\b/',
            'Education'             => '/\b(education|learning|tutorial|course|school|study|academic|math|science)\b/',
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
