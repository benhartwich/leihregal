<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Bindet die Sitzung an den Passwort-Hash. Voraussetzung dafuer, dass
        // Auth::logoutOtherDevices() beim Passwortwechsel tatsaechlich alle
        // uebrigen Sitzungen entwertet (siehe pages/profile).
        $middleware->appendToGroup('web', \Illuminate\Session\Middleware\AuthenticateSession::class);

        // Force-logout deactivated users on every authenticated request
        $middleware->appendToGroup('web', \App\Http\Middleware\RequireActive::class);

        // Named alias for role-based access: ->middleware('role:admin')
        $middleware->alias([
            'role' => \App\Http\Middleware\RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
