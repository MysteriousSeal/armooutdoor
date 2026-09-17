<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use Illuminate\Http\Response;

/**
 * A product's photos, one by one, handed over as JPEGs.
 *
 * The shop stores WebP, which marketplace forms and suppliers will not take.
 * Each download is a copy converted on the way out: the file on disk is left
 * alone.
 */
class ProductPhotoController extends Controller
{
    /** Enough for a marketplace listing without doubling the file. */
    private const JPEG_QUALITY = 90;

    public function cover(Product $product): Response
    {
        abort_if($product->image === '', 404);

        return $this->jpeg($product->image, $product->photoDownloadName(1));
    }

    public function gallery(Product $product, ProductImage $photo): Response
    {
        // The image must belong to this product: without the check, the id
        // in the address would serve any of them.
        abort_unless($photo->product_id === $product->id, 404);

        return $this->jpeg($photo->image, $product->photoDownloadName($product->galleryPhotoPosition($photo)));
    }

    public function variant(Product $product, ProductVariant $variant): Response
    {
        abort_unless($variant->product_id === $product->id, 404);

        // Only the variant's own photo: without one it shows the cover,
        // which has its own link.
        abort_if(blank($variant->image), 404);

        return $this->jpeg($variant->image, $variant->setRelation('product', $product)->photoDownloadName());
    }

    private function jpeg(string $relativePath, string $filename): Response
    {
        $source = public_path('images/'.$relativePath);

        abort_unless(is_file($source), 404);

        $decoded = @imagecreatefromstring((string) file_get_contents($source));

        abort_if($decoded === false, 404);

        // A JPEG has no transparency: anything see-through would come out
        // black without a ground of its own.
        $flattened = imagecreatetruecolor(imagesx($decoded), imagesy($decoded));
        imagefill($flattened, 0, 0, imagecolorallocate($flattened, 255, 255, 255));
        imagecopy($flattened, $decoded, 0, 0, 0, 0, imagesx($decoded), imagesy($decoded));

        ob_start();
        imagejpeg($flattened, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();

        imagedestroy($decoded);
        imagedestroy($flattened);

        return response($jpeg, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
