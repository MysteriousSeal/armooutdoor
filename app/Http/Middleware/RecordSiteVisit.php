<?php

namespace App\Http\Middleware;

use App\Services\VisitCounterService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordSiteVisit
{
    public function __construct(private VisitCounterService $visitCounter) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldRecord($request)) {
            // Deferred so the geo lookup (up to 1.5s on a cache miss) runs
            // after the response is sent, never in the visitor's wait time.
            // always(): redirects and 404s are traffic too.
            defer(fn () => $this->visitCounter->record($request))->always();
        }

        return $next($request);
    }

    private function shouldRecord(Request $request): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        $adminPath = config('shop.admin_path');

        if ($request->is($adminPath, $adminPath.'/*', 'up', 'sitemap.xml', 'robots.txt')) {
            return false;
        }

        return true;
    }
}
