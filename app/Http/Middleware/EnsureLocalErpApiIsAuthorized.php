<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalErpApiIsAuthorized
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('erp.local_api.require_https') && ! $request->secure()) {
            abort(403, 'HTTPS obrigatorio.');
        }

        $allowedIps = config('erp.local_api.allowed_ips', []);

        if ($allowedIps && ! in_array($request->ip(), $allowedIps, true)) {
            abort(403, 'IP nao autorizado.');
        }

        $configuredToken = (string) config('erp.local_api.token');
        $requestToken = (string) $request->bearerToken();

        if ($configuredToken === '' || $requestToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            abort(401, 'Token invalido.');
        }

        return $next($request);
    }
}
