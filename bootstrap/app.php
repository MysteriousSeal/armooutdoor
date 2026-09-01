<?php

use App\Http\Middleware\EnsureAdminApiToken;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\EnsureUserIsOwner;
use App\Http\Middleware\RecordSiteVisit;
use App\Http\Middleware\RedirectWwwToApex;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // A page answered under www is a duplicate of the same page under the
        // apex domain, so the request is turned around before the session,
        // the CSRF check or the database ever see it. Appended rather than
        // prepended so that TrustProxies has already settled the scheme:
        // read too early, an HTTPS request behind a proxy would be sent back
        // to its own http:// address.
        $middleware->append(RedirectWwwToApex::class);

        $middleware->web(append: [
            EnsureUserIsNotBanned::class,
            SecurityHeaders::class,
            RecordSiteVisit::class,
        ]);

        // Posé par le bandeau cookies côté client et lu côté serveur pour
        // décider si la mesure d'audience peut porter l'identifiant de
        // session — il doit donc voyager en clair.
        $middleware->encryptCookies(except: ['cookie_consent']);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'admin.owner' => EnsureUserIsOwner::class,
            'admin.api' => EnsureAdminApiToken::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            $adminPath = config('shop.admin_path');

            if ($request->is($adminPath) || $request->is($adminPath.'/*')) {
                return route('admin.login');
            }

            return route('login');
        });

        $middleware->redirectUsersTo(fn () => route('home'));

        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A response rendered from an exception — a guest's login redirect,
        // a 404 — bypasses the middleware stack: it gets its security
        // headers (and the noindex on private paths) here instead.
        $exceptions->respond(
            fn (Response $response, Throwable $e, Request $request) => SecurityHeaders::apply($request, $response),
        );
    })->create();
