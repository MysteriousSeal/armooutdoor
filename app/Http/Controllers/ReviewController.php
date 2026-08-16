<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    public function store(StoreReviewRequest $request, Product $product): RedirectResponse
    {
        $order = $product->eligibleOrderFor($request->user());

        ProductReview::query()->create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'order_id' => $order->id,
            'rating' => $request->integer('rating'),
            'comment' => $request->input('comment'),
        ]);

        return back()->with('status', __('store.review_submitted'));
    }
}
