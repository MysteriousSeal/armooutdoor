<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->select('id', 'category_id', 'name')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'slug' => $category->slug,
                'name' => $category->localizedName(),
                'parent_id' => $category->parent_id,
                'products' => $category->products->map(fn ($product) => [
                    'id' => $product->id,
                    'name' => $product->localizedName(),
                ]),
            ]);

        return response()->json(['data' => $categories]);
    }
}
