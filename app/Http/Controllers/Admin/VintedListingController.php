<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VintedListing;
use App\Models\VintedListingImage;
use App\Support\ImageThumbnailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A product's Vinted listing: compose it, keep it, copy it.
 *
 * Nothing leaves from here. Vinted opens no API for depositing a listing,
 * and just as well: the moment of posting is the moment one decides it goes
 * up. This page holds the wording ready from one time to the next, with what
 * it takes to carry it field by field into Vinted's own form.
 */
class VintedListingController extends Controller
{
    /** Enough for a listing, not enough to weigh three megabytes. */
    private const JPEG_QUALITY = 90;

    public function edit(Product $product): View
    {
        return view('admin.products.vinted', [
            'product' => $product->load('images'),
            // What the unit cost: a Vinted price is set by seeing it, not by
            // remembering it. Null when nothing has been received — an
            // unknown cost is not a cost of zero.
            'costCents' => $product->averagePurchaseCostInclVatCents(),
            // A listing never opened is not a row yet: the page starts from
            // the product, and writes only on save.
            'listing' => $product->vintedListing()->with('images')->first()
                ?? $this->draftFrom($product),
        ]);
    }

    /**
     * One of the listing's photos, as a JPEG.
     *
     * The shop stores WebP, and Vinted's form will not take it — no more
     * than a supplier or a printer will. The file on disk is left alone:
     * this is a copy made for the download and thrown away with the
     * response.
     */
    public function downloadImage(Product $product, VintedListingImage $image): Response
    {
        // The photo must belong to this product's listing: without the
        // check, the id in the address would serve any of them.
        abort_unless(
            $image->listing !== null && $image->listing->product_id === $product->id,
            404,
        );

        $source = public_path('images/'.$image->image);

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
            'Content-Disposition' => 'attachment; filename="'.$image->downloadName().'"',
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Vinted caps at 500,000 €; past that it is a typing slip.
            'price' => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:8192'],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => ['integer'],
            'order' => ['nullable', 'array'],
            'order.*' => ['integer'],
        ]);

        $listing = VintedListing::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'price_cents' => array_key_exists('price', $data) && $data['price'] !== null
                    ? (int) round((float) $data['price'] * 100)
                    : null,
            ],
        );

        $this->removeImages($listing, $data['remove_images'] ?? []);
        $this->addImages($listing, $product, $request->file('images', []) ?? []);
        $this->reorderImages($listing, $data['order'] ?? []);

        return redirect()
            ->route('admin.products.vinted.edit', $product)
            ->with('success', 'Vinted listing saved.');
    }

    /**
     * A blank listing, entirely. Nothing is carried over from the product
     * page: what one writes on Vinted is not what one writes in a catalogue,
     * and a prefilled field gets corrected rather than written — one keeps
     * the phrasing next door for want of having cleared it. The shop's price
     * stays in view beside the field without settling into it.
     */
    private function draftFrom(Product $product): VintedListing
    {
        $listing = new VintedListing;
        $listing->product_id = $product->id;
        $listing->setRelation('images', collect());

        return $listing;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function removeImages(VintedListing $listing, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $images = $listing->images()->whereIn('id', $ids)->get();

        foreach ($images as $image) {
            $this->deleteFile($image->image);
            $image->delete();
        }
    }

    /**
     * @param  array<int, UploadedFile|null>  $files
     */
    private function addImages(VintedListing $listing, Product $product, array $files): void
    {
        $position = (int) $listing->images()->max('sort_order');

        foreach (array_filter($files) as $file) {
            VintedListingImage::query()->create([
                'vinted_listing_id' => $listing->id,
                'image' => $this->storeUploadedImage($file, $product->slug),
                'sort_order' => ++$position,
            ]);
        }
    }

    /**
     * The requested order, applied to this listing's photos only: an id
     * from anywhere else moves nothing.
     *
     * @param  array<int, int>  $ids
     */
    private function reorderImages(VintedListing $listing, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $images = $listing->images()->get()->keyBy('id');
        $position = 0;

        foreach ($ids as $id) {
            $image = $images->get((int) $id);

            if ($image !== null) {
                $image->update(['sort_order' => ++$position]);
            }
        }
    }

    private function storeUploadedImage(UploadedFile $file, string $slug): string
    {
        $directory = public_path('images/vinted');

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $name = Str::slug($slug).'-'.Str::lower(Str::random(6)).'.'.$file->getClientOriginalExtension();
        $file->move($directory, $name);

        $relativePath = ImageThumbnailer::normalizeMain('vinted/'.$name) ?? 'vinted/'.$name;

        ImageThumbnailer::generate($relativePath);

        return $relativePath;
    }

    /**
     * The file goes with the row, and its thumbnail with it: generated on
     * upload, it has nothing left to illustrate, and nothing would sweep it
     * later since no row names it.
     */
    private function deleteFile(string $image): void
    {
        foreach ([public_path('images/'.$image), ImageThumbnailer::absoluteThumbnailPath($image)] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
}
