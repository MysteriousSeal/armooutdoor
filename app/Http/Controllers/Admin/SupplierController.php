<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.suppliers', [
            'suppliers' => Supplier::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.supplier-form', [
            'supplier' => new Supplier,
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('admin.settings.supplier-form', [
            'supplier' => $supplier,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:suppliers,name'],
            'website' => ['nullable', 'url', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $supplier = Supplier::query()->create($validated);
        AdminActivityLog::record('supplier.created', $supplier, 'Created supplier '.$supplier->name);

        return redirect()
            ->route('admin.settings.suppliers.index')
            ->with('status', 'Supplier added.');
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80', 'unique:suppliers,name,'.$supplier->id],
            'website' => ['nullable', 'url', 'max:255'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:365'],
        ]);

        $supplier->update($validated);
        AdminActivityLog::record('supplier.updated', $supplier, 'Updated supplier '.$supplier->name);

        return redirect()
            ->route('admin.settings.suppliers.index')
            ->with('status', 'Supplier updated.');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $name = $supplier->name;
        $supplier->delete();
        AdminActivityLog::record('supplier.deleted', null, 'Removed supplier '.$name);

        return redirect()
            ->route('admin.settings.suppliers.index')
            ->with('status', 'Supplier removed.');
    }
}
