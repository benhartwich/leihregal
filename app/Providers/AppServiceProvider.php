<?php

namespace App\Providers;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Sperrt migrate:fresh, migrate:refresh, migrate:reset und db:wipe,
        // sobald APP_ENV=production ist. Zweite Schutzschicht neben der
        // Datenbank-Prüfung in tests/TestCase.php.
        DB::prohibitDestructiveCommands($this->app->isProduction());

        // Eigener Versandweg fuer Web-Push (Phase 8).
        Notification::extend("webpush", fn () => new WebPushChannel());

        // 10 AI requests per minute per authenticated user
        RateLimiter::for('ai', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
