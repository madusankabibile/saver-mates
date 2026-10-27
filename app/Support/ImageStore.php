<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded images on the public disk, downscaling oversized photos with
 * GD so avatars, banners and post attachments stay light on an intranet link.
 */
class ImageStore
{
    public static function put(UploadedFile $file, string $directory, int $maxWidth = 1600, int $maxHeight = 1600): string
    {
        $name = Str::uuid().'.'.self::extensionFor($file);
        $path = trim($directory, '/').'/'.$name;

        $resized = self::downscale($file, $maxWidth, $maxHeight);

        if ($resized !== null) {
            Storage::disk('public')->put($path, $resized);

            return $path;
        }

        // Unsupported format for GD (or GD unavailable) - keep the original.
        return $file->storeAs(trim($directory, '/'), $name, 'public');
    }

    protected static function extensionFor(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? $ext : 'jpg';
    }

    /**
     * @return string|null Encoded image bytes, or null when we should not touch the file.
     */
    protected static function downscale(UploadedFile $file, int $maxWidth, int $maxHeight): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $info = @getimagesize($file->getRealPath());

        if ($info === false) {
            return null;
        }

        [$width, $height] = $info;
        $type = $info[2];

        $source = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_GIF => @imagecreatefromgif($file->getRealPath()),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($file->getRealPath()) : null,
            default => null,
        };

        if (! $source) {
            return null;
        }

        $scale = min($maxWidth / $width, $maxHeight / $height, 1);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for PNG/GIF/WebP sources.
        if (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();

        match ($type) {
            IMAGETYPE_PNG => imagepng($canvas, null, 8),
            IMAGETYPE_GIF => imagegif($canvas),
            IMAGETYPE_WEBP => imagewebp($canvas, null, 85),
            default => imagejpeg($canvas, null, 86),
        };

        $bytes = ob_get_clean();
        imagedestroy($canvas);

        return $bytes !== false && $bytes !== '' ? $bytes : null;
    }
}
