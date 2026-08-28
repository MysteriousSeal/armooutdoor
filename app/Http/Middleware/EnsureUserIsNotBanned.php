<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shows a banned account the door on its very next request.
 *
 * The login form already refuses them, but a ban lands while sessions and
 * remember-me cookies are still out there; this closes those too, without
 * having to hunt them down at the moment of the ban.
 */
class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBanned()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => __('store.account_banned'),
            ]);
        }

        return $next($request);
    }
}
