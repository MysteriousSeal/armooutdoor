<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:package_types,name'],
        ]);

        PackageType::query()->create($validated);

        return redirect()
            ->route('admin.settings.shipping.edit')
            ->with('status', 'Package type added.');
    }

    public function destroy(PackageType $packageType): RedirectResponse
    {
        $packageType->delete();

        return redirect()
            ->route('admin.settings.shipping.edit')
            ->with('status', 'Package type removed.');
    }
}
