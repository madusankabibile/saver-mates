<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Large files (movies run to several GB) arrive in small pieces so they fit
 * under PHP's upload_max_filesize. Each piece lands in a per-user scratch
 * folder; the final request stitches them together into the target disk.
 */
class ChunkedUpload
{
    /** Kept well under the default 40 MB PHP upload limit. */
    public const CHUNK_BYTES = 8 * 1024 * 1024;

    public const MAX_CHUNKS = 1100; // ~8.6 GB

    public static function storeChunk(int $userId, string $uploadId, int $index, UploadedFile $chunk): void
    {
        $dir = self::dir($userId, $uploadId);
        File::ensureDirectoryExists($dir);

        $chunk->move($dir, (string) $index);

        self::sweepStale($userId);
    }

    /**
     * Concatenate chunks 0..total-1 into $path on $disk and return the byte size.
     */
    public static function assemble(int $userId, string $uploadId, int $total, string $disk, string $path): int
    {
        $dir = self::dir($userId, $uploadId);

        for ($i = 0; $i < $total; $i++) {
            if (! is_file($dir.DIRECTORY_SEPARATOR.$i)) {
                throw ValidationException::withMessages(['upload_id' => 'The upload is incomplete. Please try again.']);
            }
        }

        $target = Storage::disk($disk)->path($path);
        File::ensureDirectoryExists(dirname($target));

        $out = fopen($target, 'wb');

        for ($i = 0; $i < $total; $i++) {
            $in = fopen($dir.DIRECTORY_SEPARATOR.$i, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);
        self::discard($userId, $uploadId);

        return (int) filesize($target);
    }

    public static function discard(int $userId, string $uploadId): void
    {
        $dir = self::dir($userId, $uploadId);
        File::deleteDirectory($dir);

        // Drop the member's scratch folder too once nothing else is in flight.
        if (is_dir(dirname($dir)) && ! (new \FilesystemIterator(dirname($dir)))->valid()) {
            @rmdir(dirname($dir));
        }
    }

    public static function validId(string $uploadId): bool
    {
        return (bool) preg_match('/^[a-f0-9-]{36}$/', $uploadId);
    }

    protected static function dir(int $userId, string $uploadId): string
    {
        if (! self::validId($uploadId)) {
            throw ValidationException::withMessages(['upload_id' => 'Invalid upload id.']);
        }

        return storage_path('app/chunks/'.$userId.'/'.$uploadId);
    }

    /** Abandoned uploads older than a day are cleared the next time the user uploads. */
    protected static function sweepStale(int $userId): void
    {
        $root = storage_path('app/chunks/'.$userId);

        foreach (File::directories($root) as $dir) {
            if (filemtime($dir) < now()->subDay()->getTimestamp()) {
                File::deleteDirectory($dir);
            }
        }
    }
}
