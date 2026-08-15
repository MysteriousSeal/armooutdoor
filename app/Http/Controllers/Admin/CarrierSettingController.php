<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCarrierSettingRequest;
use App\Models\Carrier;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CarrierSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.carriers', [
            'carriers' => Carrier::query()->orderBy('sort_order')->get(),
        ]);
    }

    public function update(UpdateCarrierSettingRequest $request): RedirectResponse
    {
        foreach ($request->validated('carriers') as $carrierId => $row) {
            Carrier::query()->whereKey($carrierId)->update([
                'price_cents' => (int) round(((float) $row['price']) * 100),
            ]);
        }

        return redirect()
            ->route('admin.settings.carriers.edit')
            ->with('status', 'Carrier prices saved.');
    }
}
