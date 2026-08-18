<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ShippingSetting;
use App\Support\HomepageCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $thresholdCents = ShippingSetting::current()->free_shipping_threshold_cents;
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with([
                'products' => fn ($query) => $query->active(),
                'products.variants.supplier',
                'children.products' => fn ($query) => $query->active(),
                'children.products.variants.supplier',
            ])
            ->orderBy('sort_order')
            ->get();
        $firstCategory = $categories->first();

        $freeShippingAmount = null;
        if ($thresholdCents !== null && $thresholdCents > 0) {
            $euros = $thresholdCents / 100;
            $freeShippingAmount = fmod($euros, 1.0) === 0.0
                ? number_format($euros, 0, ',', ' ').'€'
                : format_euros($thresholdCents);
        }

        $featured = HomepageCatalog::featured(10);

        // Only one product per root category is picked, so with 6 categories
        // "featured" tops out at 6 — fill the rest from the same pool "more"
        // draws from so the section still shows a full 10.
        if ($featured->count() < 10) {
            $featured = $featured->concat(
                HomepageCatalog::more($featured, 10 - $featured->count())
            );
        }
        $featured->load('discount');

        $more = HomepageCatalog::more($featured, 5);
        $more->load('discount');

        return view('home', [
            'freeShippingAmount' => $freeShippingAmount,
            'firstCategory' => $firstCategory,
            'categories' => $categories,
            'featured' => $featured,
            'more' => $more,
        ]);
    }
}
