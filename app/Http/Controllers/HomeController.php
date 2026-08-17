<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ShippingSetting;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $thresholdCents = ShippingSetting::current()->free_shipping_threshold_cents;
        $firstCategory = Category::query()->whereNull('parent_id')->orderBy('sort_order')->first();

        $freeShippingAmount = null;
        if ($thresholdCents !== null && $thresholdCents > 0) {
            $euros = $thresholdCents / 100;
            $freeShippingAmount = fmod($euros, 1.0) === 0.0
                ? number_format($euros, 0, ',', ' ').'€'
                : format_euros($thresholdCents);
        }

        return view('home', [
            'freeShippingAmount' => $freeShippingAmount,
            'firstCategory' => $firstCategory,
        ]);
    }
}
