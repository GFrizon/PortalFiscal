<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChangeIsNotForced
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->force_password_change) {
            return $next($request);
        }

        if ($request->routeIs('password.change', 'password.update', 'logout')) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
