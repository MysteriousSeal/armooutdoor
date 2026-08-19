<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'deactivated' ? 'deactivated' : 'active';

        return view('admin.settings.admins', [
            'tab' => $tab,
            'admins' => $tab === 'active'
                ? User::query()->where('is_admin', true)->orderBy('first_name')->get()
                : User::query()->whereNotNull('admin_deactivated_at')->orderByDesc('admin_deactivated_at')->get(),
            'activeCount' => User::query()->where('is_admin', true)->count(),
            'deactivatedCount' => User::query()->whereNotNull('admin_deactivated_at')->count(),
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.admin-form', ['admin' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $admin = User::query()->create($validated);
        $admin->is_admin = true;
        $admin->save();

        return redirect()
            ->route('admin.settings.admins.index')
            ->with('status', 'Admin added.');
    }

    public function edit(User $admin): View
    {
        abort_unless($admin->isAdmin(), 404);

        return view('admin.settings.admin-form', ['admin' => $admin]);
    }

    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isAdmin(), 404);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $admin->update($validated);

        return redirect()
            ->route('admin.settings.admins.index')
            ->with('status', 'Admin updated.');
    }

    public function deactivate(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->isAdmin(), 404);

        if ($admin->is($request->user())) {
            return back()->with('status', 'You can\'t deactivate your own account.');
        }

        if (User::query()->where('is_admin', true)->count() <= 1) {
            return back()->with('status', 'Can\'t deactivate the last remaining admin.');
        }

        $admin->is_admin = false;
        $admin->admin_deactivated_at = now();
        $admin->save();

        return redirect()
            ->route('admin.settings.admins.index')
            ->with('status', 'Admin deactivated.');
    }

    public function reactivate(User $admin): RedirectResponse
    {
        abort_if($admin->is_admin, 404);
        abort_if($admin->admin_deactivated_at === null, 404);

        $admin->is_admin = true;
        $admin->admin_deactivated_at = null;
        $admin->save();

        return redirect()
            ->route('admin.settings.admins.index')
            ->with('status', 'Admin reactivated.');
    }
}
