<?php

namespace App\Console\Commands;

use App\Models\Movie;
use App\Models\Song;
use App\Models\User;
use App\Support\MediaLibrary;
use App\Support\MediaMeta;
use App\Support\Subtitles;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Bulk-copies movies or songs from a folder (a USB drive, a share) into the
 * library, as if a member had uploaded them through the site.
 *
 *   php artisan media:import movies "F:\Movies" --user=nimal --limit=8
 *   php artisan media:import songs "F:\Songs" --user=nimal --per-folder=6
 */
class ImportMedia extends Command
{
    protected $signature = 'media:import
        {type : movies or songs}
        {path : folder to copy from}
        {--user= : username of the member the uploads belong to (default: first member)}
        {--limit=0 : maximum number of files to import (0 = no limit)}
        {--per-folder=0 : songs only - maximum tracks per artist folder}
        {--category= : movies only - category to file them under}
        {--all-formats : movies only - include MKV/MOV, which many browsers cannot play}';

    protected $description = 'Copy movies or songs from a folder into the media library';

    public function handle(): int
    {
        $root = rtrim($this->argument('path'), '\\/');

        if (! is_dir($root)) {
            $this->error("Folder not found: {$root}");

            return self::FAILURE;
        }

        $user = $this->option('user')
            ? User::where('username', $this->option('user'))->first()
            : User::orderBy('id')->first();

        if (! $user) {
            $this->error('No such member. Pass --user=<username>.');

            return self::FAILURE;
        }

        return match ($this->argument('type')) {
            'movies' => $this->importMovies($root, $user),
            'songs' => $this->importSongs($root, $user),
            default => tap(self::FAILURE, fn () => $this->error('Type must be "movies" or "songs".')),
        };
    }

    /* ---------------------------------------------------------------- */
    /* Movies                                                            */
    /* ---------------------------------------------------------------- */

    protected function importMovies(string $root, User $user): int
    {
        $extensions = $this->option('all-formats') ? MediaLibrary::VIDEO_EXTENSIONS : ['mp4', 'm4v', 'webm'];
        $videos = $this->filesWith($root, $extensions);
        $limit = (int) $this->option('limit');
        $imported = 0;

        foreach ($videos as $video) {
            if ($limit && $imported >= $limit) {
                break;
            }

            $title = $this->movieTitle($video);

            if (Movie::where('title', $title)->exists()) {
                $this->line("  skip  {$title} (already in the library)");
                continue;
            }

            $this->info("  copy  {$title}  (".\App\Models\DriveFile::humanSize(filesize($video)).')');

            $path = MediaLibrary::moviePath($video);
            $target = Storage::disk(Movie::DISK)->path($path);
            File::ensureDirectoryExists(dirname($target));

            if (! copy($video, $target)) {
                $this->error('        copy failed');
                continue;
            }

            $movie = MediaLibrary::createMovie($user, $path, [
                'title' => $title,
                'category' => $this->option('category'),
                'description' => 'Imported from '.basename(dirname($video)).'.',
            ]);

            foreach ($this->subtitlesFor($video, $videos) as $sub) {
                $added = MediaLibrary::addSubtitle($movie, file_get_contents($sub), basename($sub));
                $this->line("        subtitle: {$added->label}");
            }

            if ($poster = $this->posterFor($video)) {
                MediaLibrary::storeThumbnail($movie, new UploadedFile($poster, basename($poster), null, null, true));
                $this->line('        thumbnail: '.basename($poster));
            }

            $imported++;
        }

        $this->info("Imported {$imported} movie(s). Movies without a thumbnail get one captured from the video the first time their uploader opens them.");

        return self::SUCCESS;
    }

    /** "Luck (2022) [720p] [WEBRip]" or "Encanto.2021.720p.WEBRip" become "Luck (2022)" / "Encanto (2021)". */
    protected function movieTitle(string $video): string
    {
        $folder = basename(dirname($video));

        if (preg_match('/(19|20)\d{2}/', $folder) && ! preg_match('/^new folder/i', $folder)) {
            return $this->cleanTitle($folder);
        }

        return $this->cleanTitle(pathinfo($video, PATHINFO_FILENAME));
    }

