<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_if(! $user, 403);

        if ($user->role === UserRole::Admin) {
            return $next($request);
        }

        abort_unless(in_array($user->role->value, $roles, true), 403);

        return $next($request);
    }
}
