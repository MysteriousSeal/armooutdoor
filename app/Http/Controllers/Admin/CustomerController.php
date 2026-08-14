<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $customers = User::query()
            ->where('is_admin', false)
            ->where('external', false)
            ->withCount(['orders', 'addresses'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->simplePaginate(20)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'customerCount' => User::query()->where('is_admin', false)->where('external', false)->count(),
            'search' => $search,
        ]);
    }
}
