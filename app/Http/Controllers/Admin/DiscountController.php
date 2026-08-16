<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Models\Discount;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DiscountController extends Controller
{
    public function index(): View
    {
        $nameSql = Discount::query()->getConnection()->getDriverName() === 'sqlite'
            ? "json_extract(products.name, '$.fr')"
            : "json_unquote(json_extract(products.name, '$.fr'))";

        $discounts = Discount::query()
            ->with('product')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->orderByRaw($nameSql)
            ->select('discounts.*')
            ->get();

        return view('admin.discounts.index', [
            'discounts' => $discounts,
        ]);
    }

    public function create(): View
    {
        return view('admin.discounts.form', [
            'discount' => new Discount(),
            'products' => $this->availableProducts(),
        ]);
    }

    public function store(StoreDiscountRequest $request): RedirectResponse
    {
        Discount::query()->create($this->payload($request));

        return redirect()
            ->route('admin.discounts.index')
            ->with('status', 'Discount created.');
    }

    public function edit(Discount $discount): View
    {
        return view('admin.discounts.form', [
            'discount' => $discount,
            'products' => $this->availableProducts($discount),
        ]);
    }

    public function update(StoreDiscountRequest $request, Discount $discount): RedirectResponse
    {
        $discount->update($this->payload($request));

        return redirect()
            ->route('admin.discounts.index')
            ->with('status', 'Discount saved.');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        $discount->delete();

        return redirect()
            ->route('admin.discounts.index')
            ->with('status', 'Discount removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreDiscountRequest $request): array
    {
        $type = $request->string('type')->toString();
        $value = (float) $request->input('value');

        return [
            'product_id' => $request->integer('product_id'),
            'type' => $type,
            'value' => $type === 'percentage' ? (int) round($value) : (int) round($value * 100),
            'starts_at' => $request->filled('starts_at') ? $request->date('starts_at') : null,
            'ends_at' => $request->filled('ends_at') ? $request->date('ends_at') : null,
        ];
    }

    /**
     * Products without a discount yet, plus the one already assigned to
     * the discount being edited so its own product stays selectable.
     */
    private function availableProducts(?Discount $discount = null)
    {
        $nameSql = Product::query()->getConnection()->getDriverName() === 'sqlite'
            ? "json_extract(name, '$.fr')"
            : "json_unquote(json_extract(name, '$.fr'))";

        return Product::query()
            ->whereDoesntHave('discount')
            ->when($discount?->exists, fn ($query) => $query->orWhere('id', $discount->product_id))
            ->orderByRaw($nameSql)
            ->get();
    }
}
