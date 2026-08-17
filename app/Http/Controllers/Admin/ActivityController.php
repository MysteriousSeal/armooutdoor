<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function index(): View
    {
        $logs = AdminActivityLog::query()
            ->with(['user', 'subject'])
            ->latest()
            ->simplePaginate(30);

        return view('admin.activity', [
            'logs' => $logs,
        ]);
    }
}
