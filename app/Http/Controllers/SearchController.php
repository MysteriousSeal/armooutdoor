<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request): View
    {
        $query = trim((string) $request->query('q', ''));

        $products = $query === ''
            ? collect()
            : Product::query()
                ->active()
                ->with('category')
                ->where(function ($q) use ($query): void {
                    $q->where('name', 'like', '%'.$query.'%')
                        ->orWhere('sku', 'like', '%'.$query.'%');
                })
                ->orderBy('sort_order')
                ->get();

        return view('search.index', [
            'query' => $query,
            'products' => $products,
        ]);
    }
}
