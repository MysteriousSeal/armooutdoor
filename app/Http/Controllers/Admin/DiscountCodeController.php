<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountCodeRequest;
use App\Models\DiscountCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DiscountCodeController extends Controller
{
    public function checkCode(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code')));

        return response()->json([
            'exists' => $code !== '' && DiscountCode::query()->where('code', $code)->exists(),
        ]);
    }

    public function create(): View
    {
        return view('admin.discount-codes.form', [
            'discountCode' => new DiscountCode(),
            'customers' => $this->customerOptions(),
        ]);
    }

    public function store(StoreDiscountCodeRequest $request): RedirectResponse
    {
        DiscountCode::query()->create($this->payload($request));

        return redirect()
            ->route('admin.discounts.index', ['tab' => 'codes'])
            ->with('status', 'Discount code created.');
    }

    public function edit(DiscountCode $discountCode): View
    {
        return view('admin.discount-codes.form', [
            'discountCode' => $discountCode,
            'customers' => $this->customerOptions(),
        ]);
    }

    public function update(StoreDiscountCodeRequest $request, DiscountCode $discountCode): RedirectResponse
    {
        $discountCode->update($this->payload($request));

        return redirect()
            ->route('admin.discounts.index', ['tab' => 'codes'])
            ->with('status', 'Discount code saved.');
    }

    public function destroy(DiscountCode $discountCode): RedirectResponse
    {
        $discountCode->delete();

        return redirect()
            ->route('admin.discounts.index', ['tab' => 'codes'])
            ->with('status', 'Discount code removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(StoreDiscountCodeRequest $request): array
    {
        $type = $request->string('type')->toString();
        $value = (float) $request->input('value');

        return [
            'code' => $request->string('code')->toString(),
            'type' => $type,
            'value' => $type === 'percentage' ? (int) round($value) : (int) round($value * 100),
            'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
            'quantity' => $request->filled('quantity') ? $request->integer('quantity') : null,
        ];
    }

    private function customerOptions()
    {
        return User::query()
            ->where('is_admin', false)
            ->where('external', false)
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }
}
