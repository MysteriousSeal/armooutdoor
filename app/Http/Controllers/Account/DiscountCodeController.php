<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountCodeController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.discounts.index', [
            // Only codes reserved for this customer: public ones stay as they
            // are distributed rather than being listed for anyone who logs in.
            // User::usableDiscountCodes() is the single definition of usable,
            // shared with the account hub's count so the two cannot drift.
            'codes' => $request->user()->usableDiscountCodes(),
        ]);
    }
}
