<?php

namespace App\Http\Controllers;

use App\Models\ShippingSetting;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $thresholdCents = ShippingSetting::current()->free_shipping_threshold_cents;
        $freeShippingAmount = $thresholdCents !== null && $thresholdCents > 0
            ? format_euros($thresholdCents)
            : null;

        return view('faq.index', [
            'freeShippingAmount' => $freeShippingAmount,
        ]);
    }
}
