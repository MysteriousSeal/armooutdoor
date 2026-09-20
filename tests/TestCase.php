<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    //

    /**
     * The address of a Vinted page of the product's draft, the first one
     * created if it has none yet.
     */
    protected function vintedRoute(string $name, \App\Models\Product $product): string
    {
        $listing = \App\Models\VintedListing::query()->firstOrCreate(['product_id' => $product->id]);

        return route('admin.products.vinted.'.$name, [$product, $listing]);
    }
}
