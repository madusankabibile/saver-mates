<?php

namespace App\Support;

use App\Models\Movie;
use App\Models\MovieSubtitle;
use App\Models\Song;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates movie and song records from files already placed on the public
 * disk. Shared by the browser upload flow and the media:import command.
 */
class MediaLibrary
{
    public const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'webm', 'mkv', 'mov', 'ogv'];

    public const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'aac', 'ogg', 'oga', 'opus', 'wav', 'flac', 'weba'];

    public static function moviePath(string $originalName): string
    {
        return 'movies/videos/'.Str::uuid().'.'.self::extension($originalName, self::VIDEO_EXTENSIONS, 'video');
    }

    public static function songPath(string $originalName): string
    {
        return 'music/tracks/'.Str::uuid().'.'.self::extension($originalName, self::AUDIO_EXTENSIONS, 'audio');
    }

    protected static function extension(string $name, array $allowed, string $kind): string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'That is not a supported '.$kind.' file. Use '.strtoupper(implode(', ', $allowed)).'.',
            ]);
        }

        return $ext;
    }

    /* ---------------------------------------------------------------- */
    /* Movies                                                            */
    /* ---------------------------------------------------------------- */

    public static function createMovie(User $user, string $storedPath, array $attributes, ?int $durationHint = null): Movie
    {
        $absolute = Storage::disk(Movie::DISK)->path($storedPath);
        $mime = self::sniff($absolute);

        if (! str_starts_with($mime, 'video/')) {
            Storage::disk(Movie::DISK)->delete($storedPath);
            throw ValidationException::withMessages(['file' => 'The uploaded file does not look like a video.']);
        }

        $duration = in_array($mime, ['video/mp4', 'video/quicktime', 'video/x-m4v'], true)
            ? MediaMeta::mp4Duration($absolute)
            : null;

        return Movie::create([
            'user_id' => $user->id,
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'category' => $attributes['category'] ?? null,
            'video_path' => $storedPath,
            'video_mime' => $mime,
            'video_size' => filesize($absolute),
            'duration' => $duration ?: ($durationHint ?: null),
        ]);
    }

    public static function storeThumbnail(Movie $movie, UploadedFile $image): void
    {
        $old = $movie->thumbnail_path;
        $movie->update(['thumbnail_path' => ImageStore::put($image, 'movies/thumbs', 1280, 720)]);

        if ($old) {
            Storage::disk(Movie::DISK)->delete($old);
        }
    }

    public static function addSubtitle(Movie $movie, string $contents, string $filename, ?string $lang = null, ?string $label = null): MovieSubtitle
    {
        [$guessLang, $guessLabel] = Subtitles::guessLanguage($filename);
        $lang = $lang ?: $guessLang;

        $path = 'movies/subs/'.Str::uuid().'.vtt';
        Storage::disk(Movie::DISK)->put($path, Subtitles::toVtt($contents));

        return $movie->subtitles()->create([
            'lang' => $lang,
            'label' => Str::limit($label ?: ($lang === $guessLang ? $guessLabel : Subtitles::labelFor($lang)), 60, ''),
            'path' => $path,
        ]);
    }

    /* ---------------------------------------------------------------- */
    /* Songs                                                             */
    /* ---------------------------------------------------------------- */

    /**
     * @param  callable|null  $acceptCover  receives the md5 of embedded artwork; return false to skip it
     */
    public static function createSong(User $user, string $storedPath, string $originalName, ?string $folderArtist = null, ?int $durationHint = null, ?callable $acceptCover = null, bool $lookup = false): Song
    {
        $disk = Storage::disk(Song::DISK);
        $absolute = $disk->path($storedPath);
        $mime = self::sniff($absolute);
        $ext = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));

        $isAudio = str_starts_with($mime, 'audio/')
            || ($mime === 'video/mp4' && $ext === 'm4a')
            || ($mime === 'video/webm' && $ext === 'weba')
            || ($mime === 'application/octet-stream' && $ext === 'mp3');

        if (! $isAudio) {
            $disk->delete($storedPath);
            throw ValidationException::withMessages(['file' => 'The uploaded file does not look like audio.']);
        }

        $meta = ['title' => null, 'artist' => null, 'album' => null, 'genre' => null, 'cover' => null, 'duration' => null];

        if ($ext === 'mp3') {
            $meta = MediaMeta::mp3Info($absolute);
            $mime = 'audio/mpeg';
        } elseif (in_array($ext, ['m4a', 'aac'], true)) {
            $meta['duration'] = MediaMeta::mp4Duration($absolute);
            $mime = 'audio/mp4';
        }

        $tags = SongTags::clean($meta, $originalName, $folderArtist);
        $duration = $meta['duration'] ?: ($durationHint ?: null);

        // Member uploads: look the song up by its name. A match supplies all
        // the details; without one only the song's name is kept, rather than
        // guessing from tags that are often wrong or full of site names.
        if ($lookup) {
            $song = Song::create([
                'user_id' => $user->id,
                'title' => $tags['title'],
                'path' => $storedPath,
                'mime' => $mime,
                'size' => filesize($absolute),
                'duration' => $duration,
            ]);

            $match = SongLookup::find($tags['title'], $tags['artist'], $duration, $originalName);

            if ($match) {
                SongLookup::apply($song, $match);
                $song->detailsFound = $match['partial'] ? 'partial' : 'full';
            }

            return $song;
        }

        $coverPath = null;
        if ($meta['cover']) {
            $hash = md5($meta['cover']['data']);

            if (! $acceptCover || $acceptCover($hash)) {
                // Albums share artwork, so covers are stored once per image.
                $coverPath = 'music/covers/'.$hash.($meta['cover']['mime'] === 'image/png' ? '.png' : '.jpg');
                if (! $disk->exists($coverPath)) {
                    $disk->put($coverPath, $meta['cover']['data']);
                }
            }
        }

        return Song::create([
            'user_id' => $user->id,
            'title' => $tags['title'],
            'artist' => $tags['artist'],
            'album' => $tags['album'],
            'genre' => $tags['genre'],
            'path' => $storedPath,
            'mime' => $mime,
            'size' => filesize($absolute),
            'duration' => $duration,
            'cover_path' => $coverPath,
        ]);
    }

    protected static function sniff(string $absolute): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: 'application/octet-stream';

        return $mime === 'audio/mp3' ? 'audio/mpeg' : $mime;
    }
}
