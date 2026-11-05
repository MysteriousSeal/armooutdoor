<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends Controller
{
    private const RULES = [
        'starts_on' => ['required', 'date'],
        'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
    ];

    public function index(): View
    {
        return view('admin.settings.holidays', [
            'holidays' => Holiday::query()->orderByDesc('starts_on')->get(),
            'today' => now('Europe/Paris')->toDateString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $holiday = Holiday::query()->create($request->validate(self::RULES));
        AdminActivityLog::record('holiday.created', $holiday, 'Added holiday '.$holiday->starts_on->toDateString().' to '.$holiday->ends_on->toDateString());

        return redirect()
            ->route('admin.settings.holidays.index')
            ->with('status', 'Holiday added.');
    }

    public function update(Request $request, Holiday $holiday): RedirectResponse
    {
        $holiday->update($request->validate(self::RULES));
        AdminActivityLog::record('holiday.updated', $holiday, 'Changed holiday to '.$holiday->starts_on->toDateString().' to '.$holiday->ends_on->toDateString());

        return redirect()
            ->route('admin.settings.holidays.index')
            ->with('status', 'Holiday saved.');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $period = $holiday->starts_on->toDateString().' to '.$holiday->ends_on->toDateString();
        $holiday->delete();
        AdminActivityLog::record('holiday.deleted', null, 'Removed holiday '.$period);

        return redirect()
            ->route('admin.settings.holidays.index')
            ->with('status', 'Holiday removed.');
    }
}
