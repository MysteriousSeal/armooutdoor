<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
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
            // Same rule as the discounts page: only what they can use now.
            'discountCount' => $user->discountCodes()->get()
                ->filter(fn (DiscountCode $code): bool => $code->eligibilityError($user) === null)
                ->count(),
        ]);
    }
}
