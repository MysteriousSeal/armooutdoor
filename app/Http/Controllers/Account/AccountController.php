<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();

        return view('account.index', [
            'user' => $user,
            'addressCount' => $user->addresses()->count(),
            'wishlistCount' => $user->wishlistItems()->count(),
            'orderCount' => $user->orders()->whereNull('archived_at')->count(),
            'conversationCount' => $user->conversations()->count(),
            // Same rule as the discounts page, from the same method.
            'discountCount' => $user->usableDiscountCodes()->count(),
        ]);
    }
}
