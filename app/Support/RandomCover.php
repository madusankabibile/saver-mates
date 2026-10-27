<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gives a member a random wallpaper as their profile banner, so new profiles
 * don't all start on the same placeholder. The API answers with JSON whose
 * "data" field is an image URL; the image is downloaded and stored like an
 * uploaded banner. Any failure is logged and skipped - a missing banner must
 * never stop someone registering.
 */
class RandomCover
{
    protected const MAX_BYTES = 10 * 1024 * 1024;

    protected const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    /** Assign a random banner if the member has none. Returns whether one was set. */
    public static function assign(User $user): bool
    {
        $endpoint = config('services.wallpaper.url');

        if (! $endpoint || $user->cover) {
            return false;
        }

        $temp = null;

        try {
            $imageUrl = self::client(5)->get($endpoint)->throw()->json('data');

            if (! is_string($imageUrl) || ! preg_match('#^https?://#i', $imageUrl)) {
                throw new \RuntimeException('The wallpaper API returned no image URL.');
            }

            $body = self::client(10)->get($imageUrl)->throw()->body();

            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                throw new \RuntimeException('The wallpaper image was empty or too large.');
            }

            $temp = tempnam(sys_get_temp_dir(), 'cover');
            file_put_contents($temp, $body);

            // Only trust what the bytes actually are, not what the server says.
            $info = @getimagesize($temp);
            $ext = $info ? (self::TYPES[$info[2]] ?? null) : null;

            if (! $ext) {
                throw new \RuntimeException('The wallpaper was not a JPEG, PNG or WebP image.');
            }

            $file = new UploadedFile($temp, 'cover.'.$ext, $info['mime'], null, true);
            $path = ImageStore::put($file, 'covers', 2000, 700);

            // Another request may have set a banner meanwhile; theirs wins.
            if ($user->fresh()?->cover) {
                Storage::disk('public')->delete($path);

                return false;
            }

            $user->forceFill(['cover' => $path])->saveQuietly();

            return true;
        } catch (Throwable $e) {
            Log::warning('Random cover skipped for user '.$user->id.': '.$e->getMessage());

            return false;
        } finally {
            if ($temp && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    protected static function client(int $timeout): PendingRequest
    {
        return OutboundHttp::client($timeout);
    }
}
