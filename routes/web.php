<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// Redirect root to login or dashboard
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Health check – DB ping + disk (see spec 7.7)
Route::get('/healthz', function () {
    try {
        \DB::connection()->getPdo();
        $dbOk = true;
    } catch (\Exception $e) {
        $dbOk = false;
    }
    $diskFree = disk_free_space('/') / 1024 / 1024 / 1024;
    return response()->json([
        'status'    => $dbOk ? 'ok' : 'error',
        'db'        => $dbOk ? 'ok' : 'error',
        'disk_free' => round($diskFree, 1) . ' GB',
        'php'       => PHP_VERSION,
    ], $dbOk ? 200 : 503);
});

// Web-App-Manifest. Wird erzeugt statt statisch ausgeliefert, damit Name,
// Beschreibung und Farbe aus der Konfiguration kommen: Eine Einrichtung, die
// die Anwendung unter eigenem Namen betreibt, ändert nur die .env – nicht
// eine Datei unter public/, die beim nächsten Update überschrieben würde.
Route::get('/manifest.webmanifest', function () {
    return response()->json([
        'name'             => config('app.name') . ' – ' . config('branding.untertitel'),
        'short_name'       => config('app.name'),
        'description'      => config('branding.beschreibung'),
        'start_url'        => '/dashboard',
        'scope'            => '/',
        'display'          => 'standalone',
        'orientation'      => 'portrait',
        'background_color' => config('branding.farben.hintergrund'),
        'theme_color'      => config('branding.farben.marke'),
        'lang'             => str_replace('_', '-', app()->getLocale()),
        'dir'              => 'ltr',
        'categories'       => ['education', 'productivity'],
        'icons' => [
            ['src' => '/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
        'shortcuts' => [
            [
                'name'        => 'Medium scannen',
                'short_name'  => 'Scannen',
                'description' => 'Barcode eines Mediums erfassen',
                'url'         => '/ausleihen/scan',
            ],
            ['name' => 'Meine Ausleihen', 'short_name' => 'Ausleihen', 'url' => '/ausleihen'],
            ['name' => 'KI-Assistent',    'short_name' => 'Assistent', 'url' => '/assistent'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest');

// ── Authenticated routes ───────────────────────────────────────────────────

Route::middleware(['auth'])->group(function () {

    // Dashboard (alle eingeloggten Nutzer)
    Volt::route('dashboard', 'pages.dashboard')
        ->name('dashboard');

    // Profil (eigene Daten ändern)
    Volt::route('profile', 'pages.profile')
        ->name('profile');

    // ── Medien ────────────────────────────────────────────────────────────
    Volt::route('medien', 'pages.media.index')
        ->name('media.index');

    // Static routes BEFORE parameterized ones to avoid "neu" matching {media}
    Route::middleware('role:kurator,admin')->group(function () {
        Volt::route('medien/neu', 'pages.media.create')
            ->name('media.create');

        Volt::route('medien/{media}/bearbeiten', 'pages.media.edit')
            ->name('media.edit');
    });

    Volt::route('medien/{media}', 'pages.media.show')
        ->name('media.show');

    // ── Ausleihe & Reservierung ───────────────────────────────────────────
    Volt::route('ausleihen', 'pages.loans.index')
        ->name('loans.index');

    Volt::route('ausleihen/scan', 'pages.loans.scan')
        ->name('loans.scan');

    Volt::route('ausleihen/{loan}/zurueckgeben', 'pages.loans.return')
        ->name('loans.return');

    // Barcode label PDF (kurator/admin only)
    Route::middleware('role:kurator,admin')
        ->get('medien/{media}/barcode', [\App\Http\Controllers\BarcodeController::class, 'single'])
        ->name('media.barcode');

    Route::middleware('role:kurator,admin')
        ->get('barcode/alle', [\App\Http\Controllers\BarcodeController::class, 'batch'])
        ->name('media.barcode.batch');

    // ── KI-Assistent (rate-limited: 10 req/min pro User) ─────────────────
    Route::middleware(['throttle:ai'])->group(function () {
        Volt::route('assistent', 'pages.chat')
            ->name('chat');
    });

    // ── Wünsche (alle Nutzer einreichen, Kuratoren verwalten) ────────────
    Volt::route('wuensche', 'pages.wishes.index')
        ->name('wishes.index');

    Volt::route('wuensche/neu', 'pages.wishes.create')
        ->name('wishes.create');

    Volt::route('merkliste', 'pages.bookmarks')
        ->name('bookmarks');

    // Benachrichtigungs-Center (Spec 4.6)
    Volt::route('benachrichtigungen', 'pages.notifications')
        ->name('notifications');

    // Web-Push: Gerät an- und abmelden (Phase 8)
    Route::post('push/abo', [\App\Http\Controllers\PushSubscriptionController::class, 'store'])
        ->name('push.subscribe');
    Route::delete('push/abo', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])
        ->name('push.unsubscribe');

    Route::post('medien/{media}/bookmark', [\App\Http\Controllers\BookmarkController::class, 'toggle'])
        ->name('media.bookmark');

    // ── Kuration (Kurator/Admin) ──────────────────────────────────────────
    Route::middleware('role:kurator,admin')->prefix('kuration')->name('curation.')->group(function () {
        Volt::route('/', 'pages.curation.index')
            ->name('index');

        Volt::route('whitelist', 'pages.curation.whitelist')
            ->name('whitelist');

        Volt::route('wuensche', 'pages.curation.wishes')
            ->name('wishes');

        Volt::route('anschaffungen', 'pages.curation.acquisitions')
            ->name('acquisitions');

        // PDF + CSV export
        Route::get('anschaffungen/pdf', [\App\Http\Controllers\AcquisitionController::class, 'pdf'])
            ->name('acquisitions.pdf');

        Route::get('anschaffungen/csv', [\App\Http\Controllers\AcquisitionController::class, 'csv'])
            ->name('acquisitions.csv');

        // Bestandsliste PDF
        Route::get('bestandsliste', [\App\Http\Controllers\AcquisitionController::class, 'inventoryPdf'])
            ->name('inventory.pdf');

        Volt::route('statistiken', 'pages.curation.stats')
            ->name('stats');
    });

    // ── Admin-Bereich ──────────────────────────────────────────────────────
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Volt::route('nutzer', 'pages.admin.users')
            ->name('users');

        Volt::route('nutzer/neu', 'pages.admin.users-create')
            ->name('users.create');

        Volt::route('nutzer/{user}/bearbeiten', 'pages.admin.users-edit')
            ->name('users.edit');

        Volt::route('einstellungen', 'pages.admin.settings')
            ->name('settings');

        Volt::route('standorte', 'pages.admin.locations')
            ->name('locations');

        Volt::route('ausleihen', 'pages.admin.loans')
            ->name('loans');

        // Protokoll der Kurations- und Admin-Aktionen (Spec 5)
        Volt::route('protokoll', 'pages.admin.audit-log')
            ->name('audit');
    });

});

require __DIR__.'/auth.php';
