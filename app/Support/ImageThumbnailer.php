<?php

namespace App\Support;

class ImageThumbnailer
{
    public const SIZE = 400;
    public const MAIN_SIZE = 1000;

    /**
     * Generates a 400x400 WebP thumbnail for a local image living under
     * public/images/, saved alongside the original in a thumbs/ subfolder.
     * Returns false for remote (http/https) images or unreadable files.
     */
    public static function generate(string $relativePath): bool
    {
        if ($relativePath === '' || str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return false;
        }

        $source = public_path('images/'.$relativePath);

        if (! is_file($source)) {
            return false;
        }

        $image = self::load($source);

        if ($image === null) {
            return false;
        }

        $thumbPath = self::absoluteThumbnailPath($relativePath);
        $directory = dirname($thumbPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $resized = self::resizeCover($image, self::SIZE, self::SIZE);
        imagewebp($resized, $thumbPath, 82);
        imagedestroy($image);
        imagedestroy($resized);

        return true;
    }

    /**
     * Normalizes a local image to exactly 1000x1000 WebP — scaled to fit
     * (never cropped, since the product stage displays with object-fit:
     * contain) and padded with transparency. Returns the new relative path
     * (extension may have changed), or null for remote/unreadable images.
     * Idempotent: an image that's already 1000x1000 WebP is left alone.
     */
    public static function normalizeMain(string $relativePath): ?string
    {
        if ($relativePath === '' || str_starts_with($relativePath, 'http://') || str_starts_with($relativePath, 'https://')) {
            return null;
        }

        $source = public_path('images/'.$relativePath);

        if (! is_file($source)) {
            return null;
        }

        $info = pathinfo($relativePath);
        $dir = ($info['dirname'] === '.') ? '' : $info['dirname'].'/';
        $newRelativePath = $dir.$info['filename'].'.webp';

        if ($relativePath === $newRelativePath) {
            $size = @getimagesize($source);

            if ($size && $size[0] === self::MAIN_SIZE && $size[1] === self::MAIN_SIZE) {
                return $relativePath;
            }
        }

        $image = self::load($source);

        if ($image === null) {
            return null;
        }

        $resized = self::resizeContain($image, self::MAIN_SIZE, self::MAIN_SIZE);
        imagewebp($resized, public_path('images/'.$newRelativePath), 90);
        imagedestroy($image);
        imagedestroy($resized);

        if ($newRelativePath !== $relativePath) {
            @unlink($source);
        }

        return $newRelativePath;
    }

    /**
     * The public asset URL for an image's thumbnail — falls back to the
     * full-size image for remote URLs or when no thumbnail exists yet.
     */
    public static function urlFor(?string $image): string
    {
        if ($image === null || $image === '') {
            return '';
        }

        if (str_starts_with($image, 'https://') || str_starts_with($image, 'http://')) {
            return $image;
        }

        if (! is_file(self::absoluteThumbnailPath($image))) {
            return asset('images/'.$image);
        }

        return asset('images/'.self::relativeThumbnailPath($image));
    }

    public static function relativeThumbnailPath(string $relativePath): string
    {
        $info = pathinfo($relativePath);
        $dir = ($info['dirname'] === '.') ? '' : $info['dirname'].'/';

        return $dir.'thumbs/'.$info['filename'].'.webp';
    }

    public static function absoluteThumbnailPath(string $relativePath): string
    {
        return public_path('images/'.self::relativeThumbnailPath($relativePath));
    }

    /**
     * Sniffs actual file content rather than trusting the extension — a
     * handful of downloaded images ended up with a mismatched extension
     * (e.g. JPEG bytes saved as .webp), which a purely extension-based
     * decoder would fail to read.
     *
     * @return \GdImage|null
     */
    private static function load(string $path)
    {
        $contents = @file_get_contents($path);

        if ($contents !== false) {
            $image = @imagecreatefromstring($contents);

            if ($image !== false) {
                return $image;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'avif' && function_exists('imagecreatefromavif')) {
            $image = @imagecreatefromavif($path);

            return $image ?: null;
        }

        return null;
    }

    /**
     * Center-crops the source to the target aspect ratio, then resamples
     * down to the exact target size — a "cover" fit, same as the CSS
     * object-fit: cover used everywhere these thumbnails are displayed.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private static function resizeCover($image, int $width, int $height)
    {
        $srcWidth = imagesx($image);
        $srcHeight = imagesy($image);
        $srcRatio = $srcWidth / $srcHeight;
        $dstRatio = $width / $height;

        if ($srcRatio > $dstRatio) {
            $cropHeight = $srcHeight;
            $cropWidth = (int) round($srcHeight * $dstRatio);
        } else {
            $cropWidth = $srcWidth;
            $cropHeight = (int) round($srcWidth / $dstRatio);
        }

        $srcX = (int) (($srcWidth - $cropWidth) / 2);
        $srcY = (int) (($srcHeight - $cropHeight) / 2);

        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled($dst, $image, 0, 0, $srcX, $srcY, $width, $height, $cropWidth, $cropHeight);

        return $dst;
    }

    /**
     * Scales the source to fit entirely within the target box (no
     * cropping) and centers it on a transparent canvas of that exact
     * size — a "contain" fit, matching the product stage's
     * object-fit: contain display.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private static function resizeContain($image, int $width, int $height)
    {
        $srcWidth = imagesx($image);
        $srcHeight = imagesy($image);
        $scale = min($width / $srcWidth, $height / $srcHeight);
        $dstWidth = max(1, (int) round($srcWidth * $scale));
        $dstHeight = max(1, (int) round($srcHeight * $scale));
        $offsetX = (int) (($width - $dstWidth) / 2);
        $offsetY = (int) (($height - $dstHeight) / 2);

        $dst = imagecreatetruecolor($width, $height);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefill($dst, 0, 0, $transparent);

        imagecopyresampled($dst, $image, $offsetX, $offsetY, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        return $dst;
    }
}
