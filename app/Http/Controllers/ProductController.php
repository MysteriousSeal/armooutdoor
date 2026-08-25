<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductSlug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Une adresse abandonnée mène à l'actuelle.
     *
     * Appelée quand le slug de l'URL ne désigne plus aucun produit. Sans
     * cela, renommer un produit rendait 404 tous les liens déjà partagés,
     * indexés ou imprimés sur une annonce.
     */
    public function movedOrMissing(Request $request, string $slug): RedirectResponse
    {
        $retired = ProductSlug::query()->retired()->where('slug', $slug)->first();

        abort_if($retired === null, 404);

        $product = $retired->product;

        // Le produit peut avoir été retiré de la vente depuis : la page
        // d'arrivée répondrait 404, autant le dire ici.
        abort_unless($product !== null && $product->is_active, 404);

        // 301 : le déménagement est définitif, et le référencement suit.
        return redirect()->route('products.show', $product, 301);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->load('category.parent', 'images', 'variants.supplier', 'reviews.user', 'discount', 'supplier');

        $related = Product::query()
            ->active()
            ->with('category', 'discount', 'variants.supplier')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }
}
