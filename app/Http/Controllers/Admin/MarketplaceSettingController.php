<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\MarketplaceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketplaceSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.naturabuy', [
            'setting' => MarketplaceSetting::current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'naturabuy_url' => ['nullable', 'url', 'max:255'],
            // Entered as 4,9 out of 5 and kept in tenths.
            'naturabuy_rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'naturabuy_reviews' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'naturabuy_sales' => ['nullable', 'integer', 'min:0', 'max:9999999'],
        ]);

        MarketplaceSetting::current()->update([
            'naturabuy_url' => $validated['naturabuy_url'] ?? null,
            'naturabuy_rating_tenths' => isset($validated['naturabuy_rating'])
                ? (int) round((float) $validated['naturabuy_rating'] * 10)
                : null,
            'naturabuy_reviews' => $validated['naturabuy_reviews'] ?? null,
            'naturabuy_sales' => $validated['naturabuy_sales'] ?? null,
            'naturabuy_on_home' => $request->boolean('naturabuy_on_home'),
        ]);

        AdminActivityLog::record('marketplace_setting.updated', null, 'Updated marketplace settings');

        return redirect()
            ->route('admin.settings.naturabuy.edit')
            ->with('status', 'Marketplace settings saved.');
    }
}
