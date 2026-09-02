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
        $shipping = ShippingSetting::current();

        // A threshold with no carrier flagged grants free shipping on nothing,
        // so there is no figure to advertise. Both surfaces read this, so
        // neither can promise what checkout would not honour.
        $thresholdCents = ($shipping->free_shipping_carrier_ids ?? []) === []
            ? null
            : $shipping->free_shipping_threshold_cents;
        // The cards need a name, an icon and a count — never a product. Loading
        // the catalogue to call `count()` on it hydrated every active product
        // with its variants and its suppliers on every visit to the homepage;
        // counting in SQL asks the same question without the answer in memory.
        $categories = Category::query()
            ->whereNull('parent_id')
            ->withCount(['products' => fn ($query) => $query->active()])
            ->with([
                'children' => fn ($query) => $query->withCount([
                    'products' => fn ($inner) => $inner->active(),
                ]),
            ])
            ->orderBy('sort_order')
            ->get();

        $freeShippingAmount = null;
        if ($thresholdCents !== null && $thresholdCents > 0) {
            $euros = $thresholdCents / 100;
            $freeShippingAmount = fmod($euros, 1.0) === 0.0
                ? number_format($euros, 0, ',', ' ').'€'
                : format_euros($thresholdCents);
        }

        // The strip above the categories: only what is genuinely reduced, and
        // nothing at all when nothing is.
        $onSale = HomepageCatalog::onSale(10);

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
            'categories' => $categories,
            'onSale' => $onSale,
            'featured' => $featured,
            'more' => $more,
        ]);
    }
}
