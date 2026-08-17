<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountRequest;
use App\Models\AdminActivityLog;
use App\Models\Discount;
use App\Models\DiscountCode;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'codes' ? 'codes' : 'products';
        $status = in_array($request->query('status'), ['active', 'scheduled', 'expired'], true)
            ? $request->query('status')
            : 'active';

        $nameSql = Discount::query()->getConnection()->getDriverName() === 'sqlite'
            ? "json_extract(products.name, '$.fr')"
            : "json_unquote(json_extract(products.name, '$.fr'))";

        $allDiscounts = Discount::query()
            ->with('product')
            ->join('products', 'products.id', '=', 'discounts.product_id')
            ->orderByRaw($nameSql)
            ->select('discounts.*')
            ->get();

        $activeCount = $allDiscounts->filter(fn (Discount $discount): bool => $discount->status() === 'active')->count();
        $scheduledCount = $allDiscounts->filter(fn (Discount $discount): bool => $discount->status() === 'scheduled')->count();
        $expiredCount = $allDiscounts->filter(fn (Discount $discount): bool => $discount->status() === 'expired')->count();

        $codeStatus = in_array($request->query('code_status'), ['active', 'expired', 'sold_out'], true)
            ? $request->query('code_status')
            : 'active';

        $allDiscountCodes = DiscountCode::query()
            ->with('user')
            ->orderByDesc('id')
            ->get();

        $activeCodesCount = $allDiscountCodes->filter(fn (DiscountCode $code): bool => $code->status() === 'active')->count();
        $expiredCodesCount = $allDiscountCodes->filter(fn (DiscountCode $code): bool => $code->status() === 'expired')->count();
        $soldOutCodesCount = $allDiscountCodes->filter(fn (DiscountCode $code): bool => $code->status() === 'sold_out')->count();

        return view('admin.discounts.index', [
            'discounts' => $allDiscounts
                ->filter(fn (Discount $discount): bool => $discount->status() === $status)
                ->values(),
            'discountCount' => $allDiscounts->count(),
            'discountCodes' => $allDiscountCodes
                ->filter(fn (DiscountCode $code): bool => $code->status() === $codeStatus)
                ->values(),
            'discountCodeCount' => $allDiscountCodes->count(),
            'tab' => $tab,
            'status' => $status,
            'codeStatus' => $codeStatus,
            'activeCount' => $activeCount,
            'scheduledCount' => $scheduledCount,
            'expiredCount' => $expiredCount,
            'activeCodesCount' => $activeCodesCount,
            'expiredCodesCount' => $expiredCodesCount,
            'soldOutCodesCount' => $soldOutCodesCount,
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
        $discount = Discount::query()->create($this->payload($request));
        AdminActivityLog::record('discount.created', $discount, 'Created discount for '.$discount->product->localizedName());

        return redirect()
            ->route('admin.discounts.index', ['tab' => 'products', 'status' => $discount->status()])
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
        AdminActivityLog::record('discount.updated', $discount, 'Updated discount for '.$discount->product->localizedName());

        return redirect()
            ->route('admin.discounts.index', ['tab' => 'products', 'status' => $discount->status()])
            ->with('status', 'Discount saved.');
    }

    public function destroy(Discount $discount): RedirectResponse
    {
        $productName = $discount->product->localizedName();
        $discount->delete();
        AdminActivityLog::record('discount.deleted', null, 'Removed discount for '.$productName);

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
