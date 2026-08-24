<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\StockMovementReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use App\Support\StockContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with('category', 'images', 'variants')
            ->orderBy('id')
            ->paginate(50);

        return response()->json($products);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->load('category', 'images', 'variants')]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = ['fr' => $validated['name']];
        }

        if (array_key_exists('description', $validated)) {
            $payload['description'] = ['fr' => HtmlSanitizer::clean($validated['description']) ?? ''];
        }

        foreach (['category_id', 'quantity', 'is_active', 'age_restricted', 'sku', 'gtin', 'characteristics', 'filter_attributes'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('price', $validated)) {
            $payload['price_cents'] = (int) round($validated['price'] * 100);
        }

        StockContext::during(
            StockMovementReason::ApiUpdate,
            fn () => $product->update($payload),
        );

        return response()->json(['data' => $product->fresh()]);
    }
}
