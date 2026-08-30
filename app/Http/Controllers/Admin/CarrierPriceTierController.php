<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCarrierPriceTierRequest;
use App\Models\AdminActivityLog;
use App\Models\Carrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class CarrierPriceTierController extends Controller
{
    public function update(UpdateCarrierPriceTierRequest $request, Carrier $carrier): RedirectResponse
    {
        DB::transaction(function () use ($request, $carrier): void {
            $maxWeight = $request->validated('max_weight');

            $carrier->update([
                'price_cents' => (int) round(((float) $request->validated('default_price')) * 100),
                'max_weight_grams' => $maxWeight === null || $maxWeight === '' ? null : (int) $maxWeight,
            ]);

            $carrier->priceTiers()->delete();

            foreach ($request->validated('tiers', []) as $tier) {
                $carrier->priceTiers()->create([
                    'min_weight_grams' => (int) $tier['min_weight'],
                    'price_cents' => (int) round(((float) $tier['price']) * 100),
                ]);
            }
        });

        AdminActivityLog::record('carrier.price_tiers_updated', $carrier, 'Updated price tiers for '.$carrier->localizedName());

        return redirect()
            ->route('admin.settings.shipping.edit')
            ->with('status', 'Price tiers saved for '.$carrier->localizedName().'.');
    }
}
