<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminApiToken
{
    /**
     * Marque laissée sur la requête une fois le jeton vérifié.
     *
     * Les FormRequest de l'API s'y réfèrent plutôt que d'autoriser sans
     * condition : si l'une d'elles était un jour utilisée sur une route non
     * protégée, elle refuserait au lieu de laisser passer.
     */
    public const VERIFIED_ATTRIBUTE = 'admin_api_token_verified';

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('services.admin_api.token');
        $provided = (string) $request->bearerToken();

        if ($token === '' || ! hash_equals($token, $provided)) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set(self::VERIFIED_ATTRIBUTE, true);

        return $next($request);
    }
}
