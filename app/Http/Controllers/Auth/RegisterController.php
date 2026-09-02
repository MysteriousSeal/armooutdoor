<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminCustomerRegistered;
use App\Notifications\Welcome;
use App\Support\AdminMail;
use App\Support\Cart;
use App\Support\CustomerMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            // The form's own script warns first; without JavaScript the
            // refusal happens here, saying the same thing.
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Vous devez accepter les conditions pour créer un compte.',
        ]);

        unset($validated['terms']);

        $user = User::query()->create($validated);

        event(new Registered($user));
        Auth::login($user);
        $cart->claimFor($user);

        AdminMail::notify(
            new AdminCustomerRegistered($user),
            'Could not email the new-customer notice.',
            ['user_id' => $user->id],
        );

        CustomerMail::notify($user, new Welcome($user),
            'Could not email the welcome.', ['user_id' => $user->id]);

        return redirect(localized_route('home'))
            ->with('status', __('store.registered'));
    }
}
