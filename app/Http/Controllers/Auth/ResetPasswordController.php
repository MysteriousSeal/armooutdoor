<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function create(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ]);

        // The token alone decides whose password changes. Sending the email
        // along with it would hand out half of someone else's reset for free
        // — and show the address to whoever found the link on a screen.
        $email = $this->emailForToken($validated['token']);

        if ($email === null) {
            return back()->withErrors(['token' => __('store.password_reset_invalid_token')]);
        }

        $status = Password::reset([
            'token' => $validated['token'],
            'email' => $email,
            'password' => $validated['password'],
        ], function (User $user, string $password): void {
            $user->update(['password' => $password]);
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect(localized_route('login'))
                ->with('status', __('store.password_reset_success'));
        }

        return back()->withErrors(['token' => __('store.password_reset_invalid_token')]);
    }

    /**
     * Which account a reset token belongs to.
     *
     * The broker's table keys tokens by email and stores them hashed, so the
     * lookup walks the live rows checking the hash. The table only ever holds
     * one row per address with an open reset, so the walk is short.
     */
    private function emailForToken(string $token): ?string
    {
        $rows = DB::table(config('auth.passwords.users.table', 'password_reset_tokens'))
            ->where('created_at', '>', now()->subMinutes((int) config('auth.passwords.users.expire', 60)))
            ->get();

        foreach ($rows as $row) {
            if (Hash::check($token, $row->token)) {
                return $row->email;
            }
        }

        return null;
    }
}
