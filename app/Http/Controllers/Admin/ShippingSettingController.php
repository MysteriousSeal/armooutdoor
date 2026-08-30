<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShippingSettingRequest;
use App\Models\AdminActivityLog;
use App\Models\Carrier;
use App\Models\Order;
use App\Models\PackageType;
use App\Models\ShippingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShippingSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.shipping', [
            'setting' => ShippingSetting::current(),
            'carriers' => Carrier::query()->orderBy('sort_order')->with('priceTiers')->get(),
            'packageTypes' => PackageType::query()->orderBy('name')->get(),
            // Combien de commandes ont porté chaque type : un seul groupBy
            // pour toute la page, et l'admin sait si retirer un type efface
            // une habitude ou trois essais.
            'packageTypeUsage' => Order::query()
                ->whereNotNull('package_type_id')
                ->selectRaw('package_type_id, count(*) as aggregate')
                ->groupBy('package_type_id')
                ->pluck('aggregate', 'package_type_id'),
        ]);
    }

    public function update(UpdateShippingSettingRequest $request): RedirectResponse
    {
        $setting = ShippingSetting::current();

        $threshold = $request->input('free_shipping_threshold');

        $setting->update([
            'free_shipping_threshold_cents' => $threshold === null || $threshold === ''
                ? null
                : (int) round(((float) $threshold) * 100),
            'free_shipping_carrier_ids' => array_map('intval', $request->input('free_shipping_carrier_ids', [])),
        ]);
        AdminActivityLog::record('shipping_setting.updated', null, 'Updated shipping settings');

        return redirect()
            ->route('admin.settings.shipping.edit')
            ->with('status', 'Shipping settings saved.');
    }
}
