<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\VintedListing;
use App\Models\VintedListingImage;
use App\Support\ImageThumbnailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Illuminate\Support\Str;

/**
 * L'annonce Vinted d'un produit : la composer, la garder, la copier.
 *
 * Rien ne part d'ici. Vinted n'ouvre pas d'API pour déposer une annonce, et
 * c'est aussi bien : le moment du dépôt est celui où l'on décide qu'elle
 * part. Cette page tient le texte prêt d'une fois sur l'autre, avec de quoi
 * l'emporter champ par champ dans le formulaire de Vinted.
 */
class VintedListingController extends Controller
{
    public function edit(Product $product): View
    {
        return view('admin.products.vinted', [
            'product' => $product->load('images'),
            // Ce que l'unité a coûté : on fixe un prix Vinted en le voyant,
            // pas en s'en souvenant. Nul quand rien n'a été reçu — un coût
            // inconnu n'est pas un coût de zéro.
            'costCents' => $product->averagePurchaseCostInclVatCents(),
            // Une annonce jamais ouverte n'est pas encore une ligne en base :
            // la page part du produit, et n'écrit qu'à l'enregistrement.
            'listing' => $product->vintedListing()->with('images')->first()
                ?? $this->draftFrom($product),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            // Vinted plafonne à 500 000 € ; au-delà c'est une faute de frappe.
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
     * Une annonce vierge, entièrement. Rien n'est repris de la fiche : ce
     * qu'on écrit sur Vinted n'est pas ce qu'on écrit en catalogue, et un
     * champ prérempli se corrige moins bien qu'il ne s'écrit — on garde la
     * formule d'à côté faute de l'avoir effacée. Le prix de la boutique
     * reste sous les yeux, à côté du champ, sans s'y installer.
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
     * L'ordre voulu, appliqué aux seules photos de cette annonce : un
     * identifiant venu d'ailleurs ne déplace rien.
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
     * Le fichier part avec la ligne, et sa vignette avec lui : générée à
     * l'envoi, elle n'a plus rien à illustrer, et rien ne la balaierait plus
     * tard puisque aucune ligne ne la nomme.
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
