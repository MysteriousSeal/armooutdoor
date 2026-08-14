<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:marketplaces,name'],
        ]);

        Marketplace::query()->create($validated);

        return redirect()
            ->route('admin.settings.orders.edit')
            ->with('status', 'Marketplace added.');
    }

    public function destroy(Marketplace $marketplace): RedirectResponse
    {
        $marketplace->delete();

        return redirect()
            ->route('admin.settings.orders.edit')
            ->with('status', 'Marketplace removed.');
    }
}
