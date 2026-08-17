<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));

        $orders = collect();
        $customers = collect();
        $products = collect();

        if ($term !== '') {
            $orders = Order::query()
                ->with('user')
                ->withCount('items')
                ->whereNull('archived_at')
                ->where(function ($query) use ($term): void {
                    $query->where('number', 'like', '%'.$term.'%')
                        ->orWhereHas('user', function ($query) use ($term): void {
                            $query->where('first_name', 'like', '%'.$term.'%')
                                ->orWhere('last_name', 'like', '%'.$term.'%')
                                ->orWhere('email', 'like', '%'.$term.'%');
                        });
                })
                ->latest()
                ->limit(10)
                ->get();

            $customers = User::query()
                ->where('is_admin', false)
                ->withCount(['orders' => fn ($query) => $query->whereNull('archived_at')->where('status', '!=', 'draft')])
                ->where(function ($query) use ($term): void {
                    $query->where('first_name', 'like', '%'.$term.'%')
                        ->orWhere('last_name', 'like', '%'.$term.'%')
                        ->orWhere('email', 'like', '%'.$term.'%');
                })
                ->latest()
                ->limit(10)
                ->get();

            $products = Product::query()
                ->with('category')
                ->where(function ($query) use ($term): void {
                    $query->where('name', 'like', '%'.$term.'%')
                        ->orWhere('sku', 'like', '%'.$term.'%');
                })
                ->limit(10)
                ->get();
        }

        return view('admin.search', [
            'term' => $term,
            'orders' => $orders,
            'customers' => $customers,
            'products' => $products,
        ]);
    }
}
