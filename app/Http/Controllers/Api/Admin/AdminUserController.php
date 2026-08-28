<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateAdminUserRequest;
use App\Models\AdminActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function index(): JsonResponse
    {
        $admins = User::query()
            ->where('is_admin', true)
            ->orderBy('first_name')
            ->get()
            ->map(fn (User $admin) => $this->present($admin));

        return response()->json(['data' => $admins]);
    }

    public function update(UpdateAdminUserRequest $request, User $admin): JsonResponse
    {
        abort_unless($admin->isAdmin(), 404);

        $validated = $request->validated();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        if ($admin->role === 'owner' && ($validated['role'] ?? $admin->role) !== 'owner' && $this->ownerCount() <= 1) {
            return response()->json(['message' => "Can't demote the last remaining owner."], 422);
        }

        $roleChanged = array_key_exists('role', $validated) && $admin->role !== $validated['role'];
        $passwordChanged = array_key_exists('password', $validated);
        $oldRole = $admin->role;

        $admin->update($validated);

        AdminActivityLog::record('admin.updated', $admin, 'Updated admin '.$admin->name);

        if ($roleChanged) {
            AdminActivityLog::record('admin.role_changed', $admin, 'Changed '.$admin->name.'\'s role from '.$oldRole.' to '.$admin->role);
        }

        if ($passwordChanged) {
            AdminActivityLog::record('admin.password_reset', $admin, 'Reset password for '.$admin->name);
        }

        return response()->json(['data' => $this->present($admin)]);
    }

    /** @return array<string, mixed> */
    private function present(User $admin): array
    {
        return [
            'id' => $admin->id,
            'first_name' => $admin->first_name,
            'last_name' => $admin->last_name,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'is_admin' => $admin->is_admin,
            'admin_deactivated_at' => $admin->admin_deactivated_at?->toIso8601String(),
        ];
    }

    private function ownerCount(): int
    {
        return User::query()->where('is_admin', true)->where('role', 'owner')->count();
    }
}
