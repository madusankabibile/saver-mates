<?php

namespace App\Support;

use App\Models\Song;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds a song's details (proper title, artist, album, genre, cover art) in
 * online music catalogues, working from the uploaded file's name: first the
 * Deezer API, then the iTunes Search API when Deezer has nothing suitable.
 *
 * File names are usually "Song Title Artist Name", so the search uses the
 * first three words, and whatever follows a result's title in the file name
 * is treated as the singer and has to agree with the result's artist. That
 * keeps other singers' versions of the same song out. Users never see where
 * the details come from; to them they are simply "found automatically".
 */
class SongLookup
{
    /** Versions to avoid unless the file name asks for them. */
    protected const VARIANTS = '/\b(live|remix|acoustic|karaoke|instrumental|cover|sped ?up|slowed|reverb|remaster(ed)?|version|edit|mix|unplugged)\b/i';

    protected const MIN_TITLE = 0.8;

    protected const MIN_ARTIST = 0.8;

    /** @var array<string, array> query => candidates, per request */
    protected static array $cache = [];

    /** @var array<int, string|null> album id => genre, per request */
    protected static array $genres = [];

    /** Forget remembered searches (each web request starts fresh; tests share a process). */
    public static function flush(): void
    {
        self::$cache = [];
        self::$genres = [];
    }

    public static function enabled(): bool
    {
        return (bool) (config('services.song_lookup.url') || config('services.song_lookup.fallback_url'));
    }

    /**
     * The best confident match for a song, or null.
     *
     * @param  string  $name  the song's name as uploaded (often "Title Artist")
     * @param  string|null  $artist  an artist the file itself names, if any
     */
    public static function find(string $name, ?string $artist = null, ?int $duration = null, ?string $filename = null): ?array
    {
        $name = trim($name);
        if (! self::enabled() || $name === '') {
            return null;
        }

        $words = preg_split('/\s+/', self::norm($name));
        $queries = array_values(array_unique(array_filter([
            implode(' ', array_slice($words, 0, 3)), // first three words find the most
            count($words) > 3 ? $name : null,
            $artist ? implode(' ', array_slice($words, 0, 3)).' '.$artist : null,
        ])));

        $partial = null;

        foreach (['primary', 'fallback'] as $source) {
            foreach ($queries as $query) {
                $best = null;

                foreach (self::searchSource($source, $query, 15) as $candidate) {
                    $fit = self::score($candidate, $name, $artist, $duration);
                    if (! $fit) {
                        continue;
                    }

                    if ($fit['full'] && (! $best || $fit['score'] > $best[0])) {
                        // The file's title was longer than the catalogue's: keep the file's words.
                        if ($fit['title_extra'] !== '') {
                            $candidate['title'] = self::original($name, trim(self::norm($candidate['title']).' '.$fit['title_extra']));
                        }
                        $best = [$fit['score'], $candidate];
                    }

                    // Same song, someone else's recording: the title is confirmed, and the
                    // rest of the file name is our singer. Remember the best such split
                    // (a "singer" of more than four words is more likely a long title).
                    $leftWords = $fit['leftover'] === '' ? 0 : count(explode(' ', $fit['leftover']));
                    if (! $fit['full'] && $leftWords >= 1 && $leftWords <= 4 && (! $partial || $fit['title_score'] > $partial['title_score'])) {
                        $partial = ['title_score' => $fit['title_score'], 'title' => $candidate['title'], 'artist' => self::original($name, $fit['leftover'])];
                    }
                }

                if ($best) {
                    return $best[1] + ['partial' => false];
                }
            }
        }

        // Nothing is our recording, but the name could still be split into
        // title and artist. Album, genre and cover belong to the other
        // recording, so they are not used.
        return $partial ? [
            'partial' => true,
            'key' => null,
            'title' => $partial['title'],
            'artist' => $partial['artist'],
            'album' => null, 'album_key' => null, 'album_id' => null, 'genre' => null,
            'cover' => null, 'thumb' => null, 'duration' => null, 'rank' => 0,
        ] : null;
    }

