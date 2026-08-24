<?php

namespace App\Support;

class ImageThumbnailer
{
    public const SIZE = 400;

    /** Bandeau d'article : paysage 16/9, et sa vignette de carte. */
    public const LANDSCAPE_WIDTH = 1600;

    public const LANDSCAPE_HEIGHT = 900;

    public const LANDSCAPE_CARD_WIDTH = 800;

    public const LANDSCAPE_CARD_HEIGHT = 450;

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
        return self::normalizeSquare($relativePath, self::MAIN_SIZE);
    }

    /**
     * Normalizes a local image to an exact square WebP of the given size —
     * scaled to fit (never cropped) and padded with transparency. Returns
     * the new relative path (extension may have changed), or null for
     * remote/unreadable images. Idempotent: an image that's already the
     * right size and format is left alone.
     */
    public static function normalizeSquare(string $relativePath, int $size, int $quality = 90): ?string
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
            $imageSize = @getimagesize($source);

            if ($imageSize && $imageSize[0] === $size && $imageSize[1] === $size) {
                return $relativePath;
            }
        }

        $image = self::load($source);

        if ($image === null) {
            return null;
        }

        $resized = self::resizeContain($image, $size, $size);
        imagewebp($resized, public_path('images/'.$newRelativePath), $quality);
        imagedestroy($image);
        imagedestroy($resized);

        if ($newRelativePath !== $relativePath) {
            @unlink($source);
        }

        return $newRelativePath;
    }

    /**
     * Ramène une image locale à un paysage exact, recadré pour remplir.
     *
     * Volontairement séparé de `normalizeSquare()` plutôt qu'ajouté en
     * paramètre : les fiches produit dépendent du carré, et un ratio partagé
     * finirait tôt ou tard par leur arriver dessus.
     */
    public static function normalizeLandscape(
        string $relativePath,
        int $width = self::LANDSCAPE_WIDTH,
        int $height = self::LANDSCAPE_HEIGHT,
        int $quality = 88,
    ): ?string {
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
            $imageSize = @getimagesize($source);

            if ($imageSize && $imageSize[0] === $width && $imageSize[1] === $height) {
                return $relativePath;
            }
        }

        $image = self::load($source);

        if ($image === null) {
            return null;
        }

        $resized = self::resizeCover($image, $width, $height);
        imagewebp($resized, public_path('images/'.$newRelativePath), $quality);
        imagedestroy($image);
        imagedestroy($resized);

        if ($newRelativePath !== $relativePath) {
            @unlink($source);
        }

        return $newRelativePath;
    }

    /** La vignette paysage qui accompagne un bandeau d'article. */
    public static function generateLandscapeThumbnail(string $relativePath): bool
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

        $resized = self::resizeCover($image, self::LANDSCAPE_CARD_WIDTH, self::LANDSCAPE_CARD_HEIGHT);
        imagewebp($resized, $thumbPath, 82);
        imagedestroy($image);
        imagedestroy($resized);

        return true;
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
