<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\ProductSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.products', [
            'setting' => ProductSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'low_stock_threshold' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        ProductSetting::current()->update($validated);
        AdminActivityLog::record('product_setting.updated', null, 'Updated product settings');

        return redirect()
            ->route('admin.settings.products.edit')
            ->with('status', 'Product settings saved.');
    }
}
