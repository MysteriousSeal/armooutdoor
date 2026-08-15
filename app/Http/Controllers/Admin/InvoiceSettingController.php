<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateInvoiceSettingRequest;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.invoice', [
            'setting' => CompanySetting::current(),
        ]);
    }

    public function update(UpdateInvoiceSettingRequest $request): RedirectResponse
    {
        CompanySetting::current()->update($request->validated());

        return redirect()
            ->route('admin.settings.invoice.edit')
            ->with('status', 'Invoice settings saved.');
    }
}