    protected function cleanTitle(string $raw): string
    {
        $s = preg_replace('/\[[^\]]*\]|www\.\S+/i', ' ', $raw);
        $s = preg_replace('/([a-z])((19|20)\d{2})/i', '$1 $2', $s); // Vivo2021
        $s = trim(preg_replace('/\s+/', ' ', str_replace(['.', '_'], ' ', $s)));

        if (preg_match('/^(.*?)[\s(\-]*((19|20)\d{2})\b/', $s, $m) && trim($m[1]) !== '') {
            return trim($m[1], " -(").' ('.$m[2].')';
        }

        return trim(preg_replace('/\b(720p|1080p|2160p|4k|webrip|bluray|web-dl|hdrip|x264|x265|hevc|aac.*)$/i', '', $s));
    }

    /**
     * Subtitles beside the video. When the folder holds several movies, only
     * files sharing the video's first title word are taken. One per language.
     */
    protected function subtitlesFor(string $video, array $allVideos): array
    {
        $dir = dirname($video);
        $soleVideo = count(array_filter($allVideos, fn ($v) => str_starts_with($v, $dir.DIRECTORY_SEPARATOR))) === 1;

        $candidates = $soleVideo
            ? $this->filesWith($dir, ['srt', 'vtt'])
            : array_filter(
                File::glob($dir.DIRECTORY_SEPARATOR.'*.{srt,vtt,SRT}', GLOB_BRACE),
                fn ($s) => stripos(basename($s), strtok(pathinfo($video, PATHINFO_FILENAME), '. _-')) === 0
            );

        $byLanguage = [];
        foreach ($candidates as $sub) {
            [, $label] = Subtitles::guessLanguage(basename($sub));
            $byLanguage[$label] ??= $sub;
        }

        return array_slice(array_values($byLanguage), 0, 4);
    }

    /** A poster image beside the video, ignoring release-group logos like www.YTS.MX.jpg. */
    protected function posterFor(string $video): ?string
    {
        foreach (File::glob(dirname($video).DIRECTORY_SEPARATOR.'*.{jpg,jpeg,png,webp}', GLOB_BRACE) as $image) {
            if (! preg_match('/^www\./i', basename($image))) {
                return $image;
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------- */
    /* Songs                                                             */
    /* ---------------------------------------------------------------- */

    protected function importSongs(string $root, User $user): int
    {
        $perFolder = (int) $this->option('per-folder');
        $limit = (int) $this->option('limit');

        // Pick the files first: [path, artist folder].
        $picked = [];
        foreach (array_merge([$root], File::directories($root)) as $dir) {
            $files = array_filter(File::files($dir), fn ($f) => in_array(strtolower($f->getExtension()), MediaLibrary::AUDIO_EXTENSIONS, true));
            usort($files, fn ($a, $b) => strcmp($a->getFilename(), $b->getFilename()));

            if ($perFolder) {
                $files = array_slice($files, 0, $perFolder);
            }

            foreach ($files as $file) {
                $picked[] = [$file->getPathname(), $dir === $root ? null : basename($dir)];
            }
        }

        if ($limit) {
            $picked = array_slice($picked, 0, $limit);
        }

        // Artwork that turns up across many artist folders is a download site's
        // logo, not album art, so it is left off.
        $coverFolders = [];
        foreach ($picked as [$path, $folder]) {
            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'mp3' && ($cover = MediaMeta::mp3Info($path)['cover'])) {
                $coverFolders[md5($cover['data'])][$folder ?? ''] = true;
            }
        }
        $isAlbumArt = fn (string $hash) => count($coverFolders[$hash] ?? []) < 3;

        $imported = 0;
        $bar = $this->output->createProgressBar(count($picked));

        foreach ($picked as [$path, $folder]) {
            $bar->advance();

            $sameSize = Song::where('size', filesize($path))->get();
            if ($sameSize->contains(fn ($s) => md5_file(Storage::disk(Song::DISK)->path($s->path)) === md5_file($path))) {
                continue; // identical file already imported
            }

            $stored = MediaLibrary::songPath($path);
            $target = Storage::disk(Song::DISK)->path($stored);
            File::ensureDirectoryExists(dirname($target));
            copy($path, $target);

            MediaLibrary::createSong($user, $stored, basename($path), $folder, null, $isAlbumArt);
            $imported++;
        }

        $bar->finish();
        $this->newLine();
        $this->info("Imported {$imported} song(s).");

        return self::SUCCESS;
    }

    protected function filesWith(string $dir, array $extensions): array
    {
        $out = [];
        foreach (File::allFiles($dir) as $file) {
            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $out[] = $file->getPathname();
            }
        }
        sort($out);

        return $out;
    }
}
