<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
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

        $packageType = PackageType::query()->create($validated);
        AdminActivityLog::record('package_type.created', $packageType, 'Created package type '.$packageType->name);

        return redirect()
            ->route('admin.settings.shipping.edit')
            ->with('status', 'Package type added.');
    }

    public function destroy(PackageType $packageType): RedirectResponse
    {
        $name = $packageType->name;
        $packageType->delete();
        AdminActivityLog::record('package_type.deleted', null, 'Removed package type '.$name);

        return redirect()
            ->route('admin.settings.shipping.edit')
            ->with('status', 'Package type removed.');
    }
}
