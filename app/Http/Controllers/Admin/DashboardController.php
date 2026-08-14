<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'customerCount' => User::query()->where('is_admin', false)->where('external', false)->count(),
            'productCount' => Product::query()->count(),
            'orderCount' => Order::query()->count(),
        ]);
    }
}
