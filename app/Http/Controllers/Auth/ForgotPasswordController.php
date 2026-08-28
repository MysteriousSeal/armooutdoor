<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        // Throttled on the address as typed, account or not: the broker only
        // cools down addresses that exist, and an error that fires solely for
        // real accounts would tell a prober exactly what it wants to know.
        $throttleKey = 'password-reset:'.Str::lower(trim((string) $request->input('email')));

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            return $this->throttledResponse($request, __('store.password_reset_throttled'));
        }

        RateLimiter::hit($throttleKey, 60);

        $status = Password::sendResetLink($request->only('email'));

        // An unknown address gets the same answer as a known one: the form
        // must not double as a directory of who has an account here.
        if ($status === Password::RESET_LINK_SENT || $status === Password::INVALID_USER) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('store.password_reset_sent')]);
            }

            return back()->with('status', __('store.password_reset_sent'));
        }

        // Asked twice inside the broker's own cooldown: nothing failed, the
        // first link is simply still on its way — say that, not "impossible".
        $message = $status === Password::RESET_THROTTLED
            ? __('store.password_reset_throttled')
            : __('store.password_reset_failed');

        return $this->throttledResponse($request, $message);
    }

    private function throttledResponse(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['errors' => ['email' => [$message]]], 422);
        }

        return back()->withErrors(['email' => $message]);
    }
}
