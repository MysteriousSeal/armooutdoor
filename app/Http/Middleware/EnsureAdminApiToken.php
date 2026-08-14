<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.admin_api.token');
        $provided = (string) $request->bearerToken();

        if ($token === '' || ! hash_equals($token, $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
