<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Cart;
use App\Support\CustomerWelcome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Continuer avec Google": signs a customer in, or opens their account on
 * the way, from the Google account they already use.
 *
 * Only Google's own account id is trusted to say who comes back. An address
 * is matched to an existing account only when Google is the authority for
 * it (a Gmail address, or a Workspace domain); any other address Google
 * calls verified was checked once, at signup, and may have changed hands
 * since. Admin accounts never sign in this way: they keep their password.
 */
class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function redirect(): SymfonyRedirect
    {
        abort_unless(self::enabled(), 404);

        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request, Cart $cart): RedirectResponse
    {
        abort_unless(self::enabled(), 404);

        // The customer closed Google's screen or said no: not a failure.
        if ($request->filled('error')) {
            return $this->refuse(__('store.google_cancelled'));
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            // The round trip outlived the session, or was replayed.
            return $this->refuse(__('store.google_failed'));
        } catch (Throwable $e) {
            report($e);

            return $this->refuse(__('store.google_failed'));
        }

        $email = Str::lower(trim((string) $google->getEmail()));

        if ($email === '' || ($google->getRaw()['email_verified'] ?? false) !== true) {
            return $this->refuse(__('store.google_unverified'));
        }

        $user = User::query()->where('google_id', $google->getId())->first();
        $created = false;

        if ($user === null) {
            $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

            if ($user !== null && ! $this->googleOwns($email, $google)) {
                return $this->refuse(__('store.google_use_password'));
            }

            if ($user === null) {
                $user = $this->open($google, $email);
                $created = true;
            }
        }

        if ($user->isAdmin()) {
            return $this->refuse(__('store.google_admin'));
        }

        if ($user->isBanned()) {
            return $this->refuse(__('store.account_banned'));
        }

        if (! $created) {
            $this->link($user, $google);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();
        $cart->claimFor($user);

        if ($created) {
            CustomerWelcome::send($user);

            return redirect()
                ->intended(localized_route('home'))
                ->with('status', __('store.registered'));
        }

        return redirect()
            ->intended(localized_route('home'))
            ->with('status', __('store.logged_in'));
    }

    /**
     * Whether Google, and nobody else, answers for this address today.
     */
    private function googleOwns(string $email, GoogleUser $google): bool
    {
        return Str::endsWith($email, ['@gmail.com', '@googlemail.com'])
            || filled($google->getRaw()['hd'] ?? null);
    }

    private function open(GoogleUser $google, string $email): User
    {
        $raw = $google->getRaw();

        // Never typed, never shown: the column wants a value. The customer
        // can pick a real one later through "Mot de passe oublié".
        $user = User::query()->create([
            'first_name' => Str::limit(trim((string) ($raw['given_name'] ?? $google->getName())), 80, ''),
            'last_name' => Str::limit(trim((string) ($raw['family_name'] ?? '')), 80, ''),
            'email' => $email,
            'password' => Str::password(64),
        ]);

        $user->forceFill([
            'google_id' => $google->getId(),
            'email_verified_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * Ties Google to an account found by its address. A customer who only
     * existed through a manual order becomes a customer of the shop proper.
     */
    private function link(User $user, GoogleUser $google): void
    {
        $user->forceFill([
            'google_id' => $google->getId(),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'external' => false,
        ])->save();
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect(localized_route('login'))->withErrors(['google' => $message]);
    }
}
