<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * One shop, one hostname.
 *
 * The site answered on both armooutdoor.fr and www.armooutdoor.fr, and every
 * page served under www named itself as its own canonical: Laravel builds
 * absolute URLs from the incoming request, not from APP_URL. Search engines
 * were therefore shown the whole catalogue twice, with neither copy pointing
 * at the other — the ranking signals of every page split in two.
 *
 * This lives in the application rather than in nginx because the server
 * configuration is not in this repository: here it ships with the deploy.
 * An nginx-level redirect would be cheaper still, and would make this a
 * no-op rather than a conflict.
 */
class RedirectWwwToApex
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (! str_starts_with($host, 'www.')) {
            return $next($request);
        }

        $apex = substr($host, 4);

        // "www." alone leaves no name to redirect to, so it is served as-is
        // rather than sent to an empty host.
        if ($apex === '') {
            return $next($request);
        }

        $scheme = $request->getScheme();
        $port = $request->getPort();
        $authority = $port === ($scheme === 'https' ? 443 : 80)
            ? $apex
            : $apex.':'.$port;

        // A browser turns a 301 on a POST into a GET and drops the body, so
        // only reads get the permanent redirect search engines need to follow;
        // anything else keeps its method through a 308.
        $status = $request->isMethodSafe() ? 301 : 308;

        return new RedirectResponse(
            $scheme.'://'.$authority.$request->getRequestUri(),
            $status
        );
    }
}
