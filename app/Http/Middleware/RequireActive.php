<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out and redirects deactivated users immediately after login.
 * Applied globally to all authenticated routes.
 */
class RequireActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Ihr Konto wurde deaktiviert. Bitte wenden Sie sich an den Administrator.']);
        }

        return $next($request);
    }
}
