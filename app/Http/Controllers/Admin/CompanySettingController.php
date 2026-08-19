<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCompanySettingRequest;
use App\Models\AdminActivityLog;
use App\Models\CompanySetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompanySettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings.company', [
            'setting' => CompanySetting::current(),
        ]);
    }

    public function update(UpdateCompanySettingRequest $request): RedirectResponse
    {
        CompanySetting::current()->update([
            ...$request->validated(),
            'vat_exempt' => $request->boolean('vat_exempt'),
        ]);
        AdminActivityLog::record('company_setting.updated', null, 'Updated company settings');

        return redirect()
            ->route('admin.settings.company.edit')
            ->with('status', 'Company information saved.');
    }
}
