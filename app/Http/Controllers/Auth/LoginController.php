<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        $this->rememberIntendedUrl($request);

        return view('auth.login');
    }

    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('store.auth_failed'),
            ]);
        }

        // Told apart from a wrong password on purpose: a banned customer
        // shouldn't burn time resetting a password that was never the problem.
        if ($request->user()->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => __('store.account_banned'),
            ]);
        }

        $request->session()->regenerate();
        $cart->claimFor($request->user());

        return redirect()
            ->intended(localized_route('home'))
            ->with('status', __('store.logged_in'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(localized_route('home'))
            ->with('status', __('store.logged_out'));
    }

    private function rememberIntendedUrl(Request $request): void
    {
        if ($request->session()->has('url.intended')) {
            return;
        }

        $previous = url()->previous();
        $login = localized_route('login');
        $register = localized_route('register');

        if ($previous !== $login && $previous !== $register) {
            $request->session()->put('url.intended', $previous);
        }
    }
}
