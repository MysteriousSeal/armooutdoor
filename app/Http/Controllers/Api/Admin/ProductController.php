<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
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

        foreach (['category_id', 'quantity', 'is_active', 'sku', 'gtin', 'characteristics'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = $validated[$field];
            }
        }

        if (array_key_exists('price', $validated)) {
            $payload['price_cents'] = (int) round($validated['price'] * 100);
        }

        $product->update($payload);

        return response()->json(['data' => $product->fresh()]);
    }
}
