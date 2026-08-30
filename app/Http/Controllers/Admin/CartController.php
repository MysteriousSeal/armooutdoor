<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CartLine;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    /**
     * Carts persist in the database only for logged-in users — a guest's
     * cart lives in their own session and never reaches the server's
     * database, so it can't be listed here. A cart also empties itself the
     * moment its order completes, so every row shown is still in progress.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('search', ''));

        $usersQuery = User::query()
            ->whereHas('cartItems')
            ->with(['cartItems.product.discount', 'cartItems.variant'])
            ->withMax('cartItems as cart_updated_at', 'updated_at');

        if ($search !== '') {
            $usersQuery->where(fn ($query) => $query
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        $carts = $usersQuery->orderByDesc('cart_updated_at')->paginate(20)->withQueryString();

        $carts->setCollection($carts->getCollection()->map(function (User $user) {
            $lines = $user->cartItems->map(
                fn ($item) => new CartLine($item->product, $item->quantity, $item->variant)
            );

            return [
                'user' => $user,
                'lines' => $lines,
                'itemCount' => $lines->sum('quantity'),
                'totalCents' => $lines->sum(fn (CartLine $line) => $line->lineCents()),
                'updatedAt' => $user->cart_updated_at,
            ];
        }));

        return view('admin.carts.index', [
            'carts' => $carts,
            'search' => $search,
            'cartCount' => User::query()->whereHas('cartItems')->count(),
        ]);
    }
}
