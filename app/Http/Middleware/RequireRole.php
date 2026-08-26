<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Checks that the authenticated user has one of the specified roles.
 * Usage: ->middleware('role:admin') or ->middleware('role:kurator,admin')
 */
class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            abort(403, 'Ihr Konto ist deaktiviert. Bitte wenden Sie sich an den Administrator.');
        }

        $allowed = array_map(fn($r) => UserRole::from($r), $roles);

        if (! in_array($user->role, $allowed)) {
            abort(403, 'Sie haben keine Berechtigung für diesen Bereich.');
        }

        return $next($request);
    }
}
