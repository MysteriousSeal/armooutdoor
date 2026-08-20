<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountCodeController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('account.discounts.index', [
            // Only codes reserved for this customer: public ones stay as they
            // are distributed rather than being listed for anyone who logs in.
            //
            // Usability is decided by eligibilityError(), the same method
            // checkout uses. Reimplementing the rules here would eventually
            // let this page offer a code the checkout then refuses.
            'codes' => $user->discountCodes()
                ->get()
                ->filter(fn (DiscountCode $code): bool => $code->eligibilityError($user) === null)
                ->values(),
        ]);
    }
}
