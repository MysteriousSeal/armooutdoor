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
     * The policy, with room for the audience measurement if there is any.
     *
     * The host is named rather than the whole web opened: the script may be
     * fetched from PostHog and events may be sent there, and nothing else
     * changes. A shop with no key configured keeps the closed policy exactly
     * as it was — the hole only exists where it is used.
     */
    private static function policy(): string
    {
        $host = config('services.posthog.key') ? config('services.posthog.host') : null;

        if (! is_string($host) || $host === '') {
            return self::CSP;
        }

        return str_replace(
            ["script-src 'self' 'unsafe-inline'", "default-src 'self'"],
            ["script-src 'self' 'unsafe-inline' {$host}", "default-src 'self'; connect-src 'self' {$host}"],
            self::CSP,
        );
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

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

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
