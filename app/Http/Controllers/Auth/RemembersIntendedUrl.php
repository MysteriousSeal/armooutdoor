<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;

/**
 * Keeps the page a visitor came from, so signing in or signing up sends them
 * back there (a checkout in progress, most of all) instead of to the homepage.
 */
trait RemembersIntendedUrl
{
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
