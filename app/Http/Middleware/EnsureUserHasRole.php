<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts route access by user role. Usage: ->middleware('role:admin').
 *
 * @author Charlene
 *
 * @version 1.0
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole(...$roles)) {
            abort(403, 'You do not have permission to access this feature.');
        }

        return $next($request);
    }
}
