<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Marketplace;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index');
    }

    public function orders(): View
    {
        return view('admin.settings.orders', [
            'marketplaces' => Marketplace::query()->orderBy('name')->get(),
        ]);
    }
}
