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
                'children.products' => fn ($query) => $query->active(),
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

        $featured = HomepageCatalog::featured();
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
