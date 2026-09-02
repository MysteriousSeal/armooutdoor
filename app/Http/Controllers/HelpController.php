<?php

namespace App\Http\Controllers;

use App\Models\ShippingSetting;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function about(): View
    {
        return view('help.about', [
            'company' => \App\Models\CompanySetting::current(),
        ]);
    }

    public function shippingReturns(): View
    {
        $thresholdCents = ShippingSetting::current()->free_shipping_threshold_cents;
        $freeShippingAmount = $thresholdCents !== null && $thresholdCents > 0
            ? format_euros($thresholdCents)
            : null;

        return view('help.shipping-returns', [
            'freeShippingAmount' => $freeShippingAmount,
        ]);
    }

    public function securePayment(): View
    {
        return view('help.secure-payment');
    }
}
