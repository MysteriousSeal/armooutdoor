<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDiscountCodeRequest;
use App\Models\AdminActivityLog;
use App\Models\DiscountCode;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DiscountCodeController extends Controller
{
    /**
     * Une carte de 70 × 50 mm ne portant que le code, à glisser dans un
     * colis. Rien d'autre dessus, à la demande : pas d'enseigne, pas de
     * montant — le code est le message.
     */
    public function label(DiscountCode $discountCode): Response
    {
        $pdf = Pdf::loadView('admin.discounts.code-pdf', [
            'code' => $discountCode->code,
            'amount' => $discountCode->customerLabel(),
            'endsAt' => $discountCode->ends_at,
        ])
            // 70 × 50 mm en points PDF (1 mm = 72/25.4 pt), paysage par
            // construction : la largeur est le grand côté.
            ->setPaper([0, 0, 70 * 72 / 25.4, 50 * 72 / 25.4]);

        return $pdf->download('code-'.Str::slug($discountCode->code).'.pdf');
    }

    public function checkCode(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->query('code')));

        return response()->json([
            'exists' => $code !== '' && DiscountCode::query()->where('code', $code)->exists(),
        ]);
    }

    public function create(Request $request): View
    {
        $discountCode = new DiscountCode;

        if ($request->filled('user_id')) {
            $discountCode->user_id = $request->integer('user_id');
        }

        return view('admin.discount-codes.form', [
            'discountCode' => $discountCode,
            'customers' => $this->customerOptions(),
        ]);
    }

    public function store(StoreDiscountCodeRequest $request): RedirectResponse
    {
        $discountCode = DiscountCode::query()->create($this->payload($request));
        AdminActivityLog::record('discount_code.created', $discountCode, 'Created discount code '.$discountCode->code);

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
        AdminActivityLog::record('discount_code.updated', $discountCode, 'Updated discount code '.$discountCode->code);

        return redirect()
            ->route('admin.discounts.index', ['tab' => 'codes'])
            ->with('status', 'Discount code saved.');
    }

    public function destroy(DiscountCode $discountCode): RedirectResponse
    {
        $code = $discountCode->code;
        $discountCode->delete();
        AdminActivityLog::record('discount_code.deleted', null, 'Removed discount code '.$code);

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
            'value' => match ($type) {
                DiscountCode::TYPE_FREE_RELAY_SHIPPING => null,
                DiscountCode::TYPE_PERCENTAGE => (int) round($value),
                default => (int) round($value * 100),
            },
            'user_id' => $request->filled('user_id') ? $request->integer('user_id') : null,
            'quantity' => $request->filled('quantity') ? $request->integer('quantity') : null,
            'max_uses_per_customer' => $request->filled('max_uses_per_customer') ? $request->integer('max_uses_per_customer') : null,
            'ends_at' => $request->filled('ends_at') ? $request->date('ends_at') : null,
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
