<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * script-src and style-src allow 'unsafe-inline': the app ships inline
     * <script>/<style> blocks and a couple of inline event handlers. Nothing
     * is fetched from anywhere else, so the rest stays 'self'.
     */
    private const CSP = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'self'";

    /**
     * Paths whose pages are private — gated behind a login, personal to a
     * visitor, or reachable only through a secret token. /login and
     * /register themselves stay indexable, being public doors.
     */
    private const PRIVATE_PATHS = [
        'account', 'account/*',
        'orders', 'orders/*',
        'checkout', 'checkout/*',
        'cart', 'cart/*',
        'wishlist', 'wishlist/*',
        'reset-password/*',
        'messages/*',
    ];

    /**
     * The policy, with room for the audience measurement if there is any.
     *
     * The host is named rather than the whole web opened: the script may be
     * fetched from PostHog and events may be sent there, and nothing else
     * changes. A shop with no key configured keeps the closed policy exactly
     * as it was — the hole only exists where it is used.
     */
    private static function policy(): string
    {
        $scripts = [];
        $connects = [];

        if (config('services.posthog.key')) {
            $host = (string) config('services.posthog.host');
            $scripts[] = $host;
            $connects[] = $host;
        }

        if (config('services.google_analytics.id')) {
            $scripts[] = 'https://www.googletagmanager.com';
            // The tag is fetched from one host and reports to several: the
            // regional collectors are named by wildcard because Google picks
            // among them by where the visitor is.
            $connects = array_merge($connects, [
                'https://www.google-analytics.com',
                'https://*.google-analytics.com',
                'https://*.analytics.google.com',
                'https://www.googletagmanager.com',
            ]);
        }

        if ($scripts === []) {
            return self::CSP;
        }

        return str_replace(
            ["script-src 'self' 'unsafe-inline'", "default-src 'self'"],
            [
                "script-src 'self' 'unsafe-inline' ".implode(' ', $scripts),
                "default-src 'self'; connect-src 'self' ".implode(' ', $connects),
            ],
            self::CSP,
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        return self::apply($request, $next($request));
    }

    /**
     * Also called from the exception handler in bootstrap/app.php: a
     * response rendered from an exception — a guest's login redirect, a
     * 404 — never travels back through the middleware stack, and would
     * otherwise ship with no security headers at all.
     */
    public static function apply(Request $request, Response $response): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', self::policy());

        // Le back-office ne s'indexe jamais — surtout pas sous un chemin
        // renommé, que robots.txt ne cite plus justement pour le taire.
        $adminPath = config('shop.admin_path');

        if ($request->is($adminPath) || $request->is($adminPath.'/*')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        // Pages behind a login, the cart, and token-bearing links have no
        // business in a search index. The header rides the guest's 302
        // redirect too, which a meta tag in a page never rendered cannot —
        // and unlike a robots.txt Disallow, the crawler gets to read it.
        if ($request->is(...self::PRIVATE_PATHS)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