    /** The leftover words as they were written in the name, not normalised. */
    protected static function original(string $name, string $leftover): string
    {
        $tokens = preg_split('/[\s_]+/', trim(preg_replace('/[^\p{L}\p{N}\s_\'.-]+/u', ' ', $name)));
        $want = explode(' ', $leftover);
        $n = count($want);

        for ($i = 0; $i + $n <= count($tokens); $i++) {
            if (self::norm(implode(' ', array_slice($tokens, $i, $n))) === $leftover) {
                return implode(' ', array_slice($tokens, $i, $n));
            }
        }

        return Str::title($leftover);
    }

    /**
     * Search both catalogues, for the "Find song details" box on the edit page.
     *
     * @return array<int, array>
     */
    public static function search(string $query, int $limit = 8): array
    {
        $seen = [];
        $out = [];

        foreach (['primary', 'fallback'] as $source) {
            foreach (self::searchSource($source, $query, $limit) as $c) {
                $key = self::norm($c['title'].' '.$c['artist']);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $out[] = $c;
                }
            }
        }

        return array_slice($out, 0, $limit * 2);
    }

    /** One track by its "dz:123" / "it:456" key (when a user picks a search result). */
    public static function track(string $key): ?array
    {
        if (! preg_match('/^(dz|it):(\d{1,15})$/', $key, $m)) {
            return null;
        }

        try {
            if ($m[1] === 'dz' && config('services.song_lookup.url')) {
                $t = self::get(config('services.song_lookup.url'), '/track/'.$m[2], []);

                return is_array($t) && isset($t['id'], $t['title'], $t['artist']['name']) ? self::fromDeezer($t) : null;
            }

            if ($m[1] === 'it' && config('services.song_lookup.fallback_url')) {
                $t = self::get(config('services.song_lookup.fallback_url'), '/lookup', ['id' => $m[2], 'entity' => 'song'])['results'][0] ?? null;

                return is_array($t) && isset($t['trackId'], $t['trackName'], $t['artistName']) ? self::fromItunes($t) : null;
            }
        } catch (Throwable $e) {
            Log::warning('Song lookup track failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Fill a song from a match: title, artist (spelled the way the library
     * already spells that artist), album, genre and cover art.
     */
    public static function apply(Song $song, array $match): void
    {
        if ($match['partial'] ?? false) {
            $song->forceFill([
                'title' => Str::limit($match['title'], 250, ''),
                'artist' => self::libraryArtist($match['artist'], $song->id),
            ])->save();

            return;
        }

        $oldCover = $song->cover_path;
        $cover = self::storeCover($match) ?? $oldCover;

        $song->forceFill([
            'title' => Str::limit($match['title'], 250, ''),
            'artist' => self::libraryArtist($match['artist'], $song->id),
            'album' => $match['album'] ? Str::limit(self::cleanAlbum($match['album']), 250, '') : null,
            'genre' => ($match['genre'] ?? null) ?: (self::deezerGenre($match) ?? $song->genre),
            'cover_path' => $cover,
            'duration' => $song->duration ?: $match['duration'],
        ])->save();

        if ($oldCover && $oldCover !== $cover && ! Song::where('cover_path', $oldCover)->exists()) {
            Storage::disk(Song::DISK)->delete($oldCover);
        }
    }

    /** 0..1: how alike two names are, ignoring case, accents and punctuation. */
    public static function similarity(string $a, string $b): float
    {
        $a = self::norm($a);
        $b = self::norm($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        similar_text($a, $b, $percent);

        $wa = array_unique(explode(' ', $a));
        $wb = array_unique(explode(' ', $b));
        $jaccard = count(array_intersect($wa, $wb)) / max(1, count(array_unique([...$wa, ...$wb])));

        return max($percent / 100, $jaccard);
    }

    /* ------------------------------------------------------------------ */
    /* Matching                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * How well a result fits the uploaded name; null when the title differs.
     *
     * The result's title has to match the start of the name ("Title Artist")
     * or its end ("Artist Title"). The words left over are the singer: "full"
     * means they (and the length) agree with the result, so it is our
     * recording; otherwise only the title is confirmed.
     *
     * @return array{full: bool, score: float, title_score: float, leftover: string}|null
     */
    protected static function score(array $c, string $name, ?string $artist, ?int $duration): ?array
    {
        $nameWords = explode(' ', self::norm($name));
        $titleWords = explode(' ', self::norm($c['title']));
        $n = count($titleWords);

        // Transliterations differ a little ("Wisirunu" / "Visirunu"), so compare
        // fuzzily, and let the title part be a word longer or shorter to absorb
        // a word written split or joined ("Mal Pethi" / "Malpethi").
        $total = count($nameWords);
        $best = null;

        foreach ([$n, $n + 1, $n - 1] as $len) {
            if ($len < 1 || $len > $total) {
                continue;
            }

            $splits = [
                [array_slice($nameWords, 0, $len), array_slice($nameWords, $len)],          // "Title Artist"
                [array_slice($nameWords, $total - $len), array_slice($nameWords, 0, $total - $len)], // "Artist Title"
            ];

            foreach ($splits as [$titlePart, $rest]) {
                $s = self::similarity(implode(' ', $titlePart), $c['title']);
                if (! $best || $s > $best[0]) {
                    $best = [$s, implode(' ', $rest)];
                }
            }
        }

        [$titleScore, $leftover] = $best;
        if ($titleScore < self::MIN_TITLE) {
            return null;
        }

        // Who the file says sings it: the words left over, or the file's own artist tag.
        $leftover = trim($leftover);
        $claimed = array_filter([$artist, $leftover !== '' ? $leftover : null]);
        $artistScore = null;
        $titleExtra = '';

        foreach ($claimed as $who) {
            $artistScore = max($artistScore ?? 0, self::artistSimilarity($who, $c['artist']));
        }

        // "Kaulu Piyan Path Wahanna Kasun Kalhara": the catalogue's title is a word
        // short, so the leftover is "Wahanna Kasun Kalhara". When its end is the
        // artist, the words before that belong to the title, not the singer.
        if ($leftover !== '' && ($artistScore ?? 0) < self::MIN_ARTIST) {
            $words = explode(' ', $leftover);
            $k = count(explode(' ', self::norm($c['artist'])));

            if (count($words) > $k && $k > 0) {
                $tail = implode(' ', array_slice($words, -$k));
                $s = self::artistSimilarity($tail, $c['artist']);

                if ($s >= self::MIN_ARTIST) {
                    $artistScore = $s;
                    $titleExtra = implode(' ', array_slice($words, 0, -$k));
                }
            }
        }

        $fit = ['full' => false, 'score' => 0.0, 'title_score' => $titleScore, 'leftover' => $leftover, 'title_extra' => $titleExtra];

        if ($artistScore !== null && $artistScore < self::MIN_ARTIST) {
            return $fit; // someone else's version of the song
        }

        // Length has to be close; tighter when nothing confirms the artist.
        if ($duration && $c['duration'] && abs($duration - $c['duration']) > ($artistScore !== null ? 30 : 12)) {
            return $fit;
        }

        $score = $titleScore + ($artistScore ?? 0) * 0.5;

        if (preg_match(self::VARIANTS, $c['full_title'].' '.$c['album']) && ! preg_match(self::VARIANTS, $name)) {
            $score -= 0.2;
        }

        if ($duration && $c['duration'] && abs($duration - $c['duration']) <= 5) {
            $score += 0.1;
        }

        $fit['full'] = true;
        $fit['score'] = $score + min($c['rank'], 1_000_000) / 100_000_000; // popularity breaks ties

        return $fit;
    }

    /** Artist names: "A & B", "A, B" and "A feat. B" match on any of the names. */
    protected static function artistSimilarity(string $claimed, string $artist): float
    {
        $parts = preg_split('/\s*(?:&|,|\band\b|\bfeat\.?|\bft\.?|\bwith\b|\bx\b)\s*/i', $artist) ?: [];
        $best = self::similarity($claimed, $artist);

        foreach (array_filter($parts) as $part) {
            $best = max($best, self::similarity($claimed, $part));
        }

        return $best;
    }

    protected static function norm(string $s): string
    {
        $s = Str::ascii(mb_strtolower($s));
        $s = preg_replace('/\((feat|ft|with)[^)]*\)|\[[^\]]*\]|\b(feat|ft)\.? .*$/', ' ', $s);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /* ------------------------------------------------------------------ */
    /* Catalogues                                                          */
    /* ------------------------------------------------------------------ */

    /** @return array<int, array> normalised candidates */
    protected static function searchSource(string $source, string $query, int $limit): array
    {
        $query = trim($query);
        $base = config($source === 'primary' ? 'services.song_lookup.url' : 'services.song_lookup.fallback_url');

        if (! $base || $query === '') {
            return [];
        }

        $key = $source.'|'.$limit.'|'.mb_strtolower($query);
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            if ($source === 'primary') {
                $data = self::get($base, '/search', ['q' => Str::limit($query, 200, ''), 'limit' => $limit])['data'] ?? [];
                $list = collect(is_array($data) ? $data : [])
                    ->filter(fn ($t) => is_array($t) && isset($t['id'], $t['title'], $t['artist']['name']))
                    ->map(fn ($t) => self::fromDeezer($t));
            } else {
                $data = self::get($base, '/search', ['term' => Str::limit($query, 200, ''), 'entity' => 'song', 'limit' => $limit])['results'] ?? [];
                $list = collect(is_array($data) ? $data : [])
                    ->filter(fn ($t) => is_array($t) && isset($t['trackId'], $t['trackName'], $t['artistName']))
                    ->map(fn ($t) => self::fromItunes($t));
            }
        } catch (Throwable $e) {
            Log::warning("Song lookup search ({$source}) failed: ".$e->getMessage());

            return [];
        }

        return self::$cache[$key] = $list->values()->all();
    }

    protected static function get(string $base, string $path, array $query): mixed
    {
        // iTunes answers with text/javascript, so decode the body ourselves.
        $body = OutboundHttp::client(6)->get(rtrim($base, '/').$path, $query)->throw()->body();

        return json_decode($body, true);
    }

    protected static function fromDeezer(array $t): array
    {
        return [
            'key' => 'dz:'.$t['id'],
            'title' => (string) ($t['title_short'] ?? $t['title']),
            'full_title' => (string) $t['title'],
            'artist' => (string) $t['artist']['name'],
            'album' => $t['album']['title'] ?? null,
            'album_key' => isset($t['album']['id']) ? 'dz-'.$t['album']['id'] : null,
            'album_id' => isset($t['album']['id']) ? (int) $t['album']['id'] : null,
            'genre' => null, // needs a second call; fetched only for a song we keep
            'cover' => $t['album']['cover_xl'] ?? $t['album']['cover_big'] ?? null,
            'thumb' => $t['album']['cover_medium'] ?? $t['album']['cover_small'] ?? null,
            'duration' => isset($t['duration']) ? (int) $t['duration'] : null,
            'rank' => (int) ($t['rank'] ?? 0),
        ];
    }

    protected static function fromItunes(array $t): array
    {
        $art = $t['artworkUrl100'] ?? null;

        return [
            'key' => 'it:'.$t['trackId'],
            'title' => self::shortTitle((string) $t['trackName']),
            'full_title' => (string) $t['trackName'],
            'artist' => (string) $t['artistName'],
            'album' => $t['collectionName'] ?? null,
            'album_key' => isset($t['collectionId']) ? 'it-'.$t['collectionId'] : null,
            'album_id' => null,
            'genre' => isset($t['primaryGenreName']) && $t['primaryGenreName'] !== 'Worldwide' ? $t['primaryGenreName'] : null,
            // Artwork comes in any size by editing the URL.
            'cover' => $art ? preg_replace('#/\d+x\d+bb\.#', '/600x600bb.', $art) : null,
            'thumb' => $art,
            'duration' => isset($t['trackTimeMillis']) ? (int) round($t['trackTimeMillis'] / 1000) : null,
            'rank' => 0,
        ];
    }

    /** "Song (feat. X)" / "Song (Live)" -> "Song". */
    protected static function shortTitle(string $title): string
    {
        return trim(preg_replace('/\s*[\(\[][^\)\]]*[\)\]]\s*$/', '', $title)) ?: $title;
    }

    /** "Song - Single" / "Album - EP" -> the album's own name. */
    protected static function cleanAlbum(string $album): string
    {
        return trim(preg_replace('/\s+-\s+(Single|EP)$/i', '', $album));
    }

    /* ------------------------------------------------------------------ */
    /* Storing                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Use the library's existing spelling of an artist ("Piris" vs "Peiris").
     * Among close spellings, the one most songs already use wins, so the
     * library converges on a single name instead of splitting further.
     */
    protected static function libraryArtist(string $artist, ?int $exceptSongId = null): string
    {
        $known = Song::whereNotNull('artist')
            ->when($exceptSongId, fn ($q) => $q->whereKeyNot($exceptSongId))
            ->selectRaw('artist, COUNT(*) AS songs')
            ->groupBy('artist')
            ->pluck('songs', 'artist');

        $best = null;
        foreach ($known as $name => $songs) {
            $s = self::similarity((string) $name, $artist);
            if ($s >= 0.88 && (! $best || [$songs, $s] > [$best[0], $best[1]])) {
                $best = [$songs, $s, (string) $name];
            }
        }

        return Str::limit($best[2] ?? $artist, 250, '');
    }

    protected static function deezerGenre(array $match): ?string
    {
        $albumId = $match['album_id'] ?? null;
        if (! $albumId || ! config('services.song_lookup.url')) {
            return null;
        }

        if (array_key_exists($albumId, self::$genres)) {
            return self::$genres[$albumId];
        }

        try {
            $name = self::get(config('services.song_lookup.url'), '/album/'.$albumId, [])['genres']['data'][0]['name'] ?? null;
        } catch (Throwable) {
            $name = null;
        }

        return self::$genres[$albumId] = is_string($name) && $name !== '' ? Str::limit($name, 60, '') : null;
    }

    /** Album art is stored once per album and shared by its tracks. */
    protected static function storeCover(array $match): ?string
    {
        if (! $match['cover']) {
            return null;
        }

        $path = 'music/covers/album-'.($match['album_key'] ?: str_replace(':', '-', $match['key'])).'.jpg';
        $disk = Storage::disk(Song::DISK);

        if ($disk->exists($path)) {
            return $path;
        }

        $temp = null;

        try {
            $bytes = OutboundHttp::client(10)->get($match['cover'])->throw()->body();
            $temp = tempnam(sys_get_temp_dir(), 'art');
            file_put_contents($temp, $bytes);

            $info = @getimagesize($temp);
            if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                return null;
            }

            // Downscale through ImageStore, then keep it under the per-album name.
            $stored = ImageStore::put(new UploadedFile($temp, 'cover.jpg', $info['mime'], null, true), 'music/covers', 800, 800);
            $disk->move($stored, $path);

            return $path;
        } catch (Throwable $e) {
            Log::warning('Song cover download failed: '.$e->getMessage());

            return null;
        } finally {
            if ($temp && is_file($temp)) {
                @unlink($temp);
            }
        }
    }
}
