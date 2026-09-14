<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\StoreDiscountRequest;
use App\Http\Requests\Api\Admin\UpdateDiscountRequest;
use App\Models\AdminActivityLog;
use App\Models\Discount;
use App\Models\Product;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product discounts (a sale price on one product, no code needed), the same
 * ones the web admin lists under /admin/discounts?tab=products.
 */
class DiscountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');

        if ($status !== null && ! in_array($status, ['active', 'scheduled', 'expired'], true)) {
            return response()->json([
                'message' => 'The status must be active, scheduled or expired.',
                'errors' => ['status' => ['The status must be active, scheduled or expired.']],
            ], 422);
        }

        // Status depends on the current time, so it is filtered in PHP like
        // the web admin does, not in SQL.
        $discounts = Discount::query()
            ->with('product')
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->orderBy('id')
            ->get()
            ->when($status !== null, fn ($collection) => $collection->filter(
                fn (Discount $discount): bool => $discount->status() === $status
            ))
            ->values()
            ->map(fn (Discount $discount): array => $this->serialize($discount));

        return response()->json(['data' => $discounts]);
    }

    /**
     * Every product with what pricing a discount needs: available stock and
     * the average paid incl. VAT, the figure the product edit page shows
     * as "Average paid, incl. VAT, from N units received".
     */
    public function products(): JsonResponse
    {
        $products = Product::query()->with('discount')->orderBy('id')->get();
        $ids = $products->pluck('id');

        $averageCosts = Product::averagePurchaseCostsInclVatCents($ids);
        $receivedUnits = PurchaseOrderItem::query()
            ->whereIn('product_id', $ids)
            ->where('quantity_received', '>', 0)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity_received) as units')
            ->pluck('units', 'product_id');

        $data = $products->map(fn (Product $product): array => [
            'id' => $product->id,
            'name' => $product->localizedName(),
            'sku' => $product->sku,
            'is_active' => (bool) $product->is_active,
            'price_cents' => $product->price_cents,
            // Mirrors the sum of variant stock on products with variants.
            'quantity' => $product->quantity,
            'average_paid_incl_vat_cents' => $averageCosts[$product->id] ?? null,
            'received_units' => (int) ($receivedUnits[$product->id] ?? 0),
            'discount_id' => $product->discount?->id,
        ]);

        return response()->json(['data' => $data]);
    }

    public function store(StoreDiscountRequest $request): JsonResponse
    {
        $discount = Discount::query()->create($this->payload($request, null));
        AdminActivityLog::record('discount.created', $discount, 'Created discount for '.$discount->product->localizedName().' (API)');

        return response()->json(['data' => $this->serialize($discount->refresh())], 201);
    }

    public function show(Discount $discount): JsonResponse
    {
        return response()->json(['data' => $this->serialize($discount)]);
    }

    public function update(UpdateDiscountRequest $request, Discount $discount): JsonResponse
    {
        $discount->update($this->payload($request, $discount));
        AdminActivityLog::record('discount.updated', $discount, 'Updated discount for '.$discount->product->localizedName().' (API)');

        return response()->json(['data' => $this->serialize($discount->refresh())]);
    }

    /** @return array<string, mixed> */
    private function serialize(Discount $discount): array
    {
        $product = $discount->product;

        return [
            'id' => $discount->id,
            'product_id' => $discount->product_id,
            'type' => $discount->type,
            // Same unit as written: a percentage, or euros for `fixed`.
            'value' => $discount->type === 'percentage' ? $discount->value : $discount->value / 100,
            'label' => $discount->label(),
            'status' => $discount->status(),
            'starts_at' => $discount->starts_at?->toIso8601String(),
            'ends_at' => $discount->ends_at?->toIso8601String(),
            'product' => [
                'id' => $product->id,
                'name' => $product->localizedName(),
                'sku' => $product->sku,
                'price_cents' => $product->price_cents,
                'discounted_price_cents' => $product->price_cents === null ? null : $discount->apply($product->price_cents),
            ],
            'created_at' => $discount->created_at?->toIso8601String(),
            'updated_at' => $discount->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Only the fields sent are written; `value` is converted to the stored
     * unit (whole percent, or cents) using the type it will be saved with.
     *
     * @return array<string, mixed>
     */
    private function payload(StoreDiscountRequest|UpdateDiscountRequest $request, ?Discount $discount): array
    {
        $validated = $request->validated();
        $payload = [];

        if (array_key_exists('product_id', $validated)) {
            $payload['product_id'] = (int) $validated['product_id'];
        }

        if (array_key_exists('type', $validated)) {
            $payload['type'] = $validated['type'];
        }

        if (array_key_exists('value', $validated)) {
            $type = $validated['type'] ?? $discount?->type;
            $value = (float) $validated['value'];
            $payload['value'] = $type === 'percentage' ? (int) round($value) : (int) round($value * 100);
        }

        foreach (['starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $validated)) {
                $payload[$field] = filled($validated[$field]) ? $request->date($field) : null;
            }
        }

        return $payload;
    }
}
