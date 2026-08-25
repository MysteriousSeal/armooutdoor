<?php

namespace App\Support;

/**
 * Les images imprimées sur les PDF, réduites une fois pour toutes.
 *
 * Le générateur de PDF décode le fichier entier avant de le réduire à la
 * taille de la case, et embarque le résultat dans le document. Une photo de
 * produit de 1000×1000 coûtait une demi-seconde par ligne et alourdissait le
 * fichier, pour une vignette imprimée de 36 px.
 *
 * Le cache vit dans storage/, pas dans public/ : ce sont des fichiers de
 * travail, pas des images du site. La clé porte la date du fichier source,
 * donc une photo remplacée regénère la sienne toute seule.
 */
class PdfImageCache
{
    /** Deux fois la taille d'affichage, pour rester net à l'impression. */
    public const SIZE = 80;

    public static function pathFor(?string $source): ?string
    {
        if ($source === null || $source === '' || ! is_file($source)) {
            return null;
        }

        $cached = self::directory().'/'.sha1($source.'|'.filemtime($source)).'.png';

        if (is_file($cached)) {
            return $cached;
        }

        return self::render($source, $cached);
    }

    private static function render(string $source, string $cached): ?string
    {
        $image = @imagecreatefromstring((string) file_get_contents($source));

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = max($width, $height) / self::SIZE;

        // Une image déjà petite est recopiée telle quelle : l'agrandir ne
        // rendrait rien de plus, et coûterait une passe de rééchantillonnage.
        $targetWidth = $scale > 1 ? (int) round($width / $scale) : $width;
        $targetHeight = $scale > 1 ? (int) round($height / $scale) : $height;

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        // Le fond blanc plutôt que la transparence : le papier est blanc, et
        // une couche alpha alourdit le PDF sans rien changer à l'œil.
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $directory = dirname($cached);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $written = imagepng($resized, $cached);

        imagedestroy($image);
        imagedestroy($resized);

        return $written ? $cached : null;
    }

    private static function directory(): string
    {
        return storage_path('app/pdf-images');
    }
}
